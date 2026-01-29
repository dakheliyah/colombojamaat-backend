<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthSessionController extends Controller
{
    /**
     * Return the current user's ITS number from the user cookie, or an error.
     *
     * GET /api/auth/session — expects credentials (cookies). Cookie name: user.
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $raw = $request->cookie('user');

            if ($raw === null || $raw === '') {
                return $this->unauthorized();
            }

            $value = trim($raw);

            if ($value === '' || ! $this->isValidItsNo($value)) {
                return $this->unauthorized();
            }

            return response()->json(['its_no' => $value], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'server_error',
                'message' => 'An unexpected error occurred.',
            ], 500);
        }
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json([
            'error' => 'unauthorized',
            'message' => 'No valid session.',
        ], 401);
    }

    private function isValidItsNo(string $value): bool
    {
        return $value !== '' && preg_match('/\A[0-9]+\z/', $value) === 1;
    }
}
