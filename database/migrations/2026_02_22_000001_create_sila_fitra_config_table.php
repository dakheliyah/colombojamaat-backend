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
        Schema::create('sila_fitra_config', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('miqaat_id');
            $table->decimal('misaqwala_rate', 10, 2);
            $table->decimal('non_misaq_hamal_mayat_rate', 10, 2);
            $table->string('currency', 3)->default('LKR');
            $table->timestamps();

            $table->unique('miqaat_id');
            $table->foreign('miqaat_id')->references('id')->on('miqaats')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sila_fitra_config');
    }
};
