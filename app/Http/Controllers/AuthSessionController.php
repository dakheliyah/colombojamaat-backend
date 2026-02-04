<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthSessionController extends Controller
{
    /**
     * Log in by ITS number and password. Validates credentials and sets the user cookie.
     *
     * POST /api/auth/login
     * Request body: { "its_no": "12345", "password": "password123" }
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'its_no' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            $firstError = $validator->errors()->first();
            return $this->jsonError(
                'VALIDATION_ERROR',
                $firstError ?? 'its_no and password are required.',
                422
            );
        }

        $validated = $validator->validated();
        $itsNo = trim($validated['its_no']);
        $password = $validated['password'];

        if (! $this->isValidItsNo($itsNo)) {
            return $this->jsonError(
                'INVALID_ITS_NO',
                'ITS number must be numeric.',
                422
            );
        }

        // Query password directly from database to bypass the 'hashed' cast
        $userData = DB::table('users')
            ->where('its_no', $itsNo)
            ->select('id', 'password')
            ->first();

        if (! $userData || empty($userData->password)) {
            return $this->jsonError(
                'INVALID_CREDENTIALS',
                'Invalid credentials.',
                401
            );
        }

        if (! Hash::check($password, $userData->password)) {
            return $this->jsonError(
                'INVALID_CREDENTIALS',
                'Invalid credentials.',
                401
            );
        }

        // Load user with relationships for response
        $user = User::with(['roles', 'sharafTypes'])->find($userData->id);

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
            ->json(['success' => true, 'data' => $user], 200)
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
