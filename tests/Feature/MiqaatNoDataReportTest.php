<?php

namespace Tests\Feature;

use App\Services\ReportingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MiqaatNoDataReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'wajebaat',
            'sharaf_members',
            'sharafs',
            'sharaf_definitions',
            'events',
            'miqaats',
            'census',
        ] as $table) {
            Schema::dropIfExists($table);
        }

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
            $table->string('status')->default('pending');
            $table->string('hof_its')->nullable();
        });

        Schema::create('sharaf_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_id');
            $table->string('its_id');
        });

        Schema::create('census', function (Blueprint $table) {
            $table->id();
            $table->string('its_id');
            $table->string('name')->nullable();
        });

        Schema::create('wajebaat', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('miqaat_id');
            $table->string('its_id');
        });
    }

    protected function tearDown(): void
    {
        foreach ([
            'wajebaat',
            'sharaf_members',
            'sharafs',
            'sharaf_definitions',
            'events',
            'miqaats',
            'census',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_lists_miqaat_its_missing_from_census_and_skips_known_and_cancelled(): void
    {
        $miqaatId = DB::table('miqaats')->insertGetId(['name' => 'Ashara']);
        $otherMiqaatId = DB::table('miqaats')->insertGetId(['name' => 'Other']);
        $eventId = DB::table('events')->insertGetId(['miqaat_id' => $miqaatId]);
        $otherEventId = DB::table('events')->insertGetId(['miqaat_id' => $otherMiqaatId]);
        $definitionId = DB::table('sharaf_definitions')->insertGetId([
            'event_id' => $eventId,
            'name' => 'Ziyafat',
        ]);
        $otherDefinitionId = DB::table('sharaf_definitions')->insertGetId([
            'event_id' => $otherEventId,
            'name' => 'Other',
        ]);

        DB::table('census')->insert([
            ['its_id' => '11111111', 'name' => 'Known'],
        ]);

        $live = DB::table('sharafs')->insertGetId([
            'sharaf_definition_id' => $definitionId,
            'status' => 'pending',
            'hof_its' => '22222222',
        ]);
        $cancelled = DB::table('sharafs')->insertGetId([
            'sharaf_definition_id' => $definitionId,
            'status' => 'cancelled',
            'hof_its' => '33333333',
        ]);
        $other = DB::table('sharafs')->insertGetId([
            'sharaf_definition_id' => $otherDefinitionId,
            'status' => 'pending',
            'hof_its' => '55555555',
        ]);

        DB::table('sharaf_members')->insert([
            ['sharaf_id' => $live, 'its_id' => '11111111'],
            ['sharaf_id' => $live, 'its_id' => '22222222'],
            ['sharaf_id' => $cancelled, 'its_id' => '33333333'],
            ['sharaf_id' => $other, 'its_id' => '55555555'],
        ]);

        DB::table('wajebaat')->insert([
            ['miqaat_id' => $miqaatId, 'its_id' => '11111111'],
            ['miqaat_id' => $miqaatId, 'its_id' => '44444444'],
        ]);

        $report = app(ReportingService::class)->miqaatItsWithoutCensus($miqaatId);

        $this->assertSame(['22222222', '44444444'], $report['its_numbers']);
        $this->assertSame(2, $report['count']);

        $byIts = collect($report['rows'])->keyBy('its_id');
        $this->assertEqualsCanonicalizing(['sharaf_hof', 'sharaf_member'], $byIts['22222222']['sources']);
        $this->assertSame(['wajebaat'], $byIts['44444444']['sources']);

        $response = $this->getJson('/api/reports/miqaat-no-data?miqaat_id=' . $miqaatId);
        $response->assertOk();
        $response->assertJsonPath('data.its_numbers', ['22222222', '44444444']);
    }
}
