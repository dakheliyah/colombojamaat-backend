<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sharaf_check_definition_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_definition_mapping_id');
            $table->unsignedBigInteger('source_sharaf_check_definition_id');
            $table->unsignedBigInteger('target_sharaf_check_definition_id');
            $table->timestamps();

            $table->foreign('sharaf_definition_mapping_id', 'scdm_mapping_fk')
                ->references('id')->on('sharaf_definition_mappings')->onDelete('cascade');
            $table->foreign('source_sharaf_check_definition_id', 'scdm_source_check_fk')
                ->references('id')->on('sharaf_check_definitions')->onDelete('cascade');
            $table->foreign('target_sharaf_check_definition_id', 'scdm_target_check_fk')
                ->references('id')->on('sharaf_check_definitions')->onDelete('cascade');

            $table->unique(['sharaf_definition_mapping_id', 'source_sharaf_check_definition_id'], 'unique_mapping_source_check');
            $table->unique(['sharaf_definition_mapping_id', 'target_sharaf_check_definition_id'], 'unique_mapping_target_check');

            $table->index('sharaf_definition_mapping_id', 'scdm_mapping_idx');
            $table->index('source_sharaf_check_definition_id', 'scdm_source_check_idx');
            $table->index('target_sharaf_check_definition_id', 'scdm_target_check_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sharaf_check_definition_mappings');
    }
};
