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
        Schema::create('payment_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_definition_id');
            $table->string('name'); // e.g., "lagat", "najwa_ada"
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['sharaf_definition_id', 'name']);
        });

        Schema::table('payment_definitions', function (Blueprint $table) {
            $table->foreign('sharaf_definition_id')
                ->references('id')
                ->on('sharaf_definitions')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_definitions', function (Blueprint $table) {
            $table->dropForeign(['sharaf_definition_id']);
        });
        Schema::dropIfExists('payment_definitions');
    }
};
