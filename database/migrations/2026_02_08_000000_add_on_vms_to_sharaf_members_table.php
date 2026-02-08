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
            $table->boolean('on_vms')->nullable()->default(0)->after('najwa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sharaf_members', function (Blueprint $table) {
            $table->dropColumn('on_vms');
        });
    }
};
