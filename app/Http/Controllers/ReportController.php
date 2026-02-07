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
