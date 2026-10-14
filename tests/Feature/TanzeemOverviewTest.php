<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class TanzeemOverviewTest extends TestCase
{
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        // APP_URL includes /api. After the first request Laravel adopts the host root and drops that prefix.
        URL::forceRootUrl(config('app.url'));

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('user_sharaf_type');
        Schema::dropIfExists('user_role');
        Schema::dropIfExists('users');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('sharaf_members');
        Schema::dropIfExists('sharafs');
        Schema::dropIfExists('sharaf_definitions');
        Schema::dropIfExists('sharaf_types');
        Schema::dropIfExists('census');
        Schema::dropIfExists('events');
        Schema::dropIfExists('miqaats');

        Schema::create('miqaats', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('description')->nullable();
            $table->boolean('active_status')->default(false);
            $table->boolean('archived')->default(false);
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('miqaat_id');
            $table->date('date');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('sharaf_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('its_no')->nullable();
            $table->timestamps();
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('user_role', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['user_id', 'role_id']);
        });

        Schema::create('user_sharaf_type', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('sharaf_type_id');
            $table->primary(['user_id', 'sharaf_type_id']);
        });

        Schema::create('sharaf_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('sharaf_type_id')->nullable();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('sharafs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_definition_id');
            $table->integer('rank');
            $table->string('name')->nullable();
            $table->integer('capacity')->default(0);
            $table->string('status')->default('pending');
            $table->string('hof_its');
            $table->timestamps();
        });

        Schema::create('census', function (Blueprint $table) {
            $table->id();
            $table->string('its_id')->unique();
            $table->string('hof_id');
            $table->string('name')->nullable();
            $table->string('gender')->nullable();
            $table->timestamps();
        });

        Schema::create('sharaf_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_id');
            $table->string('its_id');
            $table->string('name')->nullable();
            $table->boolean('on_vms')->default(false);
            $table->timestamps();
        });
    }

    public function test_overview_counts_sharafs_and_headcount_per_definition(): void
    {
        $miqaatId = $this->insertMiqaat('Ashara');
        $otherMiqaatId = $this->insertMiqaat('Other');
        $typeId = DB::table('sharaf_types')->insertGetId([
            'name' => 'Security',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dayOne = $this->insertEvent($miqaatId, '2026-06-01', 'Day 1');
        $dayTwo = $this->insertEvent($miqaatId, '2026-06-02', 'Day 2');
        $otherEvent = $this->insertEvent($otherMiqaatId, '2026-06-01', 'Elsewhere');

        $gateId = $this->insertDefinition($dayOne, 'Gate', 1, $typeId);
        $flowId = $this->insertDefinition($dayOne, 'Flow', 2, null);
        $parkingId = $this->insertDefinition($dayTwo, 'Parking', 1, $typeId);
        $otherDefinitionId = $this->insertDefinition($otherEvent, 'Hidden', 1, $typeId);

        $this->insertPerson('1001', 'Male', 'Ahmed');
        $this->insertPerson('1002', 'Female', 'Fatema');
        $this->insertPerson('1003', 'female');
        $this->insertPerson('1005', 'male', 'Yusuf');
        $this->insertPerson('1006', 'Male', 'Cancelled Person');

        $confirmed = $this->insertSharaf($gateId, 1, 'confirmed', '1001', 'Gate A');
        $this->insertMember($confirmed, '1001', true);
        $this->insertMember($confirmed, '1002', false, 'Fatema Member');

        $pending = $this->insertSharaf($gateId, 2, 'pending', '1003');
        $this->insertMember($pending, '9999', false);
        $this->insertMember($pending, '1003', false);

        $cancelled = $this->insertSharaf($gateId, 3, 'cancelled', '1006');
        $this->insertMember($cancelled, '1006', true);

        $parking = $this->insertSharaf($parkingId, 1, 'pending', '1005');
        $this->insertMember($parking, '1005', false);

        $this->insertSharaf($otherDefinitionId, 1, 'confirmed', '1001');

        $this->insertUser('10000001', 'Admin');

        $response = $this->asUser('10000001')->getJson("/api/miqaats/{$miqaatId}/tanzeem-overview");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.scoped_to_sharaf_types', false)
            ->assertJsonPath('data.miqaat_name', 'Ashara')
            ->assertJsonPath('data.overall.event_count', 2)
            ->assertJsonPath('data.overall.definition_count', 3)
            ->assertJsonPath('data.overall.total_sharafs', 4)
            ->assertJsonPath('data.overall.confirmed_sharafs', 1)
            ->assertJsonPath('data.overall.pending_sharafs', 2)
            ->assertJsonPath('data.overall.cancelled_sharafs', 1)
            ->assertJsonPath('data.overall.total_members', 5)
            ->assertJsonPath('data.overall.male_count', 2)
            ->assertJsonPath('data.overall.female_count', 2)
            ->assertJsonPath('data.overall.unknown_gender_count', 1)
            ->assertJsonPath('data.overall.on_vms_count', 1);

        $events = $response->json('data.events');
        $this->assertSame('Day 1', $events[0]['event_name']);
        $this->assertSame('2026-06-01', $events[0]['event_date']);
        $this->assertSame('Gate', $events[0]['definitions'][0]['sharaf_definition_name']);
        $this->assertSame('Security', $events[0]['definitions'][0]['sharaf_type_name']);
        $this->assertSame(3, $events[0]['definitions'][0]['total_sharafs']);
        $this->assertSame(4, $events[0]['definitions'][0]['total_members']);
        $this->assertSame(1, $events[0]['definitions'][0]['male_count']);
        $this->assertSame(2, $events[0]['definitions'][0]['female_count']);
        $this->assertSame(1, $events[0]['definitions'][0]['unknown_gender_count']);
        $this->assertSame(1, $events[0]['definitions'][0]['on_vms_count']);

        $gateSharafs = $events[0]['definitions'][0]['sharafs'];
        $this->assertCount(3, $gateSharafs);
        $this->assertSame('Gate A', $gateSharafs[0]['name']);
        $this->assertSame(1, $gateSharafs[0]['rank']);
        $this->assertSame('confirmed', $gateSharafs[0]['status']);
        $this->assertSame('Ahmed', $gateSharafs[0]['members'][0]['name']);
        $this->assertSame('male', $gateSharafs[0]['members'][0]['gender']);
        $this->assertTrue($gateSharafs[0]['members'][0]['in_census']);
        $this->assertTrue($gateSharafs[0]['members'][0]['on_vms']);
        $this->assertSame('Fatema Member', $gateSharafs[0]['members'][1]['name']);
        $unknownMember = collect($gateSharafs[1]['members'])->firstWhere('its_id', '9999');
        $this->assertSame('9999', $unknownMember['name']);
        $this->assertNull($unknownMember['gender']);
        $this->assertFalse($unknownMember['in_census']);
        $this->assertSame('cancelled', $gateSharafs[2]['status']);
        $this->assertSame('Cancelled Person', $gateSharafs[2]['members'][0]['name']);

        $this->assertSame($flowId, $events[0]['definitions'][1]['sharaf_definition_id']);
        $this->assertSame(0, $events[0]['definitions'][1]['total_sharafs']);
        $this->assertSame(0, $events[0]['definitions'][1]['total_members']);
        $this->assertSame([], $events[0]['definitions'][1]['sharafs']);
        $this->assertNull($events[0]['definitions'][1]['sharaf_type_name']);

        $this->assertSame('Parking', $events[1]['definitions'][0]['sharaf_definition_name']);
        $this->assertSame(1, $events[1]['definitions'][0]['male_count']);
    }

    public function test_overview_returns_not_found_for_a_missing_miqaat(): void
    {
        $this->insertUser('10000001', 'Admin');

        $this->asUser('10000001')->getJson('/api/miqaats/999/tanzeem-overview')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_overview_requires_a_session(): void
    {
        $miqaatId = $this->insertMiqaat('Ashara');

        $this->getJson("/api/miqaats/{$miqaatId}/tanzeem-overview")
            ->assertUnauthorized();
    }

    public function test_overview_requires_tanzeem_or_admin_role(): void
    {
        $miqaatId = $this->insertMiqaat('Ashara');
        $this->insertUser('20000004', 'Help Desk');

        $this->asUser('20000004')->getJson("/api/miqaats/{$miqaatId}/tanzeem-overview")
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_overview_limits_definitions_to_the_users_sharaf_types(): void
    {
        $miqaatId = $this->insertMiqaat('Ashara');
        $securityId = DB::table('sharaf_types')->insertGetId([
            'name' => 'Security',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $kitchenId = DB::table('sharaf_types')->insertGetId([
            'name' => 'Kitchen',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $eventId = $this->insertEvent($miqaatId, '2026-06-01', 'Day 1');
        $gateId = $this->insertDefinition($eventId, 'Gate', 1, $securityId);
        $this->insertDefinition($eventId, 'Kitchen', 2, $kitchenId);
        $this->insertSharaf($gateId, 1, 'confirmed', '1001', 'Gate A');

        $this->insertUser('20000002', 'Tanzeem BGI', [$securityId]);

        $response = $this->asUser('20000002')->getJson("/api/miqaats/{$miqaatId}/tanzeem-overview");

        $response->assertOk()
            ->assertJsonPath('data.scoped_to_sharaf_types', true)
            ->assertJsonPath('data.sharaf_types.0.name', 'Security')
            ->assertJsonPath('data.overall.definition_count', 1)
            ->assertJsonPath('data.events.0.definitions.0.sharaf_definition_name', 'Gate')
            ->assertJsonPath('data.events.0.definitions.0.sharaf_type_id', $securityId)
            ->assertJsonPath('data.events.0.definitions.0.sharafs.0.name', 'Gate A');

        $this->insertUser('20000003', 'Tanzeem BGI');

        $this->asUser('20000003')->getJson("/api/miqaats/{$miqaatId}/tanzeem-overview")
            ->assertOk()
            ->assertJsonPath('data.scoped_to_sharaf_types', true)
            ->assertJsonPath('data.sharaf_types', [])
            ->assertJsonPath('data.overall.definition_count', 0)
            ->assertJsonPath('data.events', []);
    }

    private function asUser(string $its)
    {
        return $this->withCredentials()->withUnencryptedCookie('user', $its);
    }

    /**
     * @param  list<int>  $sharafTypeIds
     */
    private function insertUser(string $its, string $role, array $sharafTypeIds = []): int
    {
        $roleId = DB::table('user_roles')->where('name', $role)->value('id');
        if ($roleId === null) {
            $roleId = DB::table('user_roles')->insertGetId([
                'name' => $role,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $userId = DB::table('users')->insertGetId([
            'name' => $role,
            'email' => $its.'@example.test',
            'password' => 'secret',
            'its_no' => $its,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_role')->insert([
            'user_id' => $userId,
            'role_id' => $roleId,
        ]);

        foreach ($sharafTypeIds as $typeId) {
            DB::table('user_sharaf_type')->insert([
                'user_id' => $userId,
                'sharaf_type_id' => $typeId,
            ]);
        }

        return $userId;
    }

    private function insertMiqaat(string $name): int
    {
        return DB::table('miqaats')->insertGetId([
            'name' => $name,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-10',
            'active_status' => true,
            'archived' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertEvent(int $miqaatId, string $date, string $name): int
    {
        return DB::table('events')->insertGetId([
            'miqaat_id' => $miqaatId,
            'date' => $date,
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertDefinition(int $eventId, string $name, int $sortOrder, ?int $typeId): int
    {
        return DB::table('sharaf_definitions')->insertGetId([
            'event_id' => $eventId,
            'sharaf_type_id' => $typeId,
            'name' => $name,
            'sort_order' => $sortOrder,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertSharaf(int $definitionId, int $rank, string $status, string $hofIts, ?string $name = null): int
    {
        return DB::table('sharafs')->insertGetId([
            'sharaf_definition_id' => $definitionId,
            'rank' => $rank,
            'name' => $name,
            'capacity' => 10,
            'status' => $status,
            'hof_its' => $hofIts,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertPerson(string $itsId, string $gender, ?string $name = null): void
    {
        DB::table('census')->insert([
            'its_id' => $itsId,
            'hof_id' => $itsId,
            'name' => $name,
            'gender' => $gender,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertMember(int $sharafId, string $itsId, bool $onVms, ?string $name = null): void
    {
        DB::table('sharaf_members')->insert([
            'sharaf_id' => $sharafId,
            'its_id' => $itsId,
            'name' => $name,
            'on_vms' => $onVms,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
