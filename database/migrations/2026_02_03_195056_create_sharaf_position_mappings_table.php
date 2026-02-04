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
        Schema::create('sharaf_position_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sharaf_definition_mapping_id')->constrained('sharaf_definition_mappings')->onDelete('cascade');
            $table->foreignId('source_sharaf_position_id')->constrained('sharaf_positions')->onDelete('cascade');
            $table->foreignId('target_sharaf_position_id')->constrained('sharaf_positions')->onDelete('cascade');
            $table->timestamps();

            // Unique constraint: one mapping per position per definition mapping
            $table->unique(['sharaf_definition_mapping_id', 'source_sharaf_position_id'], 'unique_mapping_source_position');
            
            // Unique constraint: target position can only be mapped once per definition mapping
            $table->unique(['sharaf_definition_mapping_id', 'target_sharaf_position_id'], 'unique_mapping_target_position');
            
            // Indexes for performance
            $table->index('sharaf_definition_mapping_id');
            $table->index('source_sharaf_position_id');
            $table->index('target_sharaf_position_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sharaf_position_mappings');
    }
};
