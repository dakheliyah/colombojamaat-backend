<?php

namespace App\Http\Controllers;

use App\Enums\SharafStatus;
use App\Models\Sharaf;
use App\Services\SharafApprovalService;
use App\Services\SharafConfirmationEvaluator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SharafController extends Controller
{
    protected $approvalService;
    protected $confirmationEvaluator;

    public function __construct(
        SharafApprovalService $approvalService,
        SharafConfirmationEvaluator $confirmationEvaluator
    ) {
        $this->approvalService = $approvalService;
        $this->confirmationEvaluator = $confirmationEvaluator;
    }

    /**
     * Get all sharafs with optional filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sharaf_definition_id' => ['nullable', 'integer', 'exists:sharaf_definitions,id'],
            'status' => ['nullable', 'string', 'in:pending,bs_approved,confirmed,rejected,cancelled'],
            'hof_its' => ['nullable', 'string'],
            'member_its' => ['nullable', 'string'],
            'its_id' => ['nullable', 'string'],
            'token' => ['nullable', 'string'],
            'hof_name' => ['nullable', 'string'],
            'name' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $query = Sharaf::query()
            ->whereHas('sharafDefinition.event.miqaat', fn ($q) => $q->active())
            ->leftJoin('census', 'sharafs.hof_its', '=', 'census.its_id')
            ->select('sharafs.*', 'census.name as hof_name')
            ->with(['sharafDefinition', 'sharafMembers.sharafPosition', 'sharafClearances', 'sharafPayments.paymentDefinition']);

        // Apply filters
        if ($request->has('sharaf_definition_id')) {
            $query->where('sharaf_definition_id', $request->input('sharaf_definition_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('hof_its')) {
            $query->where('sharafs.hof_its', $request->input('hof_its'));
        }

        if ($request->has('token')) {
            $query->where('sharafs.token', 'like', '%' . $request->input('token') . '%');
        }

        if ($request->has('hof_name')) {
            $query->where('census.name', 'like', '%' . $request->input('hof_name') . '%');
        }

        if ($request->has('name')) {
            $query->where('sharafs.name', 'like', '%' . $request->input('name') . '%');
        }

        // Filter by person: sharafs where the given ITS ID is HOF or a member (member_its / its_id are aliases)
        $memberIts = $request->input('member_its') ?? $request->input('its_id');
        if ($memberIts !== null && $memberIts !== '') {
            $query->where(function ($q) use ($memberIts) {
                $q->where('sharafs.hof_its', $memberIts)
                    ->orWhereExists(function ($sub) use ($memberIts) {
                        $sub->select(DB::raw(1))
                            ->from('sharaf_members')
                            ->whereColumn('sharaf_members.sharaf_id', 'sharafs.id')
                            ->where('sharaf_members.its_id', $memberIts);
                    });
            });
        }

        $sharafs = $query->orderBy('sharaf_definition_id')->orderBy('rank')->get();

        return $this->jsonSuccessWithData($sharafs);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sharaf_definition_id' => ['required', 'integer', 'exists:sharaf_definitions,id'],
            'rank' => ['required', 'integer', 'min:0'],
            'name' => ['nullable', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'in:pending,bs_approved,confirmed,rejected,cancelled'],
            'hof_its' => ['required', 'string'],
            'token' => ['nullable', 'string', 'max:50', 'unique:sharafs,token'],
            'comments' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        // Auto-assign rank if rank is 0 (after all validations pass)
        $requestedRank = $request->input('rank');
        $sharafDefinitionId = $request->input('sharaf_definition_id');
        
        if ($requestedRank === 0) {
            // Find the maximum rank for this sharaf_definition_id
            $maxRank = Sharaf::where('sharaf_definition_id', $sharafDefinitionId)
                ->max('rank');
            
            // Assign next available rank (maxRank + 1, or 1 if no sharafs exist)
            $requestedRank = ($maxRank !== null) ? $maxRank + 1 : 1;
        }

        // Check if rank is unique within sharaf_definition_id
        $exists = Sharaf::where('sharaf_definition_id', $sharafDefinitionId)
            ->where('rank', $requestedRank)
            ->exists();

        if ($exists) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                'A sharaf with this rank already exists for the given sharaf definition.',
                422
            );
        }

        $hofIts = $request->input('hof_its');

        // CRITICAL: Check if the same HOF is already assigned to a sharaf in the SAME sharaf_definition_id
        // This should be an ERROR and prevent creation
        $duplicateHofInSameDefinition = Sharaf::where('sharaf_definition_id', $sharafDefinitionId)
            ->where('hof_its', $hofIts)
            ->exists();

        if ($duplicateHofInSameDefinition) {
            return $this->jsonError(
                'DUPLICATE_HOF_ASSIGNMENT',
                'This HOF is already assigned to a sharaf in the same sharaf definition. A HOF cannot have multiple sharafs within the same sharaf definition.',
                422
            );
        }

        // Check if the same HOF is already assigned to a different sharaf in the same miqaat (but different sharaf_definition)
        // This should be a WARNING but still allow creation
        $warnings = [];
        $targetSharafDefinitionId = $sharafDefinitionId;

        // Get the target sharaf_definition's miqaat_id
        $targetSharafDefinition = \App\Models\SharafDefinition::with('event')
            ->findOrFail($targetSharafDefinitionId);
        
        if ($targetSharafDefinition->event) {
            $targetMiqaatId = $targetSharafDefinition->event->miqaat_id;

            // Check if the same HOF is assigned to a different sharaf in the same miqaat
            $sameMiqaatSharafs = \Illuminate\Support\Facades\DB::table('sharafs as s')
                ->join('sharaf_definitions as sd', 's.sharaf_definition_id', '=', 'sd.id')
                ->join('events as e', 'sd.event_id', '=', 'e.id')
                ->join('miqaats as m', 'e.miqaat_id', '=', 'm.id')
                ->where('s.hof_its', $hofIts)
                ->where('sd.id', '!=', $targetSharafDefinitionId)  // Different sharaf_definition
                ->where('m.id', $targetMiqaatId)  // Same miqaat
                ->select('s.id as sharaf_id', 's.rank', 's.capacity', 's.status', 'sd.id as sharaf_definition_id', 'sd.name as sharaf_definition_name')
                ->get();

            if ($sameMiqaatSharafs->isNotEmpty()) {
                $warnings['same_miqaat_hof'] = $sameMiqaatSharafs->map(function ($existingSharaf) {
                    return [
                        'sharaf_id' => $existingSharaf->sharaf_id,
                        'sharaf_info' => [
                            'id' => $existingSharaf->sharaf_id,
                            'rank' => $existingSharaf->rank,
                            'capacity' => $existingSharaf->capacity,
                            'status' => $existingSharaf->status,
                            'sharaf_definition_id' => $existingSharaf->sharaf_definition_id,
                            'sharaf_definition_name' => $existingSharaf->sharaf_definition_name,
                        ],
                    ];
                })->toArray();
            }
        }

        $sharaf = Sharaf::create([
            'sharaf_definition_id' => $sharafDefinitionId,
            'rank' => $requestedRank, // Use auto-assigned rank if rank was 0
            'name' => $request->input('name'),
            'capacity' => $request->input('capacity'),
            'status' => $request->input('status') ?: 'pending',
            'hof_its' => $request->input('hof_its'),
            'token' => $request->input('token'),
            'comments' => $request->input('comments'),
        ]);

        $sharaf->load(['sharafDefinition', 'sharafMembers.sharafPosition', 'sharafClearances', 'sharafPayments.paymentDefinition']);

        // If there are warnings, return them with the created sharaf
        if (!empty($warnings)) {
            $apiWarnings = $this->mapSharafWarnings($warnings);
            return response()->json([
                'success' => true,
                'data' => $sharaf,
                'warnings' => $apiWarnings,
            ], 201);
        }

        return $this->jsonSuccessWithData($sharaf, 201);
    }

    public function update(Request $request, string $sharaf_id): JsonResponse
    {
        $sharaf = Sharaf::findOrFail($sharaf_id);

        $validator = Validator::make($request->all(), [
            'sharaf_definition_id' => ['nullable', 'integer', 'exists:sharaf_definitions,id'],
            'rank' => ['nullable', 'integer', 'min:0'],
            'name' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'in:pending,bs_approved,confirmed,rejected,cancelled'],
            'hof_its' => ['nullable', 'string'],
            'token' => ['nullable', 'string', 'max:50', 'unique:sharafs,token,' . $sharaf->id],
            'comments' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $updateData = $request->only([
            'sharaf_definition_id',
            'rank',
            'name',
            'capacity',
            'status',
            'hof_its',
            'token',
            'comments',
        ]);

        // Only update status if it's explicitly provided and not empty
        if (array_key_exists('status', $updateData) && empty($updateData['status'])) {
            unset($updateData['status']);
        }

        try {
            DB::transaction(function () use ($sharaf, &$updateData) {
                if (!array_key_exists('rank', $updateData)) {
                    $sharaf->update($updateData);
                    return;
                }

                $requestedRank = (int) $updateData['rank'];
                $sharafDefinitionId = $updateData['sharaf_definition_id'] ?? $sharaf->sharaf_definition_id;
                $currentRank = (int) $sharaf->rank;

                if ($requestedRank === $currentRank) {
                    unset($updateData['rank']);
                    $sharaf->update($updateData);
                    return;
                }

                if ($requestedRank === 0) {
                    $maxRank = Sharaf::where('sharaf_definition_id', $sharafDefinitionId)->max('rank');
                    $updateData['rank'] = ($maxRank !== null) ? $maxRank + 1 : 1;
                    $sharaf->update($updateData);
                    return;
                }

                $existingSharaf = Sharaf::where('sharaf_definition_id', $sharafDefinitionId)
                    ->where('rank', $requestedRank)
                    ->where('id', '!=', $sharaf->id)
                    ->first();

                if ($existingSharaf) {
                    $tempRank = Sharaf::where('sharaf_definition_id', $sharafDefinitionId)->max('rank') + 1;
                    $sharaf->update(['rank' => $tempRank]);
                    $existingSharaf->update(['rank' => $currentRank]);
                    $updateData['rank'] = $requestedRank;
                }

                $sharaf->update($updateData);
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                'A sharaf with this rank already exists for the given sharaf definition.',
                422
            );
        }

        $sharaf->refresh();
        $sharaf->load(['sharafDefinition', 'sharafMembers.sharafPosition', 'sharafClearances', 'sharafPayments.paymentDefinition']);

        return $this->jsonSuccessWithData($sharaf);
    }

    public function show(string $sharaf_id): JsonResponse
    {
        $sharaf = Sharaf::with(['sharafDefinition', 'sharafMembers.sharafPosition', 'sharafClearances', 'sharafPayments.paymentDefinition', 'hof'])
            ->findOrFail($sharaf_id);

        return $this->jsonSuccessWithData($sharaf);
    }

    public function destroy(string $sharaf_id): JsonResponse
    {
        $sharaf = Sharaf::findOrFail($sharaf_id);
        $sharaf->delete();

        return $this->jsonSuccess();
    }

    public function status(Request $request, string $sharaf_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', 'in:pending,bs_approved,confirmed,rejected,cancelled'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $this->approvalService->changeStatus((int) $sharaf_id, SharafStatus::from($request->input('status')));

        return $this->jsonSuccess();
    }

    public function evaluateConfirmation(string $sharaf_id): JsonResponse
    {
        $this->confirmationEvaluator->evaluateAndUpdate((int) $sharaf_id);

        return $this->jsonSuccess();
    }

    /**
     * Map service warnings to API format for sharaf creation.
     */
    protected function mapSharafWarnings(array $warnings): array
    {
        $api = [];
        
        // Handle same miqaat HOF assignments
        $sameMiqaatHof = $warnings['same_miqaat_hof'] ?? [];
        if (!empty($sameMiqaatHof)) {
            foreach ($sameMiqaatHof as $existingSharaf) {
                $sharafDefName = $existingSharaf['sharaf_info']['sharaf_definition_name'] ?? 'Sharaf Definition ' . $existingSharaf['sharaf_info']['sharaf_definition_id'];
                $api[] = [
                    'type' => 'SAME_MIQAAT_HOF',
                    'message' => "This HOF is already assigned to sharaf ID {$existingSharaf['sharaf_id']} ({$sharafDefName} - Rank {$existingSharaf['sharaf_info']['rank']}) in the same miqaat.",
                    'sharaf_id' => $existingSharaf['sharaf_id'],
                    'sharaf_info' => $existingSharaf['sharaf_info'],
                ];
            }
        }
        
        return $api;
    }
}
