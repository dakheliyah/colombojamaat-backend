<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_definitions', function (Blueprint $table) {
            $table->decimal('default_amount', 15, 2)->nullable()->after('user_type');
            $table->string('default_currency', 3)->nullable()->after('default_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payment_definitions', function (Blueprint $table) {
            $table->dropColumn(['default_amount', 'default_currency']);
        });
    }
};
