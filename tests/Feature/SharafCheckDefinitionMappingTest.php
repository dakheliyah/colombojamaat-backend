<?php

namespace Tests\Feature;

use App\Models\SharafCheck;
use App\Services\SharafDefinitionMappingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SharafCheckDefinitionMappingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'sharaf_shift_audit_logs',
            'sharaf_check_definition_mappings',
            'payment_definition_mappings',
            'sharaf_position_mappings',
            'sharaf_definition_mappings',
            'sharaf_checks',
            'sharaf_check_definitions',
            'sharaf_payments',
            'sharaf_members',
            'sharaf_clearances',
            'sharafs',
            'sharaf_definitions',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('sharaf_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('sort_order')->nullable();
            $table->timestamps();
        });

        Schema::create('sharafs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_definition_id');
            $table->integer('rank')->default(1);
            $table->integer('capacity')->default(1);
            $table->string('status')->default('pending');
            $table->string('hof_its');
            $table->timestamps();
        });

        Schema::create('sharaf_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_id');
            $table->unsignedBigInteger('sharaf_position_id')->nullable();
            $table->timestamps();
        });

        Schema::create('sharaf_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_id');
            $table->unsignedBigInteger('payment_definition_id');
            $table->timestamps();
        });

        Schema::create('sharaf_clearances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_id');
            $table->timestamps();
        });

        Schema::create('sharaf_check_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_definition_id');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('sharaf_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_id');
            $table->unsignedBigInteger('sharaf_check_definition_id');
            $table->boolean('is_done')->default(false);
            $table->timestamps();
        });

        Schema::create('sharaf_definition_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_sharaf_definition_id');
            $table->unsignedBigInteger('target_sharaf_definition_id');
            $table->boolean('is_active')->default(true);
            $table->string('created_by_its')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('sharaf_position_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_definition_mapping_id');
            $table->unsignedBigInteger('source_sharaf_position_id');
            $table->unsignedBigInteger('target_sharaf_position_id');
            $table->timestamps();
        });

        Schema::create('payment_definition_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_definition_mapping_id');
            $table->unsignedBigInteger('source_payment_definition_id');
            $table->unsignedBigInteger('target_payment_definition_id');
            $table->timestamps();
        });

        Schema::create('sharaf_check_definition_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_definition_mapping_id');
            $table->unsignedBigInteger('source_sharaf_check_definition_id');
            $table->unsignedBigInteger('target_sharaf_check_definition_id');
            $table->timestamps();
        });

        Schema::create('sharaf_shift_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_definition_mapping_id');
            $table->string('shifted_by_its')->nullable();
            $table->json('shift_summary')->nullable();
            $table->json('sharaf_ids')->nullable();
            $table->json('position_mappings_used')->nullable();
            $table->json('payment_mappings_used')->nullable();
            $table->json('check_mappings_used')->nullable();
            $table->json('rank_changes')->nullable();
            $table->timestamp('shifted_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        foreach ([
            'sharaf_shift_audit_logs',
            'sharaf_check_definition_mappings',
            'payment_definition_mappings',
            'sharaf_position_mappings',
            'sharaf_definition_mappings',
            'sharaf_checks',
            'sharaf_check_definitions',
            'sharaf_payments',
            'sharaf_members',
            'sharaf_clearances',
            'sharafs',
            'sharaf_definitions',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_shift_rewrites_recorded_checks_to_the_target_definition(): void
    {
        $sourceDefinitionId = DB::table('sharaf_definitions')->insertGetId([
            'name' => 'Source',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $targetDefinitionId = DB::table('sharaf_definitions')->insertGetId([
            'name' => 'Target',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sourceCheckId = DB::table('sharaf_check_definitions')->insertGetId([
            'sharaf_definition_id' => $sourceDefinitionId,
            'name' => 'Passport',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $targetCheckId = DB::table('sharaf_check_definitions')->insertGetId([
            'sharaf_definition_id' => $targetDefinitionId,
            'name' => 'Passport',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sharafId = DB::table('sharafs')->insertGetId([
            'sharaf_definition_id' => $sourceDefinitionId,
            'rank' => 1,
            'hof_its' => '30304640',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $checkRowId = DB::table('sharaf_checks')->insertGetId([
            'sharaf_id' => $sharafId,
            'sharaf_check_definition_id' => $sourceCheckId,
            'is_done' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mappingId = DB::table('sharaf_definition_mappings')->insertGetId([
            'source_sharaf_definition_id' => $sourceDefinitionId,
            'target_sharaf_definition_id' => $targetDefinitionId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(SharafDefinitionMappingService::class);

        $incomplete = $service->validateMappingComplete($mappingId);
        $this->assertFalse($incomplete['is_complete']);
        $this->assertSame($sourceCheckId, $incomplete['missing_check_mappings'][0]['sharaf_check_definition_id']);

        $service->addCheckDefinitionMapping($mappingId, $sourceCheckId, $targetCheckId);

        $complete = $service->validateMappingComplete($mappingId);
        $this->assertTrue($complete['is_complete']);

        $result = $service->shiftSharafs($mappingId);
        $this->assertSame(1, $result['checks_shifted']);
        $this->assertSame($targetCheckId, SharafCheck::find($checkRowId)->sharaf_check_definition_id);
        $this->assertSame($targetDefinitionId, (int) DB::table('sharafs')->where('id', $sharafId)->value('sharaf_definition_id'));
    }
}
