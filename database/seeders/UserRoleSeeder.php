<?php

namespace Database\Seeders;

use App\Models\UserRole;
use Illuminate\Database\Seeder;

class UserRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            'Admin',
            'Master',
            'Finance',
            'Anjuman',
            'Help Desk',
            'Follow Up',
        ];

        foreach ($roles as $name) {
            UserRole::firstOrCreate(
                ['name' => $name],
                ['name' => $name]
            );
        }
    }
}
