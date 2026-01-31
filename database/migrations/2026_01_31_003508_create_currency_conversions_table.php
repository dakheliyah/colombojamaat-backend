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
        Schema::create('currency_conversions', function (Blueprint $table) {
            $table->id();
            $table->string('from_currency', 3); // Source currency (e.g., 'LKR', 'USD', 'GBP')
            $table->string('to_currency', 3)->default('INR'); // Target currency (always INR for categorization)
            $table->decimal('rate', 12, 6); // Conversion rate: 1 from_currency = rate to_currency
            $table->date('effective_date'); // Date when this rate becomes effective
            $table->date('expiry_date')->nullable(); // Optional: when this rate expires (NULL = current rate)
            $table->boolean('is_active')->default(true); // Whether this rate is currently active
            $table->string('source')->nullable(); // Optional: where the rate came from (e.g., 'manual', 'api', 'bank')
            $table->text('notes')->nullable(); // Optional notes
            $table->timestamps();

            // Indexes (with custom names to avoid MySQL 64-char limit)
            $table->index(['from_currency', 'to_currency', 'effective_date'], 'cc_from_to_date_idx');
            $table->index(['from_currency', 'to_currency', 'is_active'], 'cc_from_to_active_idx');
            $table->unique(['from_currency', 'to_currency', 'effective_date'], 'cc_from_to_date_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_conversions');
    }
};
