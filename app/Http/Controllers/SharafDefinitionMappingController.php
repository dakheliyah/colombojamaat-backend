<?php

namespace App\Http\Controllers;

use App\Services\SharafDefinitionMappingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SharafDefinitionMappingController extends Controller
{
    protected $mappingService;

    public function __construct(SharafDefinitionMappingService $mappingService)
    {
        $this->mappingService = $mappingService;
    }

    /**
     * GET /api/sharaf-definition-mappings
     * List all mappings, optionally filtered by definition ID
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'definition_id' => ['nullable', 'integer', 'exists:sharaf_definitions,id'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $definitionId = $request->query('definition_id');
        $mappings = $this->mappingService->getMappings($definitionId ? (int) $definitionId : null);

        return $this->jsonSuccessWithData($mappings);
    }

    /**
     * POST /api/sharaf-definition-mappings
     * Create a new mapping
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'source_sharaf_definition_id' => ['required', 'integer', 'exists:sharaf_definitions,id'],
            'target_sharaf_definition_id' => ['required', 'integer', 'exists:sharaf_definitions,id'],
            'created_by_its' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        try {
            $mapping = $this->mappingService->createMapping(
                $request->input('source_sharaf_definition_id'),
                $request->input('target_sharaf_definition_id'),
                $request->input('created_by_its'),
                $request->input('notes')
            );

            $mapping->load(['sourceDefinition.event', 'targetDefinition.event']);

            return $this->jsonSuccessWithData($mapping, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError('VALIDATION_ERROR', $e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->jsonError('SERVER_ERROR', 'Failed to create mapping.', 500);
        }
    }

    /**
     * GET /api/sharaf-definition-mappings/{id}
     * Get specific mapping with all related mappings
     */
    public function show(string $id): JsonResponse
    {
        $validator = Validator::make(['id' => $id], [
            'id' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Invalid mapping ID.',
                422
            );
        }

        $mapping = \App\Models\SharafDefinitionMapping::with([
            'sourceDefinition.event',
            'targetDefinition.event',
            'positionMappings.sourcePosition',
            'positionMappings.targetPosition',
            'paymentDefinitionMappings.sourcePaymentDefinition',
            'paymentDefinitionMappings.targetPaymentDefinition',
        ])->find($id);

        if (!$mapping) {
            return $this->jsonError('NOT_FOUND', 'Mapping not found.', 404);
        }

        // Add validation status
        $validation = $this->mappingService->validateMappingComplete($mapping->id);
        $mappingArray = $mapping->toArray();
        $mappingArray['validation'] = $validation;

        return $this->jsonSuccessWithData($mappingArray);
    }

    /**
     * PUT/PATCH /api/sharaf-definition-mappings/{id}
     * Update mapping (is_active, notes)
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make(array_merge($request->all(), ['id' => $id]), [
            'id' => ['required', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $mapping = \App\Models\SharafDefinitionMapping::find($id);

        if (!$mapping) {
            return $this->jsonError('NOT_FOUND', 'Mapping not found.', 404);
        }

        if ($request->has('is_active')) {
            $mapping->is_active = $request->input('is_active');
        }

        if ($request->has('notes')) {
            $mapping->notes = $request->input('notes');
        }

        $mapping->save();
        $mapping->load(['sourceDefinition.event', 'targetDefinition.event']);

        return $this->jsonSuccessWithData($mapping);
    }

    /**
     * DELETE /api/sharaf-definition-mappings/{id}
     * Delete a mapping (cascades to position and payment mappings)
     */
    public function destroy(string $id): JsonResponse
    {
        $validator = Validator::make(['id' => $id], [
            'id' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Invalid mapping ID.',
                422
            );
        }

        try {
            $this->mappingService->deleteMapping((int) $id);
            return $this->jsonSuccess(['message' => 'Mapping deleted successfully.']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->jsonError('NOT_FOUND', 'Mapping not found.', 404);
        } catch (\Exception $e) {
            return $this->jsonError('SERVER_ERROR', 'Failed to delete mapping.', 500);
        }
    }

    /**
     * POST /api/sharaf-definition-mappings/{id}/position-mappings
     * Add a position mapping
     */
    public function addPositionMapping(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make(array_merge($request->all(), ['id' => $id]), [
            'id' => ['required', 'integer'],
            'source_sharaf_position_id' => ['required', 'integer', 'exists:sharaf_positions,id'],
            'target_sharaf_position_id' => ['required', 'integer', 'exists:sharaf_positions,id'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        try {
            $positionMapping = $this->mappingService->addPositionMapping(
                (int) $id,
                $request->input('source_sharaf_position_id'),
                $request->input('target_sharaf_position_id')
            );

            $positionMapping->load(['sourcePosition', 'targetPosition']);

            return $this->jsonSuccessWithData($positionMapping, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError('VALIDATION_ERROR', $e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->jsonError('SERVER_ERROR', 'Failed to create position mapping.', 500);
        }
    }

    /**
     * DELETE /api/sharaf-definition-mappings/{id}/position-mappings/{positionMappingId}
     * Remove a position mapping
     */
    public function removePositionMapping(string $id, string $positionMappingId): JsonResponse
    {
        $validator = Validator::make(['id' => $id, 'position_mapping_id' => $positionMappingId], [
            'id' => ['required', 'integer'],
            'position_mapping_id' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Invalid ID.',
                422
            );
        }

        try {
            $this->mappingService->removePositionMapping((int) $positionMappingId);
            return $this->jsonSuccess(['message' => 'Position mapping deleted successfully.']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->jsonError('NOT_FOUND', 'Position mapping not found.', 404);
        } catch (\Exception $e) {
            return $this->jsonError('SERVER_ERROR', 'Failed to delete position mapping.', 500);
        }
    }

    /**
     * POST /api/sharaf-definition-mappings/{id}/payment-definition-mappings
     * Add a payment definition mapping
     */
    public function addPaymentDefinitionMapping(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make(array_merge($request->all(), ['id' => $id]), [
            'id' => ['required', 'integer'],
            'source_payment_definition_id' => ['required', 'integer', 'exists:payment_definitions,id'],
            'target_payment_definition_id' => ['required', 'integer', 'exists:payment_definitions,id'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        try {
            $paymentMapping = $this->mappingService->addPaymentDefinitionMapping(
                (int) $id,
                $request->input('source_payment_definition_id'),
                $request->input('target_payment_definition_id')
            );

            $paymentMapping->load(['sourcePaymentDefinition', 'targetPaymentDefinition']);

            return $this->jsonSuccessWithData($paymentMapping, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError('VALIDATION_ERROR', $e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->jsonError('SERVER_ERROR', 'Failed to create payment definition mapping.', 500);
        }
    }

    /**
     * DELETE /api/sharaf-definition-mappings/{id}/payment-definition-mappings/{paymentMappingId}
     * Remove a payment definition mapping
     */
    public function removePaymentDefinitionMapping(string $id, string $paymentMappingId): JsonResponse
    {
        $validator = Validator::make(['id' => $id, 'payment_mapping_id' => $paymentMappingId], [
            'id' => ['required', 'integer'],
            'payment_mapping_id' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Invalid ID.',
                422
            );
        }

        try {
            $this->mappingService->removePaymentDefinitionMapping((int) $paymentMappingId);
            return $this->jsonSuccess(['message' => 'Payment definition mapping deleted successfully.']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->jsonError('NOT_FOUND', 'Payment definition mapping not found.', 404);
        } catch (\Exception $e) {
            return $this->jsonError('SERVER_ERROR', 'Failed to delete payment definition mapping.', 500);
        }
    }

    /**
     * GET /api/sharaf-definition-mappings/{id}/validate
     * Validate mapping completeness
     */
    public function validateMapping(Request $request, string $id): JsonResponse
    {
        // Handle sharaf_ids as query parameter (can be array or comma-separated string)
        $sharafIdsParam = $request->query('sharaf_ids');
        $sharafIdsArray = null;
        
        if ($sharafIdsParam !== null) {
            if (is_string($sharafIdsParam)) {
                // Handle comma-separated string
                $sharafIdsArray = array_filter(array_map('intval', explode(',', $sharafIdsParam)));
            } elseif (is_array($sharafIdsParam)) {
                $sharafIdsArray = array_map('intval', $sharafIdsParam);
            }
        }

        $reverseDirection = filter_var($request->query('reverse_direction', false), FILTER_VALIDATE_BOOLEAN);

        $validator = Validator::make(array_merge(['id' => $id], $sharafIdsArray ? ['sharaf_ids' => $sharafIdsArray] : []), [
            'id' => ['required', 'integer'],
            'sharaf_ids' => ['nullable', 'array'],
            'sharaf_ids.*' => ['integer', 'exists:sharafs,id'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Invalid mapping ID.',
                422
            );
        }

        try {
            $validation = $this->mappingService->validateMappingComplete((int) $id, !empty($sharafIdsArray) ? $sharafIdsArray : null, $reverseDirection);
            return $this->jsonSuccessWithData($validation);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->jsonError('NOT_FOUND', 'Mapping not found.', 404);
        } catch (\Exception $e) {
            return $this->jsonError('SERVER_ERROR', 'Failed to validate mapping.', 500);
        }
    }

    /**
     * POST /api/sharaf-definition-mappings/{id}/shift
     * Execute the shift operation
     */
    public function shift(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make(array_merge($request->all(), ['id' => $id]), [
            'id' => ['required', 'integer'],
            'shifted_by_its' => ['nullable', 'string'],
            'sharaf_ids' => ['nullable', 'array'],
            'sharaf_ids.*' => ['integer', 'exists:sharafs,id'],
            'reverse_direction' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        try {
            $sharafIds = $request->input('sharaf_ids');
            $reverseDirection = filter_var($request->input('reverse_direction', false), FILTER_VALIDATE_BOOLEAN);
            
            $result = $this->mappingService->shiftSharafs(
                (int) $id,
                $request->input('shifted_by_its'),
                $sharafIds,
                $reverseDirection
            );

            return $this->jsonSuccessWithData($result);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError('VALIDATION_ERROR', $e->getMessage(), 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->jsonError('NOT_FOUND', 'Mapping not found.', 404);
        } catch (\Exception $e) {
            return $this->jsonError('SERVER_ERROR', 'Failed to shift sharafs: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/sharaf-definition-mappings/{id}/audit-logs
     * Get audit logs for a mapping
     */
    public function auditLogs(string $id): JsonResponse
    {
        $validator = Validator::make(['id' => $id], [
            'id' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Invalid mapping ID.',
                422
            );
        }

        $mapping = \App\Models\SharafDefinitionMapping::find($id);

        if (!$mapping) {
            return $this->jsonError('NOT_FOUND', 'Mapping not found.', 404);
        }

        $auditLogs = \App\Models\SharafShiftAuditLog::where('sharaf_definition_mapping_id', $id)
            ->orderBy('shifted_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->jsonSuccessWithData($auditLogs);
    }
}
