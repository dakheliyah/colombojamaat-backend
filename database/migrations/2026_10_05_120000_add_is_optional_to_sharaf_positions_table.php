<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sharaf_positions', function (Blueprint $table) {
            $table->boolean('is_optional')->default(false)->after('order');
        });

        DB::table('sharaf_positions')->where('order', '!=', 1)->update(['is_optional' => true]);

        DB::table('sharaf_positions')
            ->where(function ($query) {
                $query->whereRaw('LOWER(TRIM(name)) in (?, ?)', ['dulhan', 'dikri'])
                    ->orWhereRaw('LOWER(TRIM(display_name)) in (?, ?)', ['dulhan', 'dikri']);
            })
            ->update(['is_optional' => false]);
    }

    public function down(): void
    {
        Schema::table('sharaf_positions', function (Blueprint $table) {
            $table->dropColumn('is_optional');
        });
    }
};
