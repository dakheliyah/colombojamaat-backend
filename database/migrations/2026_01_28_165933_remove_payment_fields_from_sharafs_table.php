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
            $table->dropColumn(['lagat_paid', 'najwa_ada_paid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sharafs', function (Blueprint $table) {
            $table->boolean('lagat_paid')->default(false)->after('hof_its');
            $table->boolean('najwa_ada_paid')->default(false)->after('lagat_paid');
        });
    }
};
