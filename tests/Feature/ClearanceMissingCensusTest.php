<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClearanceMissingCensusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('wajebaat');
        Schema::dropIfExists('census');
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

        Schema::create('census', function (Blueprint $table) {
            $table->id();
            $table->string('its_id')->unique();
            $table->string('hof_id')->nullable();
            $table->timestamps();
        });

        Schema::create('wajebaat', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('miqaat_id');
            $table->string('its_id');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('wajebaat');
        Schema::dropIfExists('census');
        Schema::dropIfExists('miqaats');
        parent::tearDown();
    }

    public function test_missing_census_record_is_cleared(): void
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

        $this->getJson("/miqaats/{$miqaatId}/wajebaat/99999999/clearance")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.hof_its_id', '99999999')
            ->assertJsonPath('data.can_mark_paid', true)
            ->assertJsonPath('data.pending_departments', [])
            ->assertJsonPath('data.wajebaat', null)
            ->assertJsonPath('data.hof_clearance_status.0.can_mark_paid', true)
            ->assertJsonPath('data.hof_clearance_status.0.pending_departments', []);
    }
}
