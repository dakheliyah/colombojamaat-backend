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
        Schema::create('sharaf_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sharaf_definition_id')->constrained('sharaf_definitions')->onDelete('cascade');
            $table->string('name'); // e.g., "HOF", "FM"
            $table->string('display_name');
            $table->integer('capacity')->nullable(); // max people for this position
            $table->integer('order'); // display order
            $table->timestamps();

            $table->index('sharaf_definition_id');
            $table->unique(['sharaf_definition_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sharaf_positions');
    }
};
