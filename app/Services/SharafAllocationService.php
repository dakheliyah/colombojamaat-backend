<?php

namespace App\Services;

use App\Exceptions\DuplicateSharafAssignmentException;
use App\Models\Sharaf;
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
     * @param string|null $name Optional name of the member
     * @param string|null $phone Optional phone number of the member
     * @param string|null $najwa Optional najwa number of the member
     * @param bool|null $onVms Optional on_vms flag. Defaults to 0.
     * @return array Returns array with 'warnings' key containing any warnings
     * @throws DuplicateSharafAssignmentException If person is already assigned to the same sharaf
     */
    public function addMember(int $sharafId, int $positionId, string $its, ?int $spKeyno = null, ?string $name = null, ?string $phone = null, ?string $najwa = null, ?bool $onVms = false): array
    {
        return DB::transaction(function () use ($sharafId, $positionId, $its, $spKeyno, $name, $phone, $najwa, $onVms) {
            // VALIDATION STEP 1: Check if person is already assigned to the SAME sharaf_id
            // This should return an ERROR and prevent the addition
            $duplicateAssignment = SharafMember::where('sharaf_id', $sharafId)
                ->where('its_id', $its)
                ->exists();

            if ($duplicateAssignment) {
                throw new DuplicateSharafAssignmentException('This person is already assigned to this sharaf.');
            }

            // Get the target sharaf's miqaat_id for comparison
            $targetSharaf = Sharaf::with(['sharafDefinition.event'])
                ->findOrFail($sharafId);
            
            if (!$targetSharaf->sharafDefinition) {
                throw new \RuntimeException('Sharaf definition not found for sharaf ID: ' . $sharafId);
            }
            
            if (!$targetSharaf->sharafDefinition->event) {
                throw new \RuntimeException('Event not found for sharaf definition ID: ' . $targetSharaf->sharafDefinition->id);
            }
            
            $targetMiqaatId = $targetSharaf->sharafDefinition->event->miqaat_id;

            $warnings = [];

            // VALIDATION STEP 2: Check if person is assigned to a DIFFERENT sharaf in the SAME miqaat
            // This should return a WARNING but still allow the user to proceed
            // Flow: sharaf_members -> sharafs -> sharaf_definitions -> events -> miqaats
            // We check: different sharaf_id, same miqaat_id (which may be in same or different sharaf_definition)
            $sameMiqaatAssignments = DB::table('sharaf_members as sm')
                ->join('sharafs as s', 'sm.sharaf_id', '=', 's.id')
                ->join('sharaf_definitions as sd', 's.sharaf_definition_id', '=', 'sd.id')
                ->join('events as e', 'sd.event_id', '=', 'e.id')
                ->join('miqaats as m', 'e.miqaat_id', '=', 'm.id')
                ->where('sm.its_id', $its)
                ->where('sm.sharaf_id', '!=', $sharafId)  // Different sharaf
                ->where('m.id', $targetMiqaatId)  // Same miqaat
                ->select('s.id as sharaf_id', 's.rank', 'sd.id as sharaf_definition_id', 'sd.name as sharaf_definition_name')
                ->get();

            if ($sameMiqaatAssignments->isNotEmpty()) {
                $warnings['same_miqaat'] = $sameMiqaatAssignments->map(function ($assignment) {
                    // Construct name from sharaf definition name and rank, or fallback to rank-based name
                    $name = $assignment->sharaf_definition_name 
                        ? $assignment->sharaf_definition_name . ' - Rank ' . $assignment->rank
                        : 'Sharaf Group ' . $assignment->rank;
                    
                    return [
                        'sharaf_id' => $assignment->sharaf_id,
                        'sharaf_info' => [
                            'id' => $assignment->sharaf_id,
                            'rank' => $assignment->rank,
                            'name' => $name,
                        ],
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
                'name' => $name,
                'phone' => $phone,
                'najwa' => $najwa,
                'on_vms' => $onVms ?? false,
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
