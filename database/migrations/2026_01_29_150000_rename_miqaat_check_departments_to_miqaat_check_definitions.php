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
        Schema::table('miqaat_checks', function (Blueprint $table) {
            $table->dropForeign(['mcd_id']);
        });

        Schema::rename('miqaat_check_departments', 'miqaat_check_definitions');

        Schema::table('miqaat_checks', function (Blueprint $table) {
            $table->foreign('mcd_id')
                ->references('mcd_id')
                ->on('miqaat_check_definitions')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('miqaat_checks', function (Blueprint $table) {
            $table->dropForeign(['mcd_id']);
        });

        Schema::rename('miqaat_check_definitions', 'miqaat_check_departments');

        Schema::table('miqaat_checks', function (Blueprint $table) {
            $table->foreign('mcd_id')
                ->references('mcd_id')
                ->on('miqaat_check_departments')
                ->nullOnDelete();
        });
    }
};
