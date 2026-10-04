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
        Schema::table('sharafs', function (Blueprint $table) {
            $table->foreignId('color_legend_id')
                ->nullable()
                ->after('comments')
                ->constrained('event_color_legends')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sharafs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('color_legend_id');
        });
    }
};
