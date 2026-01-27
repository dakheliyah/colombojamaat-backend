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
        Schema::create('sharaf_clearances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sharaf_id')->constrained('sharafs')->onDelete('cascade');
            $table->string('hof_its'); // ITS number of HOF being cleared
            $table->boolean('is_cleared')->default(false);
            $table->string('cleared_by_its')->nullable(); // ITS of person who cleared
            $table->timestamp('cleared_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('sharaf_id');
            $table->index('hof_its');
            // One clearance record per sharaf per HOF
            $table->unique(['sharaf_id', 'hof_its']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sharaf_clearances');
    }
};
