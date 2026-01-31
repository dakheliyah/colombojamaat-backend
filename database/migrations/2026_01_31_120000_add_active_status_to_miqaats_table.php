<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add active_status boolean (1/0). Only one miqaat can be active at a time;
     * enforcement is in application layer (MiqaatController::update).
     */
    public function up(): void
    {
        Schema::table('miqaats', function (Blueprint $table) {
            $table->boolean('active_status')->default(false)->after('description');
        });

        // Backfill: set exactly one row to active (smallest id) if any exist
        $minId = DB::table('miqaats')->min('id');
        if ($minId !== null) {
            DB::table('miqaats')->where('id', $minId)->update(['active_status' => true]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('miqaats', function (Blueprint $table) {
            $table->dropColumn('active_status');
        });
    }
};
