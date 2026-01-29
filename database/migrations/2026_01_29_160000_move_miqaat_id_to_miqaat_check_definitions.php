<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Move miqaat_id from miqaat_checks to miqaat_check_definitions.
     * - Add miqaat_id to miqaat_check_definitions (FK to miqaats).
     * - Backfill: definitions get miqaat_id from their checks; if one definition
     *   was used for multiple miqaats, duplicate definition rows are created per miqaat.
     * - Drop miqaat_id from miqaat_checks. Uniqueness on checks becomes (its_id, mcd_id).
     */
    public function up(): void
    {
        // 1. Add miqaat_id to miqaat_check_definitions (nullable for backfill) — skip if already present (resumable)
        if (! Schema::hasColumn('miqaat_check_definitions', 'miqaat_id')) {
            Schema::table('miqaat_check_definitions', function (Blueprint $table) {
                $table->unsignedBigInteger('miqaat_id')->nullable()->after('name');
                $table->foreign('miqaat_id')->references('id')->on('miqaats')->onDelete('cascade');
                $table->index('miqaat_id');
            });
        }

        // 2. Backfill: set one miqaat_id per existing definition (min miqaat_id from its checks)
        foreach (DB::table('miqaat_check_definitions')->get(['mcd_id']) as $row) {
            $minMiqaatId = DB::table('miqaat_checks')->where('mcd_id', $row->mcd_id)->min('miqaat_id');
            if ($minMiqaatId !== null) {
                DB::table('miqaat_check_definitions')->where('mcd_id', $row->mcd_id)->update(['miqaat_id' => $minMiqaatId]);
            }
        }

        // 3. For (mcd_id, miqaat_id) pairs where definition.miqaat_id != that miqaat_id, create new definition row and point checks to it
        $pairs = DB::table('miqaat_checks')
            ->select('mcd_id', 'miqaat_id')
            ->distinct()
            ->get();

        foreach ($pairs as $row) {
            $mcdId = (int) $row->mcd_id;
            $miqaatId = (int) $row->miqaat_id;

            $def = DB::table('miqaat_check_definitions')->where('mcd_id', $mcdId)->first();
            if (!$def || (int) $def->miqaat_id === $miqaatId) {
                continue;
            }

            // This mcd_id is used for another miqaat; create a new definition row for this miqaat
            $newMcdId = DB::table('miqaat_check_definitions')->insertGetId([
                'name' => $def->name,
                'miqaat_id' => $miqaatId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('miqaat_checks')
                ->where('mcd_id', $mcdId)
                ->where('miqaat_id', $miqaatId)
                ->update(['mcd_id' => $newMcdId]);
        }

        // 4. Make miqaat_id NOT NULL; change unique from (name) to (name, miqaat_id) — resumable
        // Index was created when table was named miqaat_check_departments; MySQL keeps original index name after rename
        $oldIndexExists = collect(DB::select("SHOW INDEX FROM miqaat_check_definitions WHERE Key_name = 'miqaat_check_departments_name_unique'"))->isNotEmpty();
        if ($oldIndexExists) {
            Schema::table('miqaat_check_definitions', function (Blueprint $table) {
                $table->dropIndex('miqaat_check_departments_name_unique');
            });
        }
        $newUniqueExists = collect(DB::select("SHOW INDEX FROM miqaat_check_definitions WHERE Key_name = 'miqaat_check_definitions_name_miqaat_id_unique'"))->isNotEmpty();
        if (! $newUniqueExists) {
            Schema::table('miqaat_check_definitions', function (Blueprint $table) {
                $table->unique(['name', 'miqaat_id']);
            });
        }
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE miqaat_check_definitions MODIFY miqaat_id BIGINT UNSIGNED NOT NULL');
        } else {
            Schema::table('miqaat_check_definitions', function (Blueprint $table) {
                $table->unsignedBigInteger('miqaat_id')->nullable(false)->change();
            });
        }

        // 5. Drop miqaat_id from miqaat_checks — skip if already dropped (resumable)
        if (Schema::hasColumn('miqaat_checks', 'miqaat_id')) {
            Schema::table('miqaat_checks', function (Blueprint $table) {
                $table->dropUnique(['miqaat_id', 'its_id', 'mcd_id']);
                $table->dropForeign(['miqaat_id']);
                $table->dropIndex(['miqaat_id']);
                $table->dropColumn('miqaat_id');
                $table->unique(['its_id', 'mcd_id']);
            });
        }
    }

    /**
     * Reverse: put miqaat_id back on miqaat_checks, remove from miqaat_check_definitions.
     */
    public function down(): void
    {
        // Add miqaat_id back to miqaat_checks
        Schema::table('miqaat_checks', function (Blueprint $table) {
            $table->unsignedBigInteger('miqaat_id')->nullable()->after('id');
            $table->foreign('miqaat_id')->references('id')->on('miqaats')->onDelete('cascade');
            $table->index('miqaat_id');
        });

        // Backfill miqaat_checks.miqaat_id from miqaat_check_definitions
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('UPDATE miqaat_checks mc INNER JOIN miqaat_check_definitions mcd ON mc.mcd_id = mcd.mcd_id SET mc.miqaat_id = mcd.miqaat_id');
        } else {
            foreach (DB::table('miqaat_checks')->get(['id', 'mcd_id']) as $check) {
                $miqaatId = DB::table('miqaat_check_definitions')->where('mcd_id', $check->mcd_id)->value('miqaat_id');
                if ($miqaatId !== null) {
                    DB::table('miqaat_checks')->where('id', $check->id)->update(['miqaat_id' => $miqaatId]);
                }
            }
        }

        Schema::table('miqaat_checks', function (Blueprint $table) {
            $table->unsignedBigInteger('miqaat_id')->nullable(false)->change();
            $table->dropUnique(['its_id', 'mcd_id']);
            $table->unique(['miqaat_id', 'its_id', 'mcd_id']);
        });

        // Remove miqaat_id from miqaat_check_definitions: drop unique (name, miqaat_id), drop column.
        // Note: unique(name) is not restored because rollback may leave duplicate names (one per miqaat).
        Schema::table('miqaat_check_definitions', function (Blueprint $table) {
            $table->dropUnique(['name', 'miqaat_id']);
            $table->dropForeign(['miqaat_id']);
            $table->dropIndex(['miqaat_id']);
            $table->dropColumn('miqaat_id');
        });
    }
};
