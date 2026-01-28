<?php

namespace App\Http\Controllers;

use App\Enums\SharafStatus;
use App\Models\Sharaf;
use App\Services\SharafApprovalService;
use App\Services\SharafConfirmationEvaluator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SharafController extends Controller
{
    public function __construct(
        protected SharafApprovalService $approvalService,
        protected SharafConfirmationEvaluator $confirmationEvaluator
    ) {}

    /**
     * Get all sharafs with optional filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sharaf_definition_id' => ['nullable', 'integer', 'exists:sharaf_definitions,id'],
            'status' => ['nullable', 'string', 'in:pending,bs_approved,confirmed,rejected,cancelled'],
            'hof_its' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $query = Sharaf::with(['sharafDefinition', 'sharafMembers.sharafPosition', 'sharafClearances', 'sharafPayments.paymentDefinition']);

        // Apply filters
        if ($request->has('sharaf_definition_id')) {
            $query->where('sharaf_definition_id', $request->input('sharaf_definition_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('hof_its')) {
            $query->where('hof_its', $request->input('hof_its'));
        }

        // Pagination
        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);

        $results = $query->orderBy('sharaf_definition_id')->orderBy('rank')->paginate($perPage, ['*'], 'page', $page);

        return $this->jsonSuccessWithData([
            'data' => $results->items(),
            'pagination' => [
                'current_page' => $results->currentPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
                'last_page' => $results->lastPage(),
                'from' => $results->firstItem(),
                'to' => $results->lastItem(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sharaf_definition_id' => ['required', 'integer', 'exists:sharaf_definitions,id'],
            'rank' => ['required', 'integer', 'min:0'],
            'capacity' => ['required', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'in:pending,bs_approved,confirmed,rejected,cancelled'],
            'hof_its' => ['required', 'string'],
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
            'capacity' => $request->input('capacity'),
            'status' => $request->input('status', 'pending'),
            'hof_its' => $request->input('hof_its'),
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

    public function show(string $sharaf_id): JsonResponse
    {
        $sharaf = Sharaf::with(['sharafDefinition', 'sharafMembers.sharafPosition', 'sharafClearances', 'sharafPayments.paymentDefinition'])
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
