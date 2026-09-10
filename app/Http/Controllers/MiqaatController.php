<?php

namespace App\Http\Controllers;

use App\Models\Miqaat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MiqaatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Miqaat::query()->orderByDesc('active_status')->orderBy('start_date');
        if (! $request->boolean('include_archived')) {
            $query->notArchived();
        }

        return $this->jsonSuccessWithData($query->get());
    }

    /**
     * Get the single active miqaat (404 if none).
     */
    public function active(): JsonResponse
    {
        $miqaat = Miqaat::getActive();
        if ($miqaat === null) {
            return $this->jsonError('NOT_FOUND', 'No active miqaat set.', 404);
        }

        return $this->jsonSuccessWithData($miqaat);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $activeStatus = Miqaat::getActive() === null;
        $miqaat = Miqaat::create([
            'name' => $request->input('name'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'description' => $request->input('description'),
            'active_status' => $activeStatus,
            'archived' => false,
        ]);

        return $this->jsonSuccessWithData($miqaat, 201);
    }

    /**
     * Update a miqaat. When active_status is set to true, all other miqaats are set inactive.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $miqaat = Miqaat::find($id);
        if ($miqaat === null) {
            return $this->jsonError('NOT_FOUND', 'Miqaat not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
            'description' => ['nullable', 'string'],
            'active_status' => ['sometimes', 'boolean'],
            'archived' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $setActive = $request->has('active_status') && $request->boolean('active_status');
        $setArchived = $request->has('archived') && $request->boolean('archived');

        if ($setActive && $setArchived) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                'An archived miqaat cannot be the active miqaat.',
                422
            );
        }

        DB::transaction(function () use ($miqaat, $request, $setActive, $setArchived) {
            if ($setActive) {
                Miqaat::where('id', '!=', $miqaat->id)
                    ->where('active_status', true)
                    ->get()
                    ->each(fn (Miqaat $other) => $other->update(['active_status' => false]));
            }
            $updates = $request->only(['name', 'start_date', 'end_date', 'description', 'active_status', 'archived']);
            if (array_key_exists('active_status', $updates)) {
                $updates['active_status'] = (bool) $updates['active_status'];
            }
            if (array_key_exists('archived', $updates)) {
                $updates['archived'] = (bool) $updates['archived'];
            }
            if ($setActive) {
                $updates['archived'] = false;
            }
            if ($setArchived) {
                $updates['active_status'] = false;
            }
            $miqaat->update($updates);
        });

        return $this->jsonSuccessWithData($miqaat->fresh());
    }
}
