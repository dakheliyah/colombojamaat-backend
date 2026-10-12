<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sharaf_shift_audit_logs', function (Blueprint $table) {
            $table->json('check_mappings_used')->nullable()->after('payment_mappings_used');
        });
    }

    public function down(): void
    {
        Schema::table('sharaf_shift_audit_logs', function (Blueprint $table) {
            $table->dropColumn('check_mappings_used');
        });
    }
};
