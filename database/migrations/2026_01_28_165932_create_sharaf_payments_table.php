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
        Schema::create('sharaf_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sharaf_id')->constrained('sharafs')->onDelete('cascade');
            $table->foreignId('payment_definition_id')->constrained('payment_definitions')->onDelete('cascade');
            $table->decimal('payment_amount', 10, 2)->default(0);
            $table->tinyInteger('payment_status')->default(0); // 0 = unpaid, 1 = paid
            $table->timestamps();

            $table->index('sharaf_id');
            $table->index('payment_definition_id');
            $table->unique(['sharaf_id', 'payment_definition_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sharaf_payments');
    }
};
