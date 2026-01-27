<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
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
        return response()->json(['error' => $error, 'message' => $message], $status);
    }
}
