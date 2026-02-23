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
        Schema::table('sila_fitra_calculations', function (Blueprint $table) {
            $table->unsignedInteger('haj_e_badal')->nullable()->after('mayat_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sila_fitra_calculations', function (Blueprint $table) {
            $table->dropColumn('haj_e_badal');
        });
    }
};
