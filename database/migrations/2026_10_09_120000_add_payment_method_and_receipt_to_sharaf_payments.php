<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sharaf_payments', function (Blueprint $table) {
            $table->string('payment_method', 16)->nullable()->after('paid_currency');
            $table->string('receipt_path', 500)->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('sharaf_payments', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'receipt_path']);
        });
    }
};
