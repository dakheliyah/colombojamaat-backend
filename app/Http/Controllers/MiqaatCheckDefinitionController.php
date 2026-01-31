<?php

namespace App\Http\Controllers;

use App\Enums\UserType;
use App\Models\Miqaat;
use App\Models\MiqaatCheckDepartment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MiqaatCheckDefinitionController extends Controller
{
    /**
     * List all miqaat check definitions with optional pagination and miqaat filter.
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'miqaat_id' => ['nullable', 'integer', 'exists:miqaats,id'],
            'user_type' => ['nullable', Rule::enum(UserType::class)],
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

        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);

        $query = MiqaatCheckDepartment::query()->with('miqaat')->orderBy('miqaat_id')->orderBy('name');

        if ($request->filled('miqaat_id')) {
            if (($err = $this->ensureActiveMiqaat((int) $request->input('miqaat_id'))) !== null) {
                return $err;
            }
            $query->where('miqaat_id', $request->input('miqaat_id'));
        } else {
            $activeId = Miqaat::getActiveId();
            if ($activeId !== null) {
                $query->where('miqaat_id', $activeId);
            }
        }

        if ($request->filled('user_type')) {
            $query->where('user_type', $request->input('user_type'));
        }

        $results = $query->paginate($perPage, ['*'], 'page', $page);

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
     * Get a single miqaat check definition by mcd_id.
     */
    public function show(int $mcd_id): JsonResponse
    {
        $definition = MiqaatCheckDepartment::with('miqaat')->find($mcd_id);

        if (!$definition) {
            return $this->jsonError('NOT_FOUND', 'Miqaat check definition not found.', 404);
        }

        return $this->jsonSuccessWithData($definition);
    }

    /**
     * Create a new miqaat check definition.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'miqaat_id' => ['required', 'integer', 'exists:miqaats,id'],
            'name' => ['required', 'string', 'max:255'],
            'user_type' => ['nullable', Rule::enum(UserType::class)],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        // Unique per (name, miqaat_id)
        $exists = MiqaatCheckDepartment::where('miqaat_id', $request->input('miqaat_id'))
            ->where('name', $request->input('name'))
            ->exists();

        if ($exists) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                'A check definition with this name already exists for this miqaat.',
                422
            );
        }

        $definition = MiqaatCheckDepartment::create([
            'miqaat_id' => $request->input('miqaat_id'),
            'name' => $request->input('name'),
            'user_type' => $request->input('user_type'),
        ]);

        return $this->jsonSuccessWithData($definition->load('miqaat'), 201);
    }

    /**
     * Update an existing miqaat check definition.
     */
    public function update(Request $request, int $mcd_id): JsonResponse
    {
        $definition = MiqaatCheckDepartment::find($mcd_id);

        if (!$definition) {
            return $this->jsonError('NOT_FOUND', 'Miqaat check definition not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'miqaat_id' => ['required', 'integer', 'exists:miqaats,id'],
            'name' => ['required', 'string', 'max:255'],
            'user_type' => ['nullable', Rule::enum(UserType::class)],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        // Unique per (name, miqaat_id), excluding current row
        $exists = MiqaatCheckDepartment::where('miqaat_id', $request->input('miqaat_id'))
            ->where('name', $request->input('name'))
            ->where('mcd_id', '!=', $mcd_id)
            ->exists();

        if ($exists) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                'A check definition with this name already exists for this miqaat.',
                422
            );
        }

        $definition->update([
            'miqaat_id' => $request->input('miqaat_id'),
            'name' => $request->input('name'),
            'user_type' => $request->input('user_type'),
        ]);

        return $this->jsonSuccessWithData($definition->load('miqaat'));
    }

    /**
     * Delete a miqaat check definition.
     */
    public function destroy(int $mcd_id): JsonResponse
    {
        $definition = MiqaatCheckDepartment::find($mcd_id);

        if (!$definition) {
            return $this->jsonError('NOT_FOUND', 'Miqaat check definition not found.', 404);
        }

        $definition->delete();

        return $this->jsonSuccess(200);
    }
}
