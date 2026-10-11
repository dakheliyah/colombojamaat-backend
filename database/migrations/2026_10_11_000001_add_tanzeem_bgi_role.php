<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tanzeem BGI: security and flow overview of a miqaat.
     */
    public function up(): void
    {
        if (DB::table('user_roles')->where('name', 'Tanzeem BGI')->doesntExist()) {
            DB::table('user_roles')->insert([
                'name' => 'Tanzeem BGI',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('user_roles')->where('name', 'Tanzeem BGI')->delete();
    }
};
