<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sharafs', function (Blueprint $table) {
            $table->string('token', 50)->nullable()->change();
        });

        // Clean existing data for payment_currency before changing spec
        DB::statement("UPDATE sharaf_payments SET payment_currency = 'LKR' WHERE payment_currency IS NULL OR payment_currency = ''");
        DB::statement("UPDATE sharaf_payments SET payment_currency = LEFT(payment_currency, 3)");

        Schema::table('sharaf_payments', function (Blueprint $table) {
            $table->string('payment_currency', 3)->default('LKR')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sharafs', function (Blueprint $table) {
            $table->string('token', 20)->nullable()->change();
        });

        Schema::table('sharaf_payments', function (Blueprint $table) {
            $table->string('payment_currency', 5)->nullable()->default(null)->change();
        });
    }
};
