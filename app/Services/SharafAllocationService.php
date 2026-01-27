<?php

namespace App\Services;

use App\Models\SharafMember;
use Illuminate\Support\Facades\DB;

class SharafAllocationService
{
    /**
     * Add a member to a sharaf position.
     *
     * @param int $sharafId
     * @param int $positionId
     * @param string $its
     * @param int|null $spKeyno Optional sp_keyno. If not provided, will be auto-assigned.
     * @return array Returns array with 'warnings' key containing any warnings
     */
    public function addMember(int $sharafId, int $positionId, string $its, ?int $spKeyno = null): array
    {
        return DB::transaction(function () use ($sharafId, $positionId, $its, $spKeyno) {
            $warnings = [];

            // Check if person is already allocated to another sharaf
            $existingAllocations = SharafMember::where('its_id', $its)
                ->where('sharaf_id', '!=', $sharafId)
                ->get();

            if ($existingAllocations->isNotEmpty()) {
                $warnings['allocated_elsewhere'] = $existingAllocations->map(function ($member) {
                    return [
                        'sharaf_id' => $member->sharaf_id,
                        'sharaf_position_id' => $member->sharaf_position_id,
                    ];
                })->toArray();
            }

            // Auto-assign sp_keyno if not provided
            if ($spKeyno === null) {
                $maxKeyno = SharafMember::where('sharaf_id', $sharafId)
                    ->where('sharaf_position_id', $positionId)
                    ->max('sp_keyno');

                $spKeyno = ($maxKeyno !== null) ? $maxKeyno + 1 : 1;
            }

            // Create the member record
            // Note: Unique constraint (sharaf_id, its_id) will prevent duplicates
            // if person is already in this sharaf (different position)
            SharafMember::create([
                'sharaf_id' => $sharafId,
                'sharaf_position_id' => $positionId,
                'its_id' => $its,
                'sp_keyno' => $spKeyno,
            ]);

            return ['warnings' => $warnings];
        });
    }

    /**
     * Remove a member from a sharaf.
     *
     * @param int $sharafId
     * @param string $its
     * @return void
     */
    public function removeMember(int $sharafId, string $its): void
    {
        SharafMember::where('sharaf_id', $sharafId)
            ->where('its_id', $its)
            ->delete();
    }

    /**
     * Reorder members within a sharaf position.
     *
     * @param int $sharafId
     * @param int $positionId
     * @param array $orderedItsList Array of ITS numbers in desired order
     * @return void
     */
    public function reorderPositionMembers(int $sharafId, int $positionId, array $orderedItsList): void
    {
        DB::transaction(function () use ($sharafId, $positionId, $orderedItsList) {
            // Get all members for this sharaf position
            $members = SharafMember::where('sharaf_id', $sharafId)
                ->where('sharaf_position_id', $positionId)
                ->get()
                ->keyBy('its_id');

            // Update sp_keyno sequentially for members in the ordered list
            foreach ($orderedItsList as $index => $its) {
                $its = (string) (int) $its; // Normalize ITS format
                $spKeyno = $index + 1;

                if ($members->has($its)) {
                    $members->get($its)->update(['sp_keyno' => $spKeyno]);
                }
            }
        });
    }
}
