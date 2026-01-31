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
        Schema::table('wajebaat', function (Blueprint $table) {
            // Drop the old unique constraint on (miqaat_id, its_id)
            $table->dropUnique(['miqaat_id', 'its_id']);
            
            // Add new unique constraint that includes wg_id
            // This allows separate records for:
            // - Personal entry: (miqaat_id, its_id, wg_id=NULL)
            // - Group entry: (miqaat_id, its_id, wg_id=7)
            // Note: MySQL allows multiple NULLs in unique constraints, so application logic should handle personal entry uniqueness
            $table->unique(['miqaat_id', 'its_id', 'wg_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wajebaat', function (Blueprint $table) {
            // Restore the old constraint
            $table->dropUnique(['miqaat_id', 'its_id', 'wg_id']);
            $table->unique(['miqaat_id', 'its_id']);
        });
    }
};
