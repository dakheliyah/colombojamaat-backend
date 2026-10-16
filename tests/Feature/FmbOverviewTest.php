<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class FmbOverviewTest extends TestCase
{
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
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
        Schema::dropIfExists('foreign_census');
        Schema::dropIfExists('census');
        Schema::dropIfExists('events');
        Schema::dropIfExists('miqaats');

        Schema::create('miqaats', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('miqaat_id');
            $table->date('date');
            $table->string('name');
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
            $table->timestamps();
        });

        Schema::create('sharafs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_definition_id');
            $table->integer('rank');
            $table->string('status')->default('pending');
            $table->string('hof_its');
            $table->timestamps();
        });

        Schema::create('census', function (Blueprint $table) {
            $table->id();
            $table->string('its_id')->unique();
            $table->string('gender')->nullable();
            $table->integer('age')->nullable();
            $table->timestamps();
        });

        Schema::create('foreign_census', function (Blueprint $table) {
            $table->id();
            $table->string('its_id')->unique();
            $table->string('gender')->nullable();
            $table->integer('age')->nullable();
            $table->timestamps();
        });

        Schema::create('sharaf_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_id');
            $table->string('its_id');
            $table->timestamps();
        });
    }

    public function test_overview_counts_definitions_and_unique_food_headcount(): void
    {
        $miqaatId = $this->insertMiqaat('Ashara');
        $typeId = DB::table('sharaf_types')->insertGetId([
            'name' => 'Khidmat',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dayOne = $this->insertEvent($miqaatId, '2026-06-01', 'Day 1');
        $dayTwo = $this->insertEvent($miqaatId, '2026-06-02', 'Day 2');
        $gateId = $this->insertDefinition($dayOne, 'Gate', 1, $typeId);
        $kitchenId = $this->insertDefinition($dayOne, 'Kitchen', 2, null);
        $parkingId = $this->insertDefinition($dayTwo, 'Parking', 1, $typeId);

        $this->insertLocal('1001', 'Male', 40);
        $this->insertLocal('1002', 'Female', 10);
        $this->insertLocal('3001', 'Male', 20);
        $this->insertForeign('3001', 'Female', 9);
        $this->insertForeign('2001', 'Male', 15);
        $this->insertForeign('2002', 'F', 8);
        $this->insertLocal('1006', 'Male', 5);

        $first = $this->insertSharaf($gateId, 'confirmed', '1001');
        $this->insertMember($first, '1001');
        $this->insertMember($first, '1002');

        $second = $this->insertSharaf($gateId, 'confirmed', '1001');
        $this->insertMember($second, '1001');
        $this->insertMember($second, '3001');
        $this->insertMember($second, '2002');
        $this->insertMember($second, '9999');

        $cancelled = $this->insertSharaf($gateId, 'cancelled', '1006');
        $this->insertMember($cancelled, '1006');

        $parking = $this->insertSharaf($parkingId, 'pending', '2001');
        $this->insertMember($parking, '2001');
        $this->insertMember($parking, '2002');

        $this->insertUser('10000001', 'FMB');

        $response = $this->asUser('10000001')->getJson("/api/miqaats/{$miqaatId}/fmb-overview");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.miqaat_name', 'Ashara')
            ->assertJsonPath('data.overall.event_count', 2)
            ->assertJsonPath('data.overall.definition_count', 3)
            ->assertJsonPath('data.overall.total_sharafs', 4)
            ->assertJsonPath('data.overall.confirmed_sharafs', 2)
            ->assertJsonPath('data.overall.pending_sharafs', 1)
            ->assertJsonPath('data.overall.cancelled_sharafs', 1)
            ->assertJsonPath('data.overall.food.local.total', 3)
            ->assertJsonPath('data.overall.food.local.male', 2)
            ->assertJsonPath('data.overall.food.local.female', 1)
            ->assertJsonPath('data.overall.food.local.below_15', 1)
            ->assertJsonPath('data.overall.food.foreign.total', 2)
            ->assertJsonPath('data.overall.food.foreign.male', 1)
            ->assertJsonPath('data.overall.food.foreign.female', 1)
            ->assertJsonPath('data.overall.food.foreign.below_15', 1)
            ->assertJsonPath('data.overall.food.unlisted.total', 1);

        $events = $response->json('data.events');
        $this->assertSame('Day 1', $events[0]['event_name']);
        $this->assertSame(3, $events[0]['food']['local']['total']);
        $this->assertSame(2, $events[0]['food']['local']['male']);
        $this->assertSame(1, $events[0]['food']['local']['female']);
        $this->assertSame(1, $events[0]['food']['local']['below_15']);
        $this->assertSame(1, $events[0]['food']['foreign']['total']);
        $this->assertSame(1, $events[0]['food']['foreign']['female']);
        $this->assertSame(1, $events[0]['food']['foreign']['below_15']);
        $this->assertSame(1, $events[0]['food']['unlisted']['total']);

        $gate = $events[0]['definitions'][0];
        $this->assertSame('Gate', $gate['sharaf_definition_name']);
        $this->assertSame('Khidmat', $gate['sharaf_type_name']);
        $this->assertSame(3, $gate['total_sharafs']);
        $this->assertSame(2, $gate['confirmed_sharafs']);
        $this->assertSame(1, $gate['cancelled_sharafs']);
        $this->assertSame(6, $gate['total_members']);
        $this->assertSame(3, $gate['male_count']);
        $this->assertSame(2, $gate['female_count']);
        $this->assertSame(1, $gate['unknown_gender_count']);
        $this->assertArrayNotHasKey('sharafs', $gate);

        $this->assertSame($kitchenId, $events[0]['definitions'][1]['sharaf_definition_id']);
        $this->assertSame(0, $events[0]['definitions'][1]['total_sharafs']);
        $this->assertSame(0, $events[0]['definitions'][1]['total_members']);

        $this->assertSame('Parking', $events[1]['definitions'][0]['sharaf_definition_name']);
        $this->assertSame(2, $events[1]['food']['foreign']['total']);
        $this->assertSame(1, $events[1]['food']['foreign']['male']);
        $this->assertSame(1, $events[1]['food']['foreign']['below_15']);
        $this->assertSame(0, $events[1]['food']['local']['total']);
    }

    public function test_overview_requires_fmb_or_admin_role(): void
    {
        $miqaatId = $this->insertMiqaat('Ashara');
        $this->insertUser('20000004', 'Help Desk');

        $this->asUser('20000004')->getJson("/api/miqaats/{$miqaatId}/fmb-overview")
            ->assertForbidden();

        $this->insertUser('20000005', 'Admin');
        $this->asUser('20000005')->getJson("/api/miqaats/{$miqaatId}/fmb-overview")
            ->assertOk()
            ->assertJsonPath('data.overall.definition_count', 0);
    }

    public function test_overview_requires_a_session(): void
    {
        $miqaatId = $this->insertMiqaat('Ashara');

        $this->getJson("/api/miqaats/{$miqaatId}/fmb-overview")
            ->assertUnauthorized();
    }

    public function test_overview_returns_not_found_for_a_missing_miqaat(): void
    {
        $this->insertUser('10000001', 'FMB');

        $this->asUser('10000001')->getJson('/api/miqaats/999/fmb-overview')
            ->assertNotFound();
    }

    private function asUser(string $its)
    {
        return $this->withCredentials()->withUnencryptedCookie('user', $its);
    }

    private function insertUser(string $its, string $role): void
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
    }

    private function insertMiqaat(string $name): int
    {
        return DB::table('miqaats')->insertGetId([
            'name' => $name,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-10',
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

    private function insertSharaf(int $definitionId, string $status, string $hofIts): int
    {
        return DB::table('sharafs')->insertGetId([
            'sharaf_definition_id' => $definitionId,
            'rank' => 1,
            'status' => $status,
            'hof_its' => $hofIts,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertMember(int $sharafId, string $itsId): void
    {
        DB::table('sharaf_members')->insert([
            'sharaf_id' => $sharafId,
            'its_id' => $itsId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertLocal(string $itsId, string $gender, ?int $age): void
    {
        DB::table('census')->insert([
            'its_id' => $itsId,
            'gender' => $gender,
            'age' => $age,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertForeign(string $itsId, string $gender, ?int $age): void
    {
        DB::table('foreign_census')->insert([
            'its_id' => $itsId,
            'gender' => $gender,
            'age' => $age,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
