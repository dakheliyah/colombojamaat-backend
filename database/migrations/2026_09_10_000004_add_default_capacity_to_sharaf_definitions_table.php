<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sharaf_definitions', function (Blueprint $table) {
            $table->unsignedInteger('default_capacity')->nullable()->after('key');
        });
    }

    public function down(): void
    {
        Schema::table('sharaf_definitions', function (Blueprint $table) {
            $table->dropColumn('default_capacity');
        });
    }
};
