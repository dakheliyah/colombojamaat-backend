<?php

namespace App\Http\Controllers;

use App\Models\UserRole;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    /**
     * List all assignable roles for user create/edit forms.
     * Returns id and name for each role (e.g. Admin, Master, Finance, Anjuman, Help Desk, Follow Up).
     */
    public function index(): JsonResponse
    {
        $roles = UserRole::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return $this->jsonSuccessWithData($roles);
    }
}
