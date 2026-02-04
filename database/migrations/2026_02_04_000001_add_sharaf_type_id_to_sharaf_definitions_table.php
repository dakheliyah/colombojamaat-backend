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
        Schema::table('sharaf_definitions', function (Blueprint $table) {
            $table->foreignId('sharaf_type_id')->nullable()->after('event_id')->constrained('sharaf_types')->onDelete('restrict');
            $table->index('sharaf_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sharaf_definitions', function (Blueprint $table) {
            $table->dropForeign(['sharaf_type_id']);
            $table->dropIndex(['sharaf_type_id']);
        });
    }
};
