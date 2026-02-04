<?php

namespace App\Http\Controllers;

use App\Models\SharafType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SharafTypeController extends Controller
{
    /**
     * List all sharaf types.
     */
    public function index(): JsonResponse
    {
        $types = SharafType::query()->orderBy('name')->get();

        return $this->jsonSuccessWithData($types);
    }

    /**
     * Get a single sharaf type by id.
     */
    public function show(int $id): JsonResponse
    {
        $sharafType = SharafType::find($id);

        if (! $sharafType) {
            return $this->jsonError('NOT_FOUND', 'Sharaf type not found.', 404);
        }

        return $this->jsonSuccessWithData($sharafType);
    }

    /**
     * Create a new sharaf type.
     */
    public function store(Request $request): JsonResponse
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

        $sharafType = SharafType::create([
            'name' => $request->input('name'),
        ]);

        return $this->jsonSuccessWithData($sharafType, 201);
    }

    /**
     * Update an existing sharaf type.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $sharafType = SharafType::find($id);

        if (! $sharafType) {
            return $this->jsonError('NOT_FOUND', 'Sharaf type not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $sharafType->update($request->only(['name']));

        return $this->jsonSuccessWithData($sharafType->fresh());
    }

    /**
     * Delete a sharaf type.
     */
    public function destroy(int $id): JsonResponse
    {
        $sharafType = SharafType::find($id);

        if (! $sharafType) {
            return $this->jsonError('NOT_FOUND', 'Sharaf type not found.', 404);
        }

        if ($sharafType->sharafDefinitions()->exists()) {
            return $this->jsonError(
                'CONFLICT',
                'Cannot delete sharaf type that is in use by sharaf definitions.',
                409
            );
        }

        $sharafType->delete();

        return $this->jsonSuccess(200);
    }
}
