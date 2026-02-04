<?php

namespace App\Http\Controllers;

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
        $users = User::with(['roles', 'sharafTypes'])->orderBy('id')->get();

        return $this->jsonSuccessWithData($users);
    }

    /**
     * Get a single user by ID.
     */
    public function show(int $id): JsonResponse
    {
        $user = User::with(['roles', 'sharafTypes'])->find($id);

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
        $user = User::with(['roles', 'sharafTypes'])->where('its_no', $its_no)->first();

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
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:user_roles,id'],
            'sharaf_type_ids' => ['nullable', 'array'],
            'sharaf_type_ids.*' => ['integer', 'exists:sharaf_types,id'],
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
        ]);

        if ($request->filled('role_ids')) {
            $user->roles()->sync($request->input('role_ids'));
        }
        if ($request->filled('sharaf_type_ids')) {
            $user->sharafTypes()->sync($request->input('sharaf_type_ids'));
        }

        return $this->jsonSuccessWithData($user->load(['roles', 'sharafTypes']), 201);
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
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:user_roles,id'],
            'sharaf_type_ids' => ['nullable', 'array'],
            'sharaf_type_ids.*' => ['integer', 'exists:sharaf_types,id'],
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

        if (array_key_exists('role_ids', $data)) {
            $user->roles()->sync($data['role_ids']);
            unset($data['role_ids']);
        }
        if (array_key_exists('sharaf_type_ids', $data)) {
            $user->sharafTypes()->sync($data['sharaf_type_ids']);
            unset($data['sharaf_type_ids']);
        }

        $user->update($data);

        return $this->jsonSuccessWithData($user->fresh(['roles', 'sharafTypes']));
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
