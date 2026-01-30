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
            $table->string('token', 20)->nullable()->unique()->after('hof_its');
            $table->text('comments')->nullable()->after('token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sharafs', function (Blueprint $table) {
            $table->dropColumn(['token', 'comments']);
        });
    }
};
