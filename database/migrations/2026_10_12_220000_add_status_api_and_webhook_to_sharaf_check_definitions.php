<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sharaf_check_definitions', function (Blueprint $table) {
            $table->string('status_api_url', 500)->nullable()->after('user_type');
            $table->string('webhook_url', 500)->nullable()->after('status_api_url');
        });
    }

    public function down(): void
    {
        Schema::table('sharaf_check_definitions', function (Blueprint $table) {
            $table->dropColumn(['status_api_url', 'webhook_url']);
        });
    }
};
