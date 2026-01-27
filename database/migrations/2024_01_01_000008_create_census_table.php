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
        Schema::create('census', function (Blueprint $table) {
            $table->id();
            $table->string('its_id')->unique();
            $table->string('hof_id')->index();
            $table->string('father_its')->nullable();
            $table->string('mother_its')->nullable();
            $table->string('spouse_its')->nullable();
            $table->string('sabeel')->nullable();
            $table->string('name')->nullable();
            $table->string('arabic_name')->nullable();
            $table->unsignedInteger('age')->nullable();
            $table->string('gender')->nullable();
            $table->string('misaq')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('blood_group')->nullable();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('pincode')->nullable();
            $table->string('mohalla')->nullable();
            $table->string('area')->nullable();
            $table->string('jamaat')->nullable();
            $table->string('jamiat')->nullable();
            $table->string('pwd')->nullable();
            $table->string('synced')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('census');
    }
};
