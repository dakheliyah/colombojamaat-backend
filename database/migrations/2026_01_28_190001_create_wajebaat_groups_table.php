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
        Schema::create('wajebaat_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wg_id'); // group identifier (shared across members)
            $table->foreignId('miqaat_id')->constrained('miqaats')->onDelete('cascade');
            $table->string('master_its')->index(); // logical reference to census.its_id
            $table->string('its_id')->index(); // member ITS; logical reference to census.its_id
            $table->timestamps();

            // A person can belong to at most one group per miqaat
            $table->unique(['miqaat_id', 'its_id']);

            // Prevent duplicate membership rows for the same group
            $table->unique(['miqaat_id', 'wg_id', 'its_id']);

            $table->index(['miqaat_id', 'wg_id']);
            $table->index(['miqaat_id', 'master_its']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wajebaat_groups');
    }
};

