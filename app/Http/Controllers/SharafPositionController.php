<?php

namespace App\Http\Controllers;

use App\Models\SharafPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\QueryException;

class SharafPositionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sharaf_definition_id' => ['required', 'integer', 'exists:sharaf_definitions,id'],
            'name' => ['required', 'string', 'max:255'],
            'display_name' => ['required', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'order' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        // Check if name is unique within sharaf_definition_id
        $exists = SharafPosition::where('sharaf_definition_id', $request->input('sharaf_definition_id'))
            ->where('name', $request->input('name'))
            ->exists();

        if ($exists) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                'A sharaf position with this name already exists for the given sharaf definition.',
                422
            );
        }

        try {
            $sharafPosition = SharafPosition::create([
                'sharaf_definition_id' => $request->input('sharaf_definition_id'),
                'name' => $request->input('name'),
                'display_name' => $request->input('display_name'),
                'capacity' => $request->input('capacity'),
                'order' => $request->input('order'),
            ]);

            return $this->jsonSuccessWithData($sharafPosition, 201);
        } catch (QueryException $e) {
            // Handle database constraint violations
            if ($e->getCode() === '23000') {
                return $this->jsonError(
                    'VALIDATION_ERROR',
                    'A sharaf position with this name already exists for the given sharaf definition.',
                    422
                );
            }
            throw $e;
        }
    }

    /**
     * Update an existing sharaf position.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $sharafPosition = SharafPosition::find($id);

        if (! $sharafPosition) {
            return $this->jsonError('NOT_FOUND', 'Sharaf position not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'sharaf_definition_id' => ['sometimes', 'integer', 'exists:sharaf_definitions,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'display_name' => ['sometimes', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'order' => ['sometimes', 'integer'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $sharafDefinitionId = $request->input('sharaf_definition_id', $sharafPosition->sharaf_definition_id);
        $name = $request->input('name', $sharafPosition->name);

        if ($request->has('name') || $request->has('sharaf_definition_id')) {
            $exists = SharafPosition::where('sharaf_definition_id', $sharafDefinitionId)
                ->where('name', $name)
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return $this->jsonError(
                    'VALIDATION_ERROR',
                    'A sharaf position with this name already exists for the given sharaf definition.',
                    422
                );
            }
        }

        try {
            $sharafPosition->update($request->only(['sharaf_definition_id', 'name', 'display_name', 'capacity', 'order']));

            return $this->jsonSuccessWithData($sharafPosition->fresh('sharafDefinition'));
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return $this->jsonError(
                    'VALIDATION_ERROR',
                    'A sharaf position with this name already exists for the given sharaf definition.',
                    422
                );
            }
            throw $e;
        }
    }
}
