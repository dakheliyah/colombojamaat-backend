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
        Schema::table('sharaf_payments', function (Blueprint $table) {
            $table->string('payment_currency', 5)->nullable()->after('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sharaf_payments', function (Blueprint $table) {
            $table->dropColumn('payment_currency');
        });
    }
};
