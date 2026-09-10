<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes used by GET /sharafs and related list/report filters.
     * FK columns (sharaf_definition_id, event_id, miqaat_id, hof_its) are already indexed.
     */
    public function up(): void
    {
        Schema::table('sharafs', function (Blueprint $table) {
            $table->index('status', 'sharafs_status_index');
        });

        Schema::table('miqaats', function (Blueprint $table) {
            $table->index('active_status', 'miqaats_active_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('sharafs', function (Blueprint $table) {
            $table->dropIndex('sharafs_status_index');
        });

        Schema::table('miqaats', function (Blueprint $table) {
            $table->dropIndex('miqaats_active_status_index');
        });
    }
};
