<?php

namespace App\Http\Controllers;

use App\Models\PaymentDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentDefinitionController extends Controller
{
    /**
     * Get all payment definitions with optional filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sharaf_definition_id' => ['nullable', 'integer', 'exists:sharaf_definitions,id'],
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

        $query = PaymentDefinition::with('sharafDefinition');

        // Apply filters
        if ($request->has('sharaf_definition_id')) {
            $query->where('sharaf_definition_id', $request->input('sharaf_definition_id'));
        }

        // Pagination
        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);

        $results = $query->orderBy('sharaf_definition_id')->orderBy('name')->paginate($perPage, ['*'], 'page', $page);

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

    /**
     * Store a newly created payment definition.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sharaf_definition_id' => ['required', 'integer', 'exists:sharaf_definitions,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'user_type' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        // Check if payment definition with same name already exists for this sharaf definition
        $exists = PaymentDefinition::where('sharaf_definition_id', $request->input('sharaf_definition_id'))
            ->where('name', $request->input('name'))
            ->exists();

        if ($exists) {
            return $this->jsonError(
                'DUPLICATE_ERROR',
                'A payment definition with this name already exists for this sharaf definition.',
                422
            );
        }

        $paymentDefinition = PaymentDefinition::create([
            'sharaf_definition_id' => $request->input('sharaf_definition_id'),
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'user_type' => $request->input('user_type', 'Finance'),
        ]);

        return $this->jsonSuccessWithData($paymentDefinition, 201);
    }

    /**
     * Update an existing payment definition.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $paymentDefinition = PaymentDefinition::find($id);

        if (! $paymentDefinition) {
            return $this->jsonError('NOT_FOUND', 'Payment definition not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'sharaf_definition_id' => ['sometimes', 'integer', 'exists:sharaf_definitions,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'user_type' => ['sometimes', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $sharafDefinitionId = $request->input('sharaf_definition_id', $paymentDefinition->sharaf_definition_id);
        $name = $request->input('name', $paymentDefinition->name);

        if ($request->has('name') || $request->has('sharaf_definition_id')) {
            $exists = PaymentDefinition::where('sharaf_definition_id', $sharafDefinitionId)
                ->where('name', $name)
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return $this->jsonError(
                    'DUPLICATE_ERROR',
                    'A payment definition with this name already exists for this sharaf definition.',
                    422
                );
            }
        }

        $paymentDefinition->update($request->only(['sharaf_definition_id', 'name', 'description', 'user_type']));

        return $this->jsonSuccessWithData($paymentDefinition->fresh('sharafDefinition'));
    }
}
