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
        // Make hex_color nullable (requires raw SQL since doctrine/dbal may not be installed)
        \DB::statement('ALTER TABLE `waj_categories` MODIFY `hex_color` VARCHAR(7) NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore hex_color with default value
        \DB::statement("ALTER TABLE `waj_categories` MODIFY `hex_color` VARCHAR(7) NOT NULL DEFAULT '#CCCCCC'");
    }
};
