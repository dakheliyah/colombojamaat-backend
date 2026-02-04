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
        Schema::create('payment_definition_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sharaf_definition_mapping_id')->constrained('sharaf_definition_mappings')->onDelete('cascade');
            $table->foreignId('source_payment_definition_id')->constrained('payment_definitions')->onDelete('cascade');
            $table->foreignId('target_payment_definition_id')->constrained('payment_definitions')->onDelete('cascade');
            $table->timestamps();

            // Unique constraint: one mapping per payment definition per definition mapping
            $table->unique(['sharaf_definition_mapping_id', 'source_payment_definition_id'], 'unique_mapping_source_payment');
            
            // Unique constraint: target payment definition can only be mapped once per definition mapping
            $table->unique(['sharaf_definition_mapping_id', 'target_payment_definition_id'], 'unique_mapping_target_payment');
            
            // Indexes for performance
            $table->index('sharaf_definition_mapping_id');
            $table->index('source_payment_definition_id');
            $table->index('target_payment_definition_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_definition_mappings');
    }
};
