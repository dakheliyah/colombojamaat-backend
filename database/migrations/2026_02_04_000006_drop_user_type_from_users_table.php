<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Drops user_type from users after app and clients use roles (user_role pivot).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('user_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('user_type', ['BS', 'Admin', 'Help Desk', 'Anjuman', 'Finance', 'Follow Up'])
                ->default('BS')
                ->after('email');
        });
    }
};
