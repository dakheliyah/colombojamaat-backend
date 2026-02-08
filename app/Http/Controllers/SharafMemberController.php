<?php

namespace App\Http\Controllers;

use App\Exceptions\DuplicateSharafAssignmentException;
use App\Models\SharafMember;
use App\Services\SharafAllocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SharafMemberController extends Controller
{
    public function __construct(
        protected SharafAllocationService $allocationService
    ) {}

    public function index(string $sharaf_id): JsonResponse
    {
        $members = SharafMember::where('sharaf_id', $sharaf_id)
            ->with('sharafPosition')
            ->get();

        return $this->jsonSuccessWithData($members);
    }

    public function store(Request $request, string $sharaf_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'its' => ['required', 'numeric'],
            'position_id' => ['required', 'integer', 'exists:sharaf_positions,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'najwa' => ['nullable', 'string', 'max:50'],
            'on_vms' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $its = (string) (int) $request->input('its');
        $positionId = (int) $request->input('position_id');
        $spKeyno = $request->has('sp_keyno') ? (int) $request->input('sp_keyno') : null;
        $name = $request->input('name');
        $phone = $request->input('phone');
        $najwa = $request->input('najwa');
        $onVms = $request->has('on_vms') ? $request->boolean('on_vms') : false;

        try {
            $result = $this->allocationService->addMember((int) $sharaf_id, $positionId, $its, $spKeyno, $name, $phone, $najwa, $onVms);

            $warnings = $this->mapWarnings($result['warnings'] ?? []);

            if (!empty($warnings)) {
                return $this->jsonSuccessWithWarnings($warnings);
            }

            return $this->jsonSuccess();
        } catch (DuplicateSharafAssignmentException $e) {
            return $this->jsonError(
                'DUPLICATE_ASSIGNMENT',
                $e->getMessage(),
                422
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->jsonError(
                'NOT_FOUND',
                'Sharaf not found.',
                404
            );
        } catch (\Exception $e) {
            // Log the exception for debugging
            \Log::error('Error adding sharaf member: ' . $e->getMessage(), [
                'exception' => $e,
                'sharaf_id' => $sharaf_id,
                'its' => $its,
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->jsonError(
                'INTERNAL_ERROR',
                'An error occurred while adding the member. Please check the logs for details.',
                500
            );
        }
    }

    public function update(Request $request, string $sharaf_id, string $its): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $member = SharafMember::where('sharaf_id', $sharaf_id)
            ->where('its_id', $its)
            ->firstOrFail();

        $member->update(['name' => $request->input('name')]);
        $member->load('sharafPosition');

        return $this->jsonSuccessWithData($member);
    }

    public function updateOnVmsBulk(Request $request, string $sharaf_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'its_ids' => ['required', 'array'],
            'its_ids.*' => ['string', 'numeric'],
            'on_vms' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $itsIds = array_map(fn ($v) => (string) (int) $v, $request->input('its_ids'));
        $itsIds = array_values(array_unique(array_filter($itsIds)));

        $updatedCount = SharafMember::where('sharaf_id', $sharaf_id)
            ->whereIn('its_id', $itsIds)
            ->update(['on_vms' => $request->boolean('on_vms')]);

        $members = SharafMember::where('sharaf_id', $sharaf_id)
            ->whereIn('its_id', $itsIds)
            ->with('sharafPosition')
            ->get();

        return $this->jsonSuccessWithData([
            'updated_count' => $updatedCount,
            'members' => $members,
        ]);
    }

    public function updateOnVms(Request $request, string $sharaf_id, string $its): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'on_vms' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $member = SharafMember::where('sharaf_id', $sharaf_id)
            ->where('its_id', $its)
            ->firstOrFail();

        $member->update(['on_vms' => $request->boolean('on_vms')]);
        $member->load('sharafPosition');

        return $this->jsonSuccessWithData($member);
    }

    public function destroy(string $sharaf_id, string $its): JsonResponse
    {
        $this->allocationService->removeMember((int) $sharaf_id, $its);

        return $this->jsonSuccess();
    }

    /**
     * Map service warnings to API format.
     * same_miqaat -> SAME_MIQAAT_ASSIGNMENT with sharaf_id and sharaf_info.
     */
    protected function mapWarnings(array $serviceWarnings): array
    {
        $api = [];
        
        // Handle same miqaat assignments
        $sameMiqaat = $serviceWarnings['same_miqaat'] ?? [];
        if (!empty($sameMiqaat)) {
            foreach ($sameMiqaat as $assignment) {
                $api[] = [
                    'type' => 'SAME_MIQAAT_ASSIGNMENT',
                    'message' => "This person is already assigned to sharaf ID {$assignment['sharaf_id']} in the same miqaat.",
                    'sharaf_id' => $assignment['sharaf_id'],
                    'sharaf_info' => $assignment['sharaf_info'],
                ];
            }
        }
        
        return $api;
    }
}
