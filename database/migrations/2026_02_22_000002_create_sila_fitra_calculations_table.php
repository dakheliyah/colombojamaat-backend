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
        Schema::create('sila_fitra_calculations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('miqaat_id');
            $table->string('hof_its', 255);
            $table->unsignedInteger('misaqwala_count')->default(0);
            $table->unsignedInteger('non_misaq_count')->default(0);
            $table->unsignedInteger('hamal_count')->default(0);
            $table->unsignedInteger('mayat_count')->default(0);
            $table->decimal('calculated_amount', 10, 2);
            $table->string('currency', 3)->default('LKR');
            $table->string('receipt_path', 500)->nullable();
            $table->boolean('payment_verified')->default(false);
            $table->string('verified_by_its', 255)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['miqaat_id', 'hof_its']);
            $table->index('miqaat_id');
            $table->index('hof_its');
            $table->index('payment_verified');
            $table->foreign('miqaat_id')->references('id')->on('miqaats')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sila_fitra_calculations');
    }
};
