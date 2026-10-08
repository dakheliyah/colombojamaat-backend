<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SharafHofNameFallbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('sharaf_payments');
        Schema::dropIfExists('sharaf_members');
        Schema::dropIfExists('sharafs');
        Schema::dropIfExists('payment_definitions');
        Schema::dropIfExists('sharaf_definitions');
        Schema::dropIfExists('event_color_legends');
        Schema::dropIfExists('events');
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
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('miqaat_id');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('event_color_legends', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->string('label')->nullable();
            $table->timestamps();
        });

        Schema::create('sharaf_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->string('name');
            $table->integer('sort_order')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_definition_id');
            $table->string('name');
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
            $table->unsignedBigInteger('color_legend_id')->nullable();
            $table->timestamps();
        });

        Schema::create('sharaf_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_id');
            $table->string('its_id');
            $table->string('name')->nullable();
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
        Schema::dropIfExists('sharaf_members');
        Schema::dropIfExists('sharafs');
        Schema::dropIfExists('payment_definitions');
        Schema::dropIfExists('sharaf_definitions');
        Schema::dropIfExists('event_color_legends');
        Schema::dropIfExists('events');
        Schema::dropIfExists('census');
        Schema::dropIfExists('miqaats');
        parent::tearDown();
    }

    public function test_list_uses_sharaf_member_name_when_census_is_missing(): void
    {
        $ids = $this->seedSharaf('30304640', 'Member From Sharaf');

        $this->getJson("/sharafs?miqaat_id={$ids['miqaat']}")
            ->assertOk()
            ->assertJsonPath('data.0.hof_its', '30304640')
            ->assertJsonPath('data.0.hof_name', 'Member From Sharaf');
    }

    public function test_list_prefers_census_name_over_sharaf_member_name(): void
    {
        $ids = $this->seedSharaf('30304640', 'Member From Sharaf');

        DB::table('census')->insert([
            'its_id' => '30304640',
            'name' => 'Census Name',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson("/sharafs?miqaat_id={$ids['miqaat']}")
            ->assertOk()
            ->assertJsonPath('data.0.hof_name', 'Census Name');
    }

    /**
     * @return array{miqaat: int, sharaf: int}
     */
    protected function seedSharaf(string $itsId, string $memberName): array
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
            'capacity' => 1,
            'status' => 'pending',
            'hof_its' => $itsId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sharaf_members')->insert([
            'sharaf_id' => $sharafId,
            'its_id' => $itsId,
            'name' => $memberName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'miqaat' => (int) $miqaatId,
            'sharaf' => (int) $sharafId,
        ];
    }
}
