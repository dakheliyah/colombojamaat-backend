<?php

namespace App\Http\Controllers;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * List all users.
     */
    public function index(): JsonResponse
    {
        $users = User::orderBy('id')->get();

        return $this->jsonSuccessWithData($users);
    }

    /**
     * Get a single user by ID.
     */
    public function show(int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return $this->jsonError('NOT_FOUND', 'User not found.', 404);
        }

        return $this->jsonSuccessWithData($user);
    }

    /**
     * Get user by ITS number.
     */
    public function showByItsNo(string $its_no): JsonResponse
    {
        $user = User::where('its_no', $its_no)->first();

        if (!$user) {
            return $this->jsonError('NOT_FOUND', 'User not found.', 404);
        }

        return $this->jsonSuccessWithData($user);
    }

    /**
     * Create a new user.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'its_no' => array_values(array_filter([
                'nullable',
                'string',
                'max:255',
                $request->filled('its_no') ? Rule::unique('users', 'its_no') : null,
            ])),
            'user_type' => ['nullable', Rule::enum(UserType::class)],
        ]);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => $request->input('password'),
            'its_no' => $request->filled('its_no') ? $request->input('its_no') : null,
            'user_type' => $request->input('user_type'),
        ]);

        return $this->jsonSuccessWithData($user, 201);
    }

    /**
     * Update an existing user.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return $this->jsonError('NOT_FOUND', 'User not found.', 404);
        }

        $rules = [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'password' => ['sometimes', 'string', 'min:8'],
            'its_no' => array_values(array_filter([
                'nullable',
                'string',
                'max:255',
                $request->filled('its_no') ? Rule::unique('users', 'its_no')->ignore($id) : null,
            ])),
            'user_type' => ['nullable', Rule::enum(UserType::class)],
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?? 'Validation failed.',
                422
            );
        }

        $data = $validator->validated();

        if (array_key_exists('its_no', $data) && $data['its_no'] === '') {
            $data['its_no'] = null;
        }

        if (!array_key_exists('password', $data)) {
            unset($data['password']);
        }

        $user->update($data);

        return $this->jsonSuccessWithData($user->fresh());
    }

    /**
     * Delete a user.
     */
    public function destroy(int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return $this->jsonError('NOT_FOUND', 'User not found.', 404);
        }

        $user->delete();

        return $this->jsonSuccess(200);
    }
}
