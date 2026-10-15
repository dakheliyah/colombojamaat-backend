<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * AVRP: sharaf head names and tarteeb by definition for an event.
     */
    public function up(): void
    {
        if (DB::table('user_roles')->where('name', 'AVRP')->doesntExist()) {
            DB::table('user_roles')->insert([
                'name' => 'AVRP',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('user_roles')->where('name', 'AVRP')->delete();
    }
};
