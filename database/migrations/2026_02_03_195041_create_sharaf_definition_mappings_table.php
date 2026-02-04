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
        Schema::create('sharaf_definition_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_sharaf_definition_id')->constrained('sharaf_definitions')->onDelete('cascade');
            $table->foreignId('target_sharaf_definition_id')->constrained('sharaf_definitions')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->string('created_by_its')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Unique constraint to prevent duplicate mappings in one direction
            $table->unique(['source_sharaf_definition_id', 'target_sharaf_definition_id'], 'unique_source_target');
            
            // Indexes for performance
            $table->index('source_sharaf_definition_id');
            $table->index('target_sharaf_definition_id');
            
            // Check constraint to ensure source != target
            // Note: MySQL doesn't support check constraints in older versions, so we'll validate in application logic
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sharaf_definition_mappings');
    }
};
