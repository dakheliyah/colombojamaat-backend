<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ItsClearanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('sharaf_payments');
        Schema::dropIfExists('payment_definitions');
        Schema::dropIfExists('sharafs');
        Schema::dropIfExists('sharaf_definitions');
        Schema::dropIfExists('events');
        Schema::dropIfExists('miqaat_checks');
        Schema::dropIfExists('miqaat_check_definitions');
        Schema::dropIfExists('wajebaat_groups');
        Schema::dropIfExists('wajebaat');
        Schema::dropIfExists('census');
        Schema::dropIfExists('miqaats');

        Schema::create('miqaats', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('active_status')->default(false);
            $table->boolean('archived')->default(false);
            $table->timestamps();
        });

        Schema::create('census', function (Blueprint $table) {
            $table->id();
            $table->string('its_id')->unique();
            $table->string('hof_id')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('wajebaat', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('miqaat_id');
            $table->string('its_id');
            $table->timestamps();
        });

        Schema::create('wajebaat_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wg_id');
            $table->unsignedBigInteger('miqaat_id');
            $table->string('master_its')->nullable();
            $table->string('its_id')->nullable();
            $table->timestamps();
        });

        Schema::create('miqaat_check_definitions', function (Blueprint $table) {
            $table->id('mcd_id');
            $table->unsignedBigInteger('miqaat_id');
            $table->string('name');
            $table->string('user_type')->nullable();
            $table->timestamps();
        });

        Schema::create('miqaat_checks', function (Blueprint $table) {
            $table->id();
            $table->string('its_id');
            $table->unsignedBigInteger('mcd_id');
            $table->boolean('is_cleared')->default(false);
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
            $table->integer('rank')->default(0);
            $table->string('name')->nullable();
            $table->integer('capacity')->default(1);
            $table->string('status')->default('pending');
            $table->string('hof_its');
            $table->timestamps();
        });

        Schema::create('payment_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_definition_id');
            $table->string('name');
            $table->string('user_type')->nullable();
            $table->timestamps();
        });

        Schema::create('sharaf_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_id');
            $table->unsignedBigInteger('payment_definition_id');
            $table->boolean('payment_status')->default(false);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('sharaf_payments');
        Schema::dropIfExists('payment_definitions');
        Schema::dropIfExists('sharafs');
        Schema::dropIfExists('sharaf_definitions');
        Schema::dropIfExists('events');
        Schema::dropIfExists('miqaat_checks');
        Schema::dropIfExists('miqaat_check_definitions');
        Schema::dropIfExists('wajebaat_groups');
        Schema::dropIfExists('wajebaat');
        Schema::dropIfExists('census');
        Schema::dropIfExists('miqaats');
        parent::tearDown();
    }

    public function test_clearance_checks_hof_departments_and_its_payments(): void
    {
        $ids = $this->seedUnclearedMember();

        $this->getJson("/miqaats/{$ids['miqaat']}/clearance/111")
            ->assertOk()
            ->assertJsonPath('data.hof_its_id', '222')
            ->assertJsonPath('data.its_id', '111')
            ->assertJsonPath('data.can_mark_paid', false)
            ->assertJsonPath('data.payments_cleared', false)
            ->assertJsonPath('data.is_cleared', false)
            ->assertJsonPath('data.pending_departments.0.mcd_id', $ids['mcd'])
            ->assertJsonPath('data.pending_departments.0.name', 'Anjuman Clearance')
            ->assertJsonPath('data.pending_payments.0.payment_definition_id', $ids['payment'])
            ->assertJsonPath('data.pending_payments.0.name', 'Dulha Lagat')
            ->assertJsonPath('data.pending_payments.0.sharaf_id', $ids['sharaf']);
    }

    public function test_clearance_is_clear_when_hof_check_and_payment_are_done(): void
    {
        $ids = $this->seedUnclearedMember();

        DB::table('miqaat_checks')->insert([
            'its_id' => '222',
            'mcd_id' => $ids['mcd'],
            'is_cleared' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('sharaf_payments')->insert([
            'sharaf_id' => $ids['sharaf'],
            'payment_definition_id' => $ids['payment'],
            'payment_status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson("/miqaats/{$ids['miqaat']}/clearance/111")
            ->assertOk()
            ->assertJsonPath('data.can_mark_paid', true)
            ->assertJsonPath('data.payments_cleared', true)
            ->assertJsonPath('data.is_cleared', true)
            ->assertJsonPath('data.pending_departments', [])
            ->assertJsonPath('data.pending_payments', []);
    }

    public function test_bulk_clearance_matches_single_requests(): void
    {
        $ids = $this->seedUnclearedMember();

        DB::table('census')->insert([
            'its_id' => '333',
            'hof_id' => '333',
            'name' => 'Other',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $single = $this->getJson("/miqaats/{$ids['miqaat']}/clearance/111")
            ->assertOk()
            ->json('data');

        $bulk = $this->postJson("/api/miqaats/{$ids['miqaat']}/clearance", [
            'its_ids' => ['111', '333', '999'],
        ])->assertOk()->json('data');

        $this->assertEquals($single, $bulk['111']);
        $this->assertSame('333', $bulk['333']['its_id']);
        $this->assertFalse($bulk['333']['can_mark_paid']);
        $this->assertTrue($bulk['333']['payments_cleared']);
        $this->assertSame('999', $bulk['999']['its_id']);
        $this->assertTrue($bulk['999']['can_mark_paid']);
        $this->assertSame([], $bulk['999']['pending_departments']);
    }

    /**
     * @return array{miqaat: int, mcd: int, payment: int, sharaf: int}
     */
    protected function seedUnclearedMember(): array
    {
        $miqaatId = DB::table('miqaats')->insertGetId([
            'name' => 'Test Miqaat',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-02',
            'active_status' => true,
            'archived' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('census')->insert([
            ['its_id' => '111', 'hof_id' => '222', 'name' => 'Member', 'created_at' => now(), 'updated_at' => now()],
            ['its_id' => '222', 'hof_id' => '222', 'name' => 'HoF', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $mcdId = DB::table('miqaat_check_definitions')->insertGetId([
            'miqaat_id' => $miqaatId,
            'name' => 'Anjuman Clearance',
            'user_type' => 'Anjuman',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $eventId = DB::table('events')->insertGetId([
            'miqaat_id' => $miqaatId,
            'name' => 'Nikah',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $definitionId = DB::table('sharaf_definitions')->insertGetId([
            'event_id' => $eventId,
            'name' => 'Nikah',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sharafId = DB::table('sharafs')->insertGetId([
            'sharaf_definition_id' => $definitionId,
            'rank' => 1,
            'name' => 'Member',
            'capacity' => 2,
            'status' => 'confirmed',
            'hof_its' => '111',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $paymentId = DB::table('payment_definitions')->insertGetId([
            'sharaf_definition_id' => $definitionId,
            'name' => 'Dulha Lagat',
            'user_type' => 'Anjuman',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'miqaat' => (int) $miqaatId,
            'mcd' => (int) $mcdId,
            'payment' => (int) $paymentId,
            'sharaf' => (int) $sharafId,
        ];
    }
}
