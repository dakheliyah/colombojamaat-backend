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
        Schema::create('waj_categories', function (Blueprint $table) {
            $table->bigIncrements('wc_id');
            $table->foreignId('miqaat_id')->constrained('miqaats')->onDelete('cascade');
            $table->string('name')->nullable();
            $table->decimal('low_bar', 10, 2);
            $table->decimal('upper_bar', 10, 2)->nullable(); // NULL = no upper limit
            $table->string('hex_color', 7)->default('#CCCCCC'); // e.g. #AABBCC
            $table->integer('order')->nullable();
            $table->timestamps();

            $table->index('miqaat_id');
            $table->index(['miqaat_id', 'low_bar']);
            $table->unique(['miqaat_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waj_categories');
    }
};

