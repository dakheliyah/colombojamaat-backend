<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * FMB: food-preparation headcount for a miqaat.
     */
    public function up(): void
    {
        if (DB::table('user_roles')->where('name', 'FMB')->doesntExist()) {
            DB::table('user_roles')->insert([
                'name' => 'FMB',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('user_roles')->where('name', 'FMB')->delete();
    }
};
