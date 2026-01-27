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

        $query = Sharaf::with(['sharafDefinition', 'sharafMembers.sharafPosition', 'sharafClearances']);

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
            'lagat_paid' => ['nullable', 'boolean'],
            'najwa_ada_paid' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        // Check if rank is unique within sharaf_definition_id
        $exists = Sharaf::where('sharaf_definition_id', $request->input('sharaf_definition_id'))
            ->where('rank', $request->input('rank'))
            ->exists();

        if ($exists) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                'A sharaf with this rank already exists for the given sharaf definition.',
                422
            );
        }

        $sharaf = Sharaf::create([
            'sharaf_definition_id' => $request->input('sharaf_definition_id'),
            'rank' => $request->input('rank'),
            'capacity' => $request->input('capacity'),
            'status' => $request->input('status', 'pending'),
            'hof_its' => $request->input('hof_its'),
            'lagat_paid' => $request->input('lagat_paid', false),
            'najwa_ada_paid' => $request->input('najwa_ada_paid', false),
        ]);

        return $this->jsonSuccessWithData($sharaf->load(['sharafDefinition', 'sharafMembers.sharafPosition', 'sharafClearances']), 201);
    }

    public function show(string $sharaf_id): JsonResponse
    {
        $sharaf = Sharaf::with(['sharafDefinition', 'sharafMembers.sharafPosition', 'sharafClearances'])
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
}
