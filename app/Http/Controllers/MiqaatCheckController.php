<?php

namespace App\Http\Controllers;

use App\Models\Miqaat;
use App\Models\MiqaatCheck;
use App\Models\MiqaatCheckDepartment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MiqaatCheckController extends Controller
{
    /**
     * List all miqaat_checks for a person (its_id) within the scope of a miqaat.
     * Used by the Anjuman view to show which definitions are cleared/uncleared for the entered ITS number.
     */
    public function index(Request $request, int $miqaat_id): JsonResponse
    {
        if (($err = $this->ensureActiveMiqaat($miqaat_id)) !== null) {
            return $err;
        }
        if (!Miqaat::where('id', $miqaat_id)->exists()) {
            return $this->jsonError('NOT_FOUND', 'Miqaat not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'its_id' => ['required', 'string'],
        ], [
            'its_id.required' => 'The its_id parameter is required.',
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $itsId = $request->input('its_id');

        $mcdIds = MiqaatCheckDepartment::where('miqaat_id', $miqaat_id)->pluck('mcd_id');

        $checks = MiqaatCheck::where('its_id', $itsId)
            ->whereIn('mcd_id', $mcdIds)
            ->orderBy('mcd_id')
            ->get();

        return $this->jsonSuccessWithData($checks->values()->all());
    }

    /**
     * Upsert a single miqaat_check (create or update by its_id + mcd_id).
     * Used by the Anjuman view when the user toggles clear/unclear for a definition.
     */
    public function upsert(Request $request, int $miqaat_id): JsonResponse
    {
        if (($err = $this->ensureActiveMiqaat($miqaat_id)) !== null) {
            return $err;
        }
        if (!Miqaat::where('id', $miqaat_id)->exists()) {
            return $this->jsonError('NOT_FOUND', 'Miqaat or check definition not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'its_id' => ['required', 'string'],
            'mcd_id' => ['required', 'integer'],
            'is_cleared' => ['required', 'boolean'],
            'cleared_by_its' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $definition = MiqaatCheckDepartment::where('mcd_id', $request->input('mcd_id'))
            ->where('miqaat_id', $miqaat_id)
            ->first();

        if (!$definition) {
            return $this->jsonError('NOT_FOUND', 'Check definition not found for this miqaat.', 404);
        }

        $itsId = $request->input('its_id');
        $mcdId = (int) $request->input('mcd_id');
        $isCleared = (bool) $request->input('is_cleared');
        $clearedByIts = $request->input('cleared_by_its');
        $notes = $request->input('notes');

        $check = MiqaatCheck::where('its_id', $itsId)->where('mcd_id', $mcdId)->first();

        $clearedAt = $isCleared ? now() : null;
        if (!$isCleared) {
            $clearedByIts = null;
        }

        $payload = [
            'is_cleared' => $isCleared,
            'cleared_by_its' => $clearedByIts,
            'cleared_at' => $clearedAt,
            'notes' => $notes,
        ];

        if ($check) {
            $check->update($payload);
            return $this->jsonSuccessWithData($check->fresh());
        }

        $check = MiqaatCheck::create([
            'its_id' => $itsId,
            'mcd_id' => $mcdId,
            'is_cleared' => $isCleared,
            'cleared_by_its' => $clearedByIts,
            'cleared_at' => $clearedAt,
            'notes' => $notes,
        ]);

        return $this->jsonSuccessWithData($check, 201);
    }
}
