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
        // Drop the id column and use composite primary key
        // Since multiple rows share the same wg_id (membership table),
        // we use (miqaat_id, wg_id, its_id) as composite primary key
        
        // First, drop the auto-increment column (must be done before dropping primary key)
        \DB::statement('ALTER TABLE `wajebaat_groups` MODIFY `id` BIGINT UNSIGNED NOT NULL');
        \DB::statement('ALTER TABLE `wajebaat_groups` DROP PRIMARY KEY');
        \DB::statement('ALTER TABLE `wajebaat_groups` DROP COLUMN `id`');
        \DB::statement('ALTER TABLE `wajebaat_groups` ADD PRIMARY KEY (`miqaat_id`, `wg_id`, `its_id`)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore id column as primary key
        \DB::statement('ALTER TABLE `wajebaat_groups` DROP PRIMARY KEY');
        \DB::statement('ALTER TABLE `wajebaat_groups` ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST');
    }
};
