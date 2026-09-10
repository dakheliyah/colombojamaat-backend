<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Archived miqaats stay in the database but are hidden from dropdowns and Settings.
     */
    public function up(): void
    {
        Schema::table('miqaats', function (Blueprint $table) {
            $table->boolean('archived')->default(false)->after('active_status');
            $table->index('archived', 'miqaats_archived_index');
        });
    }

    public function down(): void
    {
        Schema::table('miqaats', function (Blueprint $table) {
            $table->dropIndex('miqaats_archived_index');
            $table->dropColumn('archived');
        });
    }
};
