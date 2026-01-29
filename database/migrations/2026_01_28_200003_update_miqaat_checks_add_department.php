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
            $table->unsignedBigInteger('mcd_id')->nullable()->after('its_id');
            $table->index('mcd_id');
            $table->foreign('mcd_id')
                ->references('mcd_id')
                ->on('miqaat_check_departments')
                ->nullOnDelete();

            // Replace unique(miqat_id, its_id) with unique(miqat_id, its_id, mcd_id)
            $table->dropUnique(['miqaat_id', 'its_id']);
            $table->unique(['miqaat_id', 'its_id', 'mcd_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('miqaat_checks', function (Blueprint $table) {
            $table->dropUnique(['miqaat_id', 'its_id', 'mcd_id']);
            $table->unique(['miqaat_id', 'its_id']);

            $table->dropForeign(['mcd_id']);
            $table->dropIndex(['mcd_id']);
            $table->dropColumn('mcd_id');
        });
    }
};

