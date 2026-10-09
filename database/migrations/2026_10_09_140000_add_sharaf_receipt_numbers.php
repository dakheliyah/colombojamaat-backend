<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sharafs', function (Blueprint $table) {
            $table->unsignedInteger('receipt_no')->nullable()->after('hof_its');
            $table->timestamp('receipt_issued_at')->nullable()->after('receipt_no');
        });

        Schema::table('miqaats', function (Blueprint $table) {
            $table->unsignedInteger('last_receipt_no')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('sharafs', function (Blueprint $table) {
            $table->dropColumn(['receipt_no', 'receipt_issued_at']);
        });

        Schema::table('miqaats', function (Blueprint $table) {
            $table->dropColumn('last_receipt_no');
        });
    }
};
