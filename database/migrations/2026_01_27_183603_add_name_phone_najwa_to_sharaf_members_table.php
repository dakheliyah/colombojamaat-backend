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
            $table->string('name')->nullable()->after('sp_keyno');
            $table->string('phone')->nullable()->after('name');
            $table->string('najwa')->nullable()->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sharaf_members', function (Blueprint $table) {
            $table->dropColumn(['name', 'phone', 'najwa']);
        });
    }
};
