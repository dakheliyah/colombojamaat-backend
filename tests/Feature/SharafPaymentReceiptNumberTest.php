<?php

namespace Tests\Feature;

use App\Services\SharafReceiptNumberService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SharafPaymentReceiptNumberTest extends TestCase
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
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedInteger('last_receipt_no')->default(0);
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
            $table->timestamps();
        });

        Schema::create('sharafs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_definition_id');
            $table->integer('rank')->default(0);
            $table->integer('capacity')->default(1);
            $table->string('status')->default('pending');
            $table->string('hof_its');
            $table->unsignedInteger('receipt_no')->nullable();
            $table->timestamp('receipt_issued_at')->nullable();
            $table->timestamps();
        });

        DB::table('miqaats')->insert([
            ['id' => 1, 'name' => 'Ashara 2026', 'last_receipt_no' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Ashara 2027', 'last_receipt_no' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('events')->insert([
            ['id' => 1, 'miqaat_id' => 1, 'name' => 'Day 1', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'miqaat_id' => 2, 'name' => 'Day 1', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('sharaf_definitions')->insert([
            ['id' => 1, 'event_id' => 1, 'name' => 'Raza', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'event_id' => 2, 'name' => 'Raza', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('sharafs')->insert([
            ['id' => 11, 'sharaf_definition_id' => 1, 'hof_its' => '30401234', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 12, 'sharaf_definition_id' => 1, 'hof_its' => '30405678', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 20, 'sharaf_definition_id' => 2, 'hof_its' => '30409999', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_receipt_numbers_start_at_one_for_each_miqaat_and_follow_print_order(): void
    {
        $service = app(SharafReceiptNumberService::class);

        $issued = $service->issue([12, 11, 20]);

        $this->assertSame([
            ['sharaf_id' => 12, 'receipt_no' => 1],
            ['sharaf_id' => 11, 'receipt_no' => 2],
            ['sharaf_id' => 20, 'receipt_no' => 1],
        ], array_map(fn (array $row) => [
            'sharaf_id' => $row['sharaf_id'],
            'receipt_no' => $row['receipt_no'],
        ], $issued));

        $this->assertSame(2, (int) DB::table('miqaats')->where('id', 1)->value('last_receipt_no'));
        $this->assertSame(1, (int) DB::table('miqaats')->where('id', 2)->value('last_receipt_no'));
    }

    public function test_reprint_keeps_the_original_number_and_issued_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-09 11:54:00'));
        $service = app(SharafReceiptNumberService::class);

        $first = $service->issue([11]);

        Carbon::setTestNow(Carbon::parse('2026-10-10 09:00:00'));
        $again = $service->issue([12, 11]);

        $this->assertSame(1, $first[0]['receipt_no']);
        $this->assertSame($first[0]['receipt_issued_at'], $again[1]['receipt_issued_at']);
        $this->assertSame(1, $again[1]['receipt_no']);
        $this->assertSame(2, $again[0]['receipt_no']);
        $this->assertNotSame($again[0]['receipt_issued_at'], $again[1]['receipt_issued_at']);
    }
};
