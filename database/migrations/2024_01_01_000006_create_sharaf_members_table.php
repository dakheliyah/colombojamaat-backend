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
        Schema::create('sharaf_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sharaf_id')->constrained('sharafs')->onDelete('cascade');
            $table->foreignId('sharaf_position_id')->constrained('sharaf_positions')->onDelete('cascade');
            $table->string('its_id'); // references local_census/foreign_census.ITS_ID
            $table->timestamps();

            $table->index('sharaf_id');
            $table->index('sharaf_position_id');
            $table->index('its_id');
            // Prevents multiple positions per person in same sharaf
            $table->unique(['sharaf_id', 'its_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sharaf_members');
    }
};
