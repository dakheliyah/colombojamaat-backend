<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds user_type to miqaat_check_definitions, matching the users.user_type column
     * (same set of values: BS, Admin, Help Desk, Anjuman, Finance).
     */
    public function up(): void
    {
        Schema::table('miqaat_check_definitions', function (Blueprint $table) {
            $table->string('user_type', 50)->nullable()->after('miqaat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('miqaat_check_definitions', function (Blueprint $table) {
            $table->dropColumn('user_type');
        });
    }
};
