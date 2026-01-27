<?php

namespace App\Http\Controllers;

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

        $result = $this->allocationService->addMember((int) $sharaf_id, $positionId, $its, $spKeyno);

        $warnings = $this->mapWarnings($result['warnings'] ?? []);

        if (!empty($warnings)) {
            return $this->jsonSuccessWithWarnings($warnings);
        }

        return $this->jsonSuccess();
    }

    public function destroy(string $sharaf_id, string $its): JsonResponse
    {
        $this->allocationService->removeMember((int) $sharaf_id, $its);

        return $this->jsonSuccess();
    }

    /**
     * Map service warnings to API format.
     * allocated_elsewhere -> ALREADY_ALLOCATED with sharaf_ids.
     */
    protected function mapWarnings(array $serviceWarnings): array
    {
        $api = [];
        $elsewhere = $serviceWarnings['allocated_elsewhere'] ?? [];
        if (!empty($elsewhere)) {
            $sharafIds = array_values(array_unique(array_column($elsewhere, 'sharaf_id')));
            $api[] = [
                'type' => 'ALREADY_ALLOCATED',
                'message' => 'This person is already allocated to another sharaf.',
                'sharaf_ids' => $sharafIds,
            ];
        }
        return $api;
    }
}
