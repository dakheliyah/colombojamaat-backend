<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds 'Follow Up' to the user_type enum on users table.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN user_type ENUM('BS', 'Admin', 'Help Desk', 'Anjuman', 'Finance', 'Follow Up') DEFAULT 'BS'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN user_type ENUM('BS', 'Admin', 'Help Desk', 'Anjuman', 'Finance') DEFAULT 'BS'");
    }
};
