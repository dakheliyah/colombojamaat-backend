<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sharaf_check_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_definition_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('user_type')->default('Finance');
            $table->timestamps();

            $table->unique(['sharaf_definition_id', 'name']);
            $table->index('sharaf_definition_id');
        });

        Schema::table('sharaf_check_definitions', function (Blueprint $table) {
            $table->foreign('sharaf_definition_id')
                ->references('id')
                ->on('sharaf_definitions')
                ->onDelete('cascade');
        });

        Schema::create('sharaf_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sharaf_id')->constrained('sharafs')->onDelete('cascade');
            $table->foreignId('sharaf_check_definition_id')->constrained('sharaf_check_definitions')->onDelete('cascade');
            $table->boolean('is_done')->default(false);
            $table->string('checked_by_its')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['sharaf_id', 'sharaf_check_definition_id']);
            $table->index('sharaf_check_definition_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sharaf_checks');
        Schema::dropIfExists('sharaf_check_definitions');
    }
};
