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
        Schema::table('wajebaat_groups', function (Blueprint $table) {
            $table->string('group_name')->nullable()->after('wg_id');
            $table->string('group_type')->nullable()->after('group_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wajebaat_groups', function (Blueprint $table) {
            $table->dropColumn(['group_name', 'group_type']);
        });
    }
};
