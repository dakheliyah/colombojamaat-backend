<?php

namespace App\Http\Controllers;

use App\Services\ReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    public function __construct(
        protected ReportingService $reportingService
    ) {}

    /**
     * GET: List all available entity types for reporting.
     */
    public function entities(): JsonResponse
    {
        $entities = $this->reportingService->getAvailableEntities();
        
        $result = [];
        foreach ($entities as $key => $description) {
            $result[] = [
                'type' => $key,
                'description' => $description,
            ];
        }

        return $this->jsonSuccessWithData($result);
    }

    /**
     * GET: Census mumineen with no sharaf allocation for a miqaat.
     * Query params: miqaat_id (optional, defaults to active), q, city, jamaat, gender, etc.,
     * format=json|csv, page, per_page.
     */
    public function censusWithoutSharaf(Request $request): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $validator = Validator::make($request->all(), [
            'miqaat_id' => ['nullable', 'integer', 'min:1'],
            'format' => ['nullable', 'string', 'in:json,csv'],
            'q' => ['nullable', 'string', 'max:255'],
            'its_id' => ['nullable', 'string'],
            'hof_id' => ['nullable', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'jamaat' => ['nullable', 'string', 'max:255'],
            'jamiat' => ['nullable', 'string', 'max:255'],
            'mohalla' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:50'],
            'misaq' => ['nullable', 'string', 'max:255'],
            'marital_status' => ['nullable', 'string', 'max:255'],
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

        try {
            $filters = $request->all();

            if ($request->input('format') === 'csv') {
                return $this->reportingService->exportCensusWithoutSharafToCsv($filters);
            }

            $perPage = (int) $request->input('per_page', 50);
            $page = (int) $request->input('page', 1);

            $results = $this->reportingService
                ->buildCensusWithoutSharafQuery($filters)
                ->orderByRaw("name IS NULL OR name = ''")
                ->orderBy('name')
                ->paginate($perPage, ['*'], 'page', $page);

            $results->getCollection()->each->makeHidden(['pwd']);

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
                'miqaat_id' => isset($filters['miqaat_id']) && $filters['miqaat_id'] !== ''
                    ? (int) $filters['miqaat_id']
                    : \App\Models\Miqaat::getActiveId(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError('VALIDATION_ERROR', $e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->jsonError('SERVER_ERROR', 'Failed to generate report: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET: Get available fields for an entity type.
     */
    public function fields(Request $request, string $entity_type): JsonResponse
    {
        $validator = Validator::make(['entity_type' => $entity_type], [
            'entity_type' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Invalid entity type.',
                422
            );
        }

        try {
            $fields = $this->reportingService->getAvailableFields($entity_type);
            return $this->jsonSuccessWithData($fields);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError('NOT_FOUND', 'Entity type not found.', 404);
        }
    }

    /**
     * GET: Get available filters for an entity type.
     */
    public function filters(Request $request, string $entity_type): JsonResponse
    {
        $validator = Validator::make(['entity_type' => $entity_type], [
            'entity_type' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Invalid entity type.',
                422
            );
        }

        try {
            $filters = $this->reportingService->getAvailableFilters($entity_type);
            return $this->jsonSuccessWithData($filters);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError('NOT_FOUND', 'Entity type not found.', 404);
        }
    }

    /**
     * GET: Main reporting endpoint - query data with filters.
     */
    public function index(Request $request, string $entity_type): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $validator = Validator::make(array_merge($request->all(), ['entity_type' => $entity_type]), [
            'entity_type' => ['required', 'string'],
            'format' => ['nullable', 'string', 'in:json,csv'],
            'sort_by' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
            'fields' => ['nullable', 'string'],
            'include' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        try {
            $format = $request->input('format', 'json');
            
            // Extract filters from request (all params except format, sort_by, sort_order, fields, include)
            $excludedParams = ['format', 'sort_by', 'sort_order', 'fields', 'include'];
            $filters = array_diff_key($request->all(), array_flip($excludedParams));
            
            // Prepare options
            $options = [
                'sort_by' => $request->input('sort_by'),
                'sort_order' => $request->input('sort_order', 'asc'),
            ];

            // Parse include relationships
            if ($request->has('include')) {
                $include = $request->input('include');
                $options['include'] = is_string($include) ? explode(',', $include) : $include;
            }

            // CSV Export
            if ($format === 'csv') {
                // Parse fields
                $fields = [];
                if ($request->has('fields')) {
                    $fieldsInput = $request->input('fields');
                    $fields = is_string($fieldsInput) ? explode(',', $fieldsInput) : $fieldsInput;
                }

                return $this->reportingService->exportToCsv($entity_type, $filters, $fields, $options);
            }

            // JSON Response - return all results (no pagination)
            $results = $this->reportingService->query($entity_type, $filters, $options);

            return $this->jsonSuccessWithData($results);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError('VALIDATION_ERROR', $e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->jsonError('SERVER_ERROR', 'Failed to generate report: ' . $e->getMessage(), 500);
        }
    }
}
