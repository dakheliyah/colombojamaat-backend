<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Map users.user_type to user_roles and insert into user_role pivot.
     * BS → Master, Admin → Admin, Help Desk → Help Desk, Anjuman → Anjuman, Finance → Finance, Follow Up → Follow Up.
     */
    public function up(): void
    {
        $roleNames = ['Admin', 'Master', 'Finance', 'Anjuman', 'Help Desk', 'Follow Up'];
        foreach ($roleNames as $name) {
            if (DB::table('user_roles')->where('name', $name)->doesntExist()) {
                DB::table('user_roles')->insert([
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $roleNamesById = DB::table('user_roles')->pluck('id', 'name')->all();

        $userTypeToRoleName = [
            'BS' => 'Master',
            'Admin' => 'Admin',
            'Help Desk' => 'Help Desk',
            'Anjuman' => 'Anjuman',
            'Finance' => 'Finance',
            'Follow Up' => 'Follow Up',
        ];

        $users = DB::table('users')->whereNotNull('user_type')->get(['id', 'user_type']);

        foreach ($users as $user) {
            $roleName = $userTypeToRoleName[$user->user_type] ?? null;
            if ($roleName === null || ! isset($roleNamesById[$roleName])) {
                continue;
            }
            $roleId = $roleNamesById[$roleName];
            DB::table('user_role')->insertOrIgnore([
                'user_id' => $user->id,
                'role_id' => $roleId,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('user_role')->truncate();
    }
};
