<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * After SharafStatus was reduced to pending/confirmed/cancelled,
 * existing rows still had bs_approved / rejected and broke enum casting on GET /sharafs.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('sharafs')->where('status', 'bs_approved')->update(['status' => 'confirmed']);
        DB::table('sharafs')->where('status', 'rejected')->update(['status' => 'cancelled']);
    }

    public function down(): void
    {
        // Irreversible without knowing which confirmed/cancelled rows were legacy.
    }
};
