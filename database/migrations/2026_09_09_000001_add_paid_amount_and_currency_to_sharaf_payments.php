<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sharaf_payments', function (Blueprint $table) {
            $table->decimal('paid_amount', 10, 2)->nullable()->after('payment_currency');
            $table->string('paid_currency', 3)->nullable()->after('paid_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sharaf_payments', function (Blueprint $table) {
            $table->dropColumn(['paid_amount', 'paid_currency']);
        });
    }
};
