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
        Schema::create('payment_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sharaf_definition_id')->constrained('sharaf_definitions')->onDelete('cascade');
            $table->string('name'); // e.g., "lagat", "najwa_ada"
            $table->text('description')->nullable();
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
        Schema::dropIfExists('payment_definitions');
    }
};
