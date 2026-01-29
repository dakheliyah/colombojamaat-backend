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
        // Check if id column already exists (it should from the original migration)
        $columns = \DB::select('SHOW COLUMNS FROM `wajebaat_groups` WHERE Field = "id"');
        
        if (empty($columns)) {
            // id column doesn't exist, restore it
            // Step 1: Add id column first (without auto-increment)
            \DB::statement('ALTER TABLE `wajebaat_groups` ADD COLUMN `id` BIGINT UNSIGNED NOT NULL FIRST');
            
            // Step 2: Populate id with sequential values
            \DB::statement('SET @row_number = 0');
            \DB::statement('UPDATE `wajebaat_groups` SET `id` = (@row_number := @row_number + 1) ORDER BY `miqaat_id`, `wg_id`, `its_id`');
            
            // Step 3: Drop composite primary key if it exists
            try {
                \DB::statement('ALTER TABLE `wajebaat_groups` DROP PRIMARY KEY');
            } catch (\Exception $e) {
                // Primary key might not exist, continue
            }
            
            // Step 4: Make id auto-increment and primary key
            \DB::statement('ALTER TABLE `wajebaat_groups` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY');
        } else {
            // id column already exists, just ensure it's the primary key
            $primaryKeys = \DB::select("SHOW KEYS FROM `wajebaat_groups` WHERE Key_name = 'PRIMARY' AND Column_name = 'id'");
            if (empty($primaryKeys)) {
                // id exists but isn't primary, make it primary
                \DB::statement('ALTER TABLE `wajebaat_groups` DROP PRIMARY KEY');
                \DB::statement('ALTER TABLE `wajebaat_groups` ADD PRIMARY KEY (`id`)');
                \DB::statement('ALTER TABLE `wajebaat_groups` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to composite primary key (same as the remove_id migration)
        \DB::statement('ALTER TABLE `wajebaat_groups` MODIFY `id` BIGINT UNSIGNED NOT NULL');
        \DB::statement('ALTER TABLE `wajebaat_groups` DROP PRIMARY KEY');
        \DB::statement('ALTER TABLE `wajebaat_groups` DROP COLUMN `id`');
        \DB::statement('ALTER TABLE `wajebaat_groups` ADD PRIMARY KEY (`miqaat_id`, `wg_id`, `its_id`)');
    }
};
