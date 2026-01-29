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
        Schema::create('wajebaat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('miqaat_id')->constrained('miqaats')->onDelete('cascade');
            $table->string('its_id')->index(); // logical reference to census.its_id
            $table->unsignedBigInteger('wg_id')->nullable()->index(); // join key to wajebaat_groups by (miqaat_id, wg_id)
            $table->decimal('amount', 10, 2)->default(0);
            $table->boolean('status')->default(false); // 0 = unpaid, 1 = paid
            $table->unsignedBigInteger('wc_id')->nullable();
            $table->timestamps();

            $table->unique(['miqaat_id', 'its_id']);
            $table->index(['miqaat_id', 'wg_id']);

            $table->foreign('wc_id')
                ->references('wc_id')
                ->on('waj_categories')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wajebaat');
    }
};

