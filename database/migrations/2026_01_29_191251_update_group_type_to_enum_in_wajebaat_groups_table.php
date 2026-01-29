<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update group_type to use ENUM type in MySQL/MariaDB
        \DB::statement("ALTER TABLE `wajebaat_groups` MODIFY `group_type` ENUM('business_grouping', 'personal_grouping', 'organization') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to string type
        \DB::statement("ALTER TABLE `wajebaat_groups` MODIFY `group_type` VARCHAR(255) NULL");
    }
};
