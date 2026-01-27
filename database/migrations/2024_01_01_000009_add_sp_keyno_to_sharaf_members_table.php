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
        Schema::table('sharaf_members', function (Blueprint $table) {
            $table->integer('sp_keyno')->nullable()->after('its_id');
            $table->index(['sharaf_id', 'sharaf_position_id', 'sp_keyno'], 'idx_sharaf_members_ranking');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sharaf_members', function (Blueprint $table) {
            $table->dropIndex('idx_sharaf_members_ranking');
            $table->dropColumn('sp_keyno');
        });
    }
};
