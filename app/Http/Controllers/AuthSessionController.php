<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthSessionController extends Controller
{
    /**
     * Log in by ITS number. Validates the user exists and sets the user cookie.
     *
     * POST /api/auth/login
     * Request body: { "its_no": "12345" }
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'its_no' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first('its_no') ?? 'its_no is required.',
                422
            );
        }

        $itsNo = trim($validator->validated()['its_no']);

        if (! $this->isValidItsNo($itsNo)) {
            return $this->jsonError(
                'INVALID_ITS_NO',
                'ITS number must be numeric.',
                422
            );
        }

        $user = User::where('its_no', $itsNo)->first();

        if (! $user) {
            return $this->jsonError(
                'INVALID_CREDENTIALS',
                'No user found with this ITS number.',
                401
            );
        }

        $cookie = cookie(
            name: 'user',
            value: $itsNo,
            minutes: 60 * 24 * 7, // 7 days
            path: '/',
            secure: null,
            httpOnly: false,
            raw: false,
            sameSite: 'lax'
        );

        return response()
            ->json(['success' => true, 'its_no' => $itsNo], 200)
            ->cookie($cookie);
    }

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
