<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SharafRankBulkUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('sharaf_members');
        Schema::dropIfExists('sharaf_positions');
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
            $table->string('name')->nullable();
            $table->integer('capacity')->default(1);
            $table->string('status')->default('pending');
            $table->string('hof_its');
            $table->string('token')->nullable();
            $table->timestamps();
            $table->unique(['sharaf_definition_id', 'rank']);
        });

        Schema::create('sharaf_positions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_definition_id');
            $table->string('name');
            $table->integer('order')->default(1);
            $table->timestamps();
        });

        Schema::create('sharaf_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_id');
            $table->unsignedBigInteger('sharaf_position_id');
            $table->string('its_id');
            $table->integer('sp_keyno')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('entity', 64);
            $table->unsignedBigInteger('auditable_id');
            $table->string('action', 32);
            $table->string('actor_its')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('summary')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
            $table->string('parent_entity', 64)->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('sharaf_members');
        Schema::dropIfExists('sharaf_positions');
        Schema::dropIfExists('sharafs');
        Schema::dropIfExists('sharaf_definitions');
        Schema::dropIfExists('events');
        Schema::dropIfExists('miqaats');
        parent::tearDown();
    }

    public function test_assigns_ranks_by_hof_its_without_touching_other_fields(): void
    {
        $seed = $this->seedDefinition([
            ['hof_its' => '111', 'rank' => 1, 'name' => 'Alpha', 'token' => 'T-A'],
            ['hof_its' => '222', 'rank' => 2, 'name' => 'Beta', 'token' => 'T-B'],
            ['hof_its' => '333', 'rank' => 3, 'name' => 'Gamma', 'token' => 'T-C'],
        ]);

        $response = $this->putJson("/api/sharaf-definitions/{$seed['definition']}/sharafs/ranks", [
            'assignments' => [
                ['its' => '111', 'rank' => 3],
                ['its' => '333', 'rank' => 1],
            ],
        ]);

        $response->assertOk();
        $this->assertSame(3, (int) DB::table('sharafs')->where('hof_its', '111')->value('rank'));
        $this->assertSame(2, (int) DB::table('sharafs')->where('hof_its', '222')->value('rank'));
        $this->assertSame(1, (int) DB::table('sharafs')->where('hof_its', '333')->value('rank'));
        $this->assertSame('Alpha', DB::table('sharafs')->where('hof_its', '111')->value('name'));
        $this->assertSame('T-A', DB::table('sharafs')->where('hof_its', '111')->value('token'));
        $this->assertSame('Gamma', DB::table('sharafs')->where('hof_its', '333')->value('name'));
    }

    public function test_matches_first_seat_member_its_when_hof_differs(): void
    {
        $seed = $this->seedDefinition([
            ['hof_its' => '900', 'rank' => 1, 'name' => 'One'],
            ['hof_its' => '901', 'rank' => 2, 'name' => 'Two'],
        ]);

        $positionId = DB::table('sharaf_positions')->insertGetId([
            'sharaf_definition_id' => $seed['definition'],
            'name' => 'Farzand',
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sharaf_members')->insert([
            'sharaf_id' => $seed['sharafs'][0],
            'sharaf_position_id' => $positionId,
            'its_id' => '555',
            'sp_keyno' => 1,
            'name' => 'Farzand One',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->putJson("/api/sharaf-definitions/{$seed['definition']}/sharafs/ranks", [
            'assignments' => [
                ['its' => '555', 'rank' => 2],
                ['its' => '901', 'rank' => 1],
            ],
        ])->assertOk();

        $this->assertSame(2, (int) DB::table('sharafs')->where('id', $seed['sharafs'][0])->value('rank'));
        $this->assertSame(1, (int) DB::table('sharafs')->where('id', $seed['sharafs'][1])->value('rank'));
        $this->assertSame('900', DB::table('sharafs')->where('id', $seed['sharafs'][0])->value('hof_its'));
    }

    public function test_rejects_rank_collision_with_unlisted_sharaf(): void
    {
        $seed = $this->seedDefinition([
            ['hof_its' => '111', 'rank' => 1],
            ['hof_its' => '222', 'rank' => 2],
            ['hof_its' => '333', 'rank' => 3],
        ]);

        $this->putJson("/api/sharaf-definitions/{$seed['definition']}/sharafs/ranks", [
            'assignments' => [
                ['its' => '111', 'rank' => 2],
            ],
        ])->assertStatus(422);

        $this->assertSame(1, (int) DB::table('sharafs')->where('hof_its', '111')->value('rank'));
        $this->assertSame(2, (int) DB::table('sharafs')->where('hof_its', '222')->value('rank'));
    }

    /**
     * @param  list<array{hof_its: string, rank: int, name?: string, token?: string}>  $rows
     * @return array{definition: int, sharafs: list<int>}
     */
    protected function seedDefinition(array $rows): array
    {
        $miqaatId = DB::table('miqaats')->insertGetId([
            'name' => 'Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $eventId = DB::table('events')->insertGetId([
            'miqaat_id' => $miqaatId,
            'name' => 'Test Event',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $definitionId = DB::table('sharaf_definitions')->insertGetId([
            'event_id' => $eventId,
            'name' => 'Test Def',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sharafIds = [];
        foreach ($rows as $row) {
            $sharafIds[] = (int) DB::table('sharafs')->insertGetId([
                'sharaf_definition_id' => $definitionId,
                'rank' => $row['rank'],
                'name' => $row['name'] ?? null,
                'capacity' => 1,
                'status' => 'pending',
                'hof_its' => $row['hof_its'],
                'token' => $row['token'] ?? null,
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
