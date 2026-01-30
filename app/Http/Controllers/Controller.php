<?php

namespace App\Http\Controllers;

use App\Models\Miqaat;
use Illuminate\Http\JsonResponse;

abstract class Controller
{
    /**
     * Ensure the given miqaat id is the current active miqaat.
     * Returns a 403 JsonResponse if not; returns null if ok (caller should return null to continue).
     */
    protected function ensureActiveMiqaat(int $miqaatId): ?JsonResponse
    {
        $activeId = Miqaat::getActiveId();
        if ($activeId === null || $activeId !== $miqaatId) {
            return $this->jsonError('MIQAAT_NOT_ACTIVE', 'Miqaat is not the current active miqaat.', 403);
        }

        return null;
    }
    protected function jsonSuccess(int $status = 200): JsonResponse
    {
        return response()->json(['success' => true], $status);
    }

    protected function jsonSuccessWithData(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }

    protected function jsonSuccessWithWarnings(array $warnings, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'warnings' => $warnings], $status);
    }

    protected function jsonError(string $error, string $message, int $status = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => $error,
            'message' => $message
        ], $status);
    }
}
