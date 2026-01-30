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
        Schema::table('wajebaat', function (Blueprint $table) {
            $table->boolean('is_isolated')->default(false)->after('wc_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wajebaat', function (Blueprint $table) {
            $table->dropColumn('is_isolated');
        });
    }
};
