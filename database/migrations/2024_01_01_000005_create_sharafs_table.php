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
        Schema::create('sharafs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sharaf_definition_id')->constrained('sharaf_definitions')->onDelete('cascade');
            $table->integer('rank'); // unique within sharaf_definition
            $table->string('name')->nullable();
            $table->integer('capacity'); // total max people in sharaf
            $table->string('status')->default('pending'); // enum: pending, bs_approved, confirmed, rejected, cancelled
            $table->string('hof_its'); // ITS number of Head of Family
            $table->boolean('lagat_paid')->default(false);
            $table->boolean('najwa_ada_paid')->default(false);
            $table->timestamps();

            $table->index('sharaf_definition_id');
            $table->index('hof_its');
            $table->unique(['sharaf_definition_id', 'rank']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sharafs');
    }
};
