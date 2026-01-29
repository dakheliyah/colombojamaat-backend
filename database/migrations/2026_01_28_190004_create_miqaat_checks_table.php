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
        Schema::create('miqaat_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('miqaat_id')->constrained('miqaats')->onDelete('cascade');
            $table->string('its_id')->index(); // logical reference to census.its_id
            $table->boolean('is_cleared')->default(false);
            $table->string('cleared_by_its')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['miqaat_id', 'its_id']);
            $table->index('miqaat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('miqaat_checks');
    }
};

