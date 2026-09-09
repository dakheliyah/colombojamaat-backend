<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Extra Anjuman permission: view all sharafs for a miqaat.
     */
    public function up(): void
    {
        if (DB::table('user_roles')->where('name', 'Anjuman Supervisor')->doesntExist()) {
            DB::table('user_roles')->insert([
                'name' => 'Anjuman Supervisor',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('user_roles')->where('name', 'Anjuman Supervisor')->delete();
    }
};
