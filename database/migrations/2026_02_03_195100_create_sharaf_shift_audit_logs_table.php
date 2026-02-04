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
        Schema::create('sharaf_shift_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sharaf_definition_mapping_id')->constrained('sharaf_definition_mappings')->onDelete('cascade');
            $table->string('shifted_by_its')->nullable();
            $table->json('shift_summary')->nullable(); // detailed summary of what was shifted
            $table->json('sharaf_ids')->nullable(); // array of sharaf IDs that were shifted
            $table->json('position_mappings_used')->nullable(); // array of position mappings used
            $table->json('payment_mappings_used')->nullable(); // array of payment definition mappings used
            $table->json('rank_changes')->nullable(); // array of rank changes (old_rank, new_rank, sharaf_id)
            $table->timestamp('shifted_at')->nullable();
            $table->timestamps();

            // Indexes for querying
            $table->index('sharaf_definition_mapping_id');
            $table->index(['sharaf_definition_mapping_id', 'shifted_at'], 'idx_audit_mapping_shifted_at');
            $table->index('shifted_by_its');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sharaf_shift_audit_logs');
    }
};
