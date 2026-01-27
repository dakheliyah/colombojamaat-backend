<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
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
}
