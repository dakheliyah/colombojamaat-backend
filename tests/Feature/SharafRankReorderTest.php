<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SharafRankReorderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('sharafs');
        Schema::dropIfExists('sharaf_definitions');
        Schema::dropIfExists('events');
        Schema::dropIfExists('miqaats');

        Schema::create('miqaats', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('miqaat_id');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('sharaf_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->string('name');
            $table->integer('sort_order')->nullable();
            $table->timestamps();
        });

        Schema::create('sharafs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_definition_id');
            $table->integer('rank');
            $table->integer('capacity')->default(1);
            $table->string('status')->default('pending');
            $table->string('hof_its');
            $table->timestamps();
            $table->unique(['sharaf_definition_id', 'rank']);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('sharafs');
        Schema::dropIfExists('sharaf_definitions');
        Schema::dropIfExists('events');
        Schema::dropIfExists('miqaats');
        parent::tearDown();
    }

    public function test_reorder_renumbers_only_the_focused_definition(): void
    {
        $focused = $this->seedDefinition('Misaq Boys', [1, 9, 99]);
        $other = $this->seedDefinition('Misaq Girls', [4, 7]);

        $this->putJson("/api/sharaf-definitions/{$focused['definition']}/sharafs/order", [
            'ids' => [$focused['sharafs'][2], $focused['sharafs'][0], $focused['sharafs'][1]],
        ])->assertOk();

        $this->assertSame(1, (int) DB::table('sharafs')->where('id', $focused['sharafs'][2])->value('rank'));
        $this->assertSame(2, (int) DB::table('sharafs')->where('id', $focused['sharafs'][0])->value('rank'));
        $this->assertSame(3, (int) DB::table('sharafs')->where('id', $focused['sharafs'][1])->value('rank'));
        $this->assertSame(4, (int) DB::table('sharafs')->where('id', $other['sharafs'][0])->value('rank'));
        $this->assertSame(7, (int) DB::table('sharafs')->where('id', $other['sharafs'][1])->value('rank'));
    }

    public function test_reorder_rejects_ids_from_another_definition(): void
    {
        $focused = $this->seedDefinition('Misaq Boys', [1, 9]);
        $other = $this->seedDefinition('Misaq Girls', [4]);

        $this->putJson("/api/sharaf-definitions/{$focused['definition']}/sharafs/order", [
            'ids' => [$focused['sharafs'][0], $other['sharafs'][0]],
        ])->assertStatus(422);

        $this->assertSame(1, (int) DB::table('sharafs')->where('id', $focused['sharafs'][0])->value('rank'));
        $this->assertSame(9, (int) DB::table('sharafs')->where('id', $focused['sharafs'][1])->value('rank'));
        $this->assertSame(4, (int) DB::table('sharafs')->where('id', $other['sharafs'][0])->value('rank'));
    }

    /**
     * @param  list<int>  $ranks
     * @return array{definition: int, sharafs: list<int>}
     */
    protected function seedDefinition(string $name, array $ranks): array
    {
        $miqaatId = DB::table('miqaats')->insertGetId([
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $eventId = DB::table('events')->insertGetId([
            'miqaat_id' => $miqaatId,
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $definitionId = DB::table('sharaf_definitions')->insertGetId([
            'event_id' => $eventId,
            'name' => $name,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sharafIds = [];
        foreach ($ranks as $index => $rank) {
            $sharafIds[] = (int) DB::table('sharafs')->insertGetId([
                'sharaf_definition_id' => $definitionId,
                'rank' => $rank,
                'capacity' => 1,
                'status' => 'pending',
                'hof_its' => (string) (1000 + $definitionId + $index),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [
            'definition' => (int) $definitionId,
            'sharafs' => $sharafIds,
        ];
    }
}
