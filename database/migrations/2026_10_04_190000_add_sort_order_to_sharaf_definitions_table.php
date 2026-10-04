<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sharaf_definitions', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('name');
            $table->index(['event_id', 'sort_order']);
        });

        $eventIds = DB::table('sharaf_definitions')->distinct()->pluck('event_id');
        foreach ($eventIds as $eventId) {
            $ids = DB::table('sharaf_definitions')
                ->where('event_id', $eventId)
                ->orderBy('name')
                ->orderBy('id')
                ->pluck('id');

            $order = 1;
            foreach ($ids as $id) {
                DB::table('sharaf_definitions')->where('id', $id)->update(['sort_order' => $order]);
                $order++;
            }
        }
    }

    public function down(): void
    {
        Schema::table('sharaf_definitions', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
