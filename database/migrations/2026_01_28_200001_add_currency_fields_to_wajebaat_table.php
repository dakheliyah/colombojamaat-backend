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
        Schema::table('wajebaat', function (Blueprint $table) {
            $table->string('currency', 3)->default('LKR')->after('amount');
            $table->decimal('conversion_rate', 12, 6)->default(1)->after('currency');
            $table->index('currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wajebaat', function (Blueprint $table) {
            $table->dropIndex(['currency']);
            $table->dropColumn(['currency', 'conversion_rate']);
        });
    }
};

