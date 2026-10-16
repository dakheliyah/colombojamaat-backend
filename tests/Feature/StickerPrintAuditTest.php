<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StickerPrintAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('sharaf_members');
        Schema::dropIfExists('sharafs');
        Schema::dropIfExists('sharaf_definitions');
        Schema::dropIfExists('events');
        Schema::dropIfExists('miqaats');
        Schema::dropIfExists('census');
        Schema::dropIfExists('users');

        Schema::create('miqaats', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('miqaat_id');
        });

        Schema::create('sharaf_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->string('name');
        });

        Schema::create('sharafs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_definition_id');
            $table->integer('rank')->default(1);
            $table->string('name')->nullable();
            $table->integer('capacity')->default(1);
            $table->string('status')->default('pending');
            $table->string('hof_its');
            $table->timestamps();
        });

        Schema::create('sharaf_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_id');
            $table->string('its_id');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('its_no')->nullable();
        });

        Schema::create('census', function (Blueprint $table) {
            $table->id();
            $table->string('its_id');
            $table->string('name')->nullable();
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
        Schema::dropIfExists('sharafs');
        Schema::dropIfExists('sharaf_definitions');
        Schema::dropIfExists('events');
        Schema::dropIfExists('miqaats');
        Schema::dropIfExists('census');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    /**
     * @return array{miqaat_id: int, sharaf_id: int}
     */
    private function seedSharafWithTwoMembers(): array
    {
        $miqaatId = DB::table('miqaats')->insertGetId(['name' => 'Ashara']);
        $eventId = DB::table('events')->insertGetId(['miqaat_id' => $miqaatId]);
        $definitionId = DB::table('sharaf_definitions')->insertGetId([
            'event_id' => $eventId,
            'name' => 'Ziyafat',
        ]);
        $sharafId = DB::table('sharafs')->insertGetId([
            'sharaf_definition_id' => $definitionId,
            'rank' => 1,
            'hof_its' => '20324305',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('sharaf_members')->insert([
            ['sharaf_id' => $sharafId, 'its_id' => '20324305', 'created_at' => now(), 'updated_at' => now()],
            ['sharaf_id' => $sharafId, 'its_id' => '20324306', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('census')->insert(['its_id' => '20324305', 'name' => 'Shaikh']);

        return ['miqaat_id' => $miqaatId, 'sharaf_id' => $sharafId];
    }

    private function authPost(string $uri, array $data = [])
    {
        return $this->withCredentials()
            ->withUnencryptedCookie('user', '20324305')
            ->postJson($uri, $data);
    }

    public function test_each_print_click_is_logged_with_member_its(): void
    {
        ['miqaat_id' => $miqaatId, 'sharaf_id' => $sharafId] = $this->seedSharafWithTwoMembers();

        $first = $this->authPost("/api/sharafs/{$sharafId}/sticker-prints", [
            'member_its' => ['20324305', '20324306'],
        ]);
        $first->assertOk()
            ->assertJsonPath('data.print_count', 1)
            ->assertJsonPath('data.sticker_count', 2)
            ->assertJsonPath('data.member_its', ['20324305', '20324306']);

        $second = $this->authPost("/api/sharafs/{$sharafId}/sticker-prints", [
            'member_its' => ['20324305'],
        ]);
        $second->assertOk()->assertJsonPath('data.print_count', 2);

        $this->assertDatabaseHas('audit_logs', [
            'entity' => 'sharaf',
            'auditable_id' => $sharafId,
            'action' => 'sticker_printed',
            'actor_its' => '20324305',
            'actor_name' => 'Shaikh',
        ]);

        $counts = $this->getJson("/api/sharafs/sticker-print-counts?miqaat_id={$miqaatId}")
            ->assertOk()
            ->json('data');

        $byIts = collect($counts['counts'])->keyBy('its');
        $this->assertSame(2, $byIts['20324305']['print_count']);
        $this->assertSame(1, $byIts['20324306']['print_count']);
        $this->assertSame($sharafId, $counts['sharaf_runs'][0]['sharaf_id']);
        $this->assertSame(2, $counts['sharaf_runs'][0]['print_count']);

        $this->getJson("/api/sharafs/{$sharafId}/audit-logs?action=sticker_printed")
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.data.0.actor_its', '20324305');

        $this->authPost('/api/sharafs/999/sticker-prints', [
            'member_its' => ['20324305'],
        ])->assertNotFound();
    }

    public function test_subset_print_only_counts_listed_members(): void
    {
        ['miqaat_id' => $miqaatId, 'sharaf_id' => $sharafId] = $this->seedSharafWithTwoMembers();

        $this->authPost("/api/sharafs/{$sharafId}/sticker-prints", [
            'member_its' => ['20324305'],
        ])->assertOk();

        $counts = $this->getJson("/api/sharafs/sticker-print-counts?miqaat_id={$miqaatId}")
            ->assertOk()
            ->json('data.counts');

        $this->assertCount(1, $counts);
        $this->assertSame('20324305', $counts[0]['its']);
        $this->assertSame(1, $counts[0]['print_count']);
    }

    public function test_legacy_rows_credit_all_current_members_with_full_run_count(): void
    {
        ['miqaat_id' => $miqaatId, 'sharaf_id' => $sharafId] = $this->seedSharafWithTwoMembers();

        for ($i = 0; $i < 3; $i++) {
            DB::table('audit_logs')->insert([
                'entity' => 'sharaf',
                'auditable_id' => $sharafId,
                'action' => 'sticker_printed',
                'actor_its' => '20324305',
                'actor_name' => 'Shaikh',
                'summary' => 'Printed 2 stickers for sharaf #'.$sharafId,
                'metadata' => json_encode(['sticker_count' => 2]),
                'created_at' => now(),
            ]);
        }

        $counts = $this->getJson("/api/sharafs/sticker-print-counts?miqaat_id={$miqaatId}")
            ->assertOk()
            ->json('data');

        $byIts = collect($counts['counts'])->keyBy('its');
        $this->assertSame(3, $byIts['20324305']['print_count']);
        $this->assertSame(3, $byIts['20324306']['print_count']);
        $this->assertSame(3, $counts['sharaf_runs'][0]['print_count']);
    }

    public function test_mix_legacy_and_member_aware_counts(): void
    {
        ['miqaat_id' => $miqaatId, 'sharaf_id' => $sharafId] = $this->seedSharafWithTwoMembers();

        for ($i = 0; $i < 2; $i++) {
            DB::table('audit_logs')->insert([
                'entity' => 'sharaf',
                'auditable_id' => $sharafId,
                'action' => 'sticker_printed',
                'actor_its' => '20324305',
                'actor_name' => 'Shaikh',
                'summary' => 'Printed 2 stickers for sharaf #'.$sharafId,
                'metadata' => json_encode(['sticker_count' => 2]),
                'created_at' => now(),
            ]);
        }

        $this->authPost("/api/sharafs/{$sharafId}/sticker-prints", [
            'member_its' => ['20324305'],
        ])->assertOk();

        $counts = $this->getJson("/api/sharafs/sticker-print-counts?miqaat_id={$miqaatId}")
            ->assertOk()
            ->json('data');

        $byIts = collect($counts['counts'])->keyBy('its');
        $this->assertSame(3, $byIts['20324305']['print_count']);
        $this->assertSame(2, $byIts['20324306']['print_count']);
        $this->assertSame(3, $counts['sharaf_runs'][0]['print_count']);
    }

    public function test_rejects_empty_and_unknown_member_its(): void
    {
        ['sharaf_id' => $sharafId] = $this->seedSharafWithTwoMembers();

        $this->authPost("/api/sharafs/{$sharafId}/sticker-prints", [])
            ->assertStatus(422);

        $this->authPost("/api/sharafs/{$sharafId}/sticker-prints", [
            'member_its' => [],
        ])->assertStatus(422);

        $this->authPost("/api/sharafs/{$sharafId}/sticker-prints", [
            'member_its' => ['99999999'],
        ])->assertStatus(422);
    }
}
