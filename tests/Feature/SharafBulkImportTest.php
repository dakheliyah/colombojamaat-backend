<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SharafBulkImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('sharaf_payments');
        Schema::dropIfExists('sharaf_members');
        Schema::dropIfExists('sharafs');
        Schema::dropIfExists('payment_definitions');
        Schema::dropIfExists('sharaf_positions');
        Schema::dropIfExists('sharaf_definitions');
        Schema::dropIfExists('events');
        Schema::dropIfExists('census');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('miqaats');

        Schema::create('miqaats', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->timestamps();
        });

        Schema::create('census', function (Blueprint $table) {
            $table->id();
            $table->string('its_id')->unique();
            $table->string('name')->nullable();
            $table->string('mobile')->nullable();
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('miqaat_id');
            $table->date('date')->nullable();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('sharaf_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->string('name');
            $table->integer('sort_order')->nullable();
            $table->integer('default_capacity')->nullable();
            $table->timestamps();
        });

        Schema::create('sharaf_positions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_definition_id');
            $table->string('name');
            $table->string('display_name');
            $table->integer('capacity')->nullable();
            $table->integer('order');
            $table->timestamps();
        });

        Schema::create('payment_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_definition_id');
            $table->string('name');
            $table->decimal('default_amount', 10, 2)->nullable();
            $table->string('default_currency', 3)->nullable();
            $table->timestamps();
        });

        Schema::create('sharafs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_definition_id');
            $table->integer('rank');
            $table->string('name')->nullable();
            $table->integer('capacity');
            $table->string('status')->default('pending');
            $table->string('hof_its');
            $table->string('token', 50)->nullable();
            $table->text('comments')->nullable();
            $table->timestamps();
        });

        Schema::create('sharaf_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_id');
            $table->unsignedBigInteger('sharaf_position_id');
            $table->string('its_id');
            $table->integer('sp_keyno')->nullable();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('najwa')->nullable();
            $table->boolean('on_vms')->default(false);
            $table->timestamps();
        });

        Schema::create('sharaf_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_id');
            $table->unsignedBigInteger('payment_definition_id');
            $table->decimal('payment_amount', 10, 2)->default(0);
            $table->boolean('payment_status')->default(false);
            $table->string('payment_currency', 3)->default('LKR');
            $table->timestamps();
        });

        DB::table('currencies')->insert([
            ['code' => 'LKR', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'USD', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('sharaf_payments');
        Schema::dropIfExists('sharaf_members');
        Schema::dropIfExists('sharafs');
        Schema::dropIfExists('payment_definitions');
        Schema::dropIfExists('sharaf_positions');
        Schema::dropIfExists('sharaf_definitions');
        Schema::dropIfExists('events');
        Schema::dropIfExists('census');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('miqaats');

        parent::tearDown();
    }

    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        // APP_URL includes /api. After the first request Laravel adopts the host root and drops that prefix.
        URL::forceRootUrl(config('app.url'));

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    public function test_columns_and_template_follow_the_definition(): void
    {
        $ids = $this->seedDefinition();

        $columns = $this->getJson('/sharafs/import/columns?sharaf_definition_id='.$ids['definition'])
            ->assertOk()
            ->json('data.columns');

        $headers = array_column($columns, 'header');
        $this->assertContains('capacity', $headers);
        $this->assertContains('Head of Family 1 ITS', $headers);
        $this->assertContains('Family Member 1 ITS', $headers);
        $this->assertContains('Family Member 2 ITS', $headers);
        $this->assertContains('Lagat Amount', $headers);
        $this->assertContains('Lagat Currency', $headers);

        $required = array_values(array_filter($columns, fn ($column) => $column['required']));
        $requiredHeaders = array_column($required, 'header');
        $this->assertEqualsCanonicalizing(['capacity', 'Head of Family 1 ITS'], $requiredHeaders);

        $csv = $this->get('/sharafs/import/template?sharaf_definition_id='.$ids['definition'])
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Head of Family 1 ITS', $csv);
        $this->assertStringContainsString('12345678', $csv);
        $this->assertStringContainsString('Replace this example row', $csv);
    }

    public function test_validate_reports_row_errors_and_does_not_import_them(): void
    {
        $ids = $this->seedDefinition();
        $headers = $this->headers($ids['definition']);

        $file = $this->csvFile($headers, [
            $this->row($headers, [
                'capacity' => '',
                'Head of Family 1 ITS' => '30304640',
            ]),
            $this->row($headers, [
                'capacity' => '4',
                'Head of Family 1 ITS' => '30304640',
                'token' => 'A1',
            ]),
            $this->row($headers, [
                'capacity' => '4',
                'Head of Family 1 ITS' => '30304640',
                'token' => 'A1',
            ]),
        ]);

        $response = $this->post('/sharafs/import/validate', [
            'sharaf_definition_id' => $ids['definition'],
            'csv_file' => $file,
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertFalse($data['can_import']);
        $this->assertSame(3, $data['row_count']);
        $this->assertGreaterThan(0, $data['error_count']);
        $this->assertStringContainsString('Capacity', $data['rows'][0]['errors'][0]['message']);
        $this->assertNotEmpty($data['rows'][2]['errors']);
        $this->assertSame(0, DB::table('sharafs')->count());

        $import = $this->post('/sharafs/import', [
            'sharaf_definition_id' => $ids['definition'],
            'csv_file' => $this->csvFile($headers, [
                $this->row($headers, [
                    'capacity' => '4',
                    'Head of Family 1 ITS' => '',
                ]),
            ]),
        ], ['Accept' => 'application/json']);

        $import->assertStatus(422);
        $this->assertFalse($import->json('data.can_import'));
        $this->assertSame(0, DB::table('sharafs')->count());
    }

    public function test_import_creates_sharafs_members_and_payments(): void
    {
        $ids = $this->seedDefinition();
        DB::table('census')->insert([
            'its_id' => '30304640',
            'name' => 'Census HOF',
            'mobile' => '0771111111',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $headers = $this->headers($ids['definition']);
        $file = $this->csvFile($headers, [
            $this->row($headers, [
                'capacity' => '4',
                'token' => 'T1',
                'comments' => 'First',
                'Head of Family 1 ITS' => '30304640',
                'Family Member 1 ITS' => '30304641',
                'Family Member 1 Name' => 'Member One',
                'Lagat Amount' => '1500',
                'Lagat Currency' => 'LKR',
            ]),
            $this->row($headers, [
                'capacity' => '2',
                'token' => 'T2',
                'Head of Family 1 ITS' => '30304642',
                'Head of Family 1 Name' => 'Second HOF',
            ]),
        ]);

        $validated = $this->post('/sharafs/import/validate', [
            'sharaf_definition_id' => $ids['definition'],
            'csv_file' => $file,
        ], ['Accept' => 'application/json']);

        $validated->assertOk();
        $this->assertTrue($validated->json('data.can_import'));
        $this->assertSame('Census HOF', $validated->json('data.rows.0.hof_name'));
        $this->assertSame('1500.00', $validated->json('data.rows.0.payments.0.amount'));

        $imported = $this->post('/sharafs/import', [
            'sharaf_definition_id' => $ids['definition'],
            'csv_file' => $file,
        ], ['Accept' => 'application/json']);

        $imported->assertCreated();
        $this->assertSame(2, $imported->json('data.imported'));

        $first = DB::table('sharafs')->where('token', 'T1')->first();
        $this->assertNotNull($first);
        $this->assertSame('30304640', $first->hof_its);
        $this->assertSame('Census HOF', $first->name);
        $this->assertSame(4, (int) $first->capacity);
        $this->assertSame('confirmed', $first->status);
        $this->assertSame(1, (int) $first->rank);
        $this->assertSame('First', $first->comments);

        $this->assertDatabaseHas('sharaf_members', [
            'sharaf_id' => $first->id,
            'its_id' => '30304640',
            'sp_keyno' => 1,
            'name' => 'Census HOF',
            'phone' => '0771111111',
        ]);
        $this->assertDatabaseHas('sharaf_members', [
            'sharaf_id' => $first->id,
            'sharaf_position_id' => $ids['member_position'],
            'its_id' => '30304641',
            'name' => 'Member One',
        ]);
        $this->assertDatabaseHas('sharaf_payments', [
            'sharaf_id' => $first->id,
            'payment_definition_id' => $ids['payment'],
            'payment_currency' => 'LKR',
        ]);

        $second = DB::table('sharafs')->where('token', 'T2')->first();
        $this->assertSame(2, (int) $second->rank);
        $this->assertSame('Second HOF', $second->name);
    }

    public function test_same_miqaat_hof_is_a_warning_and_can_still_import(): void
    {
        $ids = $this->seedDefinition();
        $otherDefinition = DB::table('sharaf_definitions')->insertGetId([
            'event_id' => $ids['event'],
            'name' => 'Other Sharaf',
            'sort_order' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('sharafs')->insert([
            'sharaf_definition_id' => $otherDefinition,
            'rank' => 1,
            'capacity' => 2,
            'status' => 'confirmed',
            'hof_its' => '30304640',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $headers = $this->headers($ids['definition']);
        $file = $this->csvFile($headers, [
            $this->row($headers, [
                'capacity' => '2',
                'Head of Family 1 ITS' => '30304640',
                'Head of Family 1 Name' => 'Already Elsewhere',
            ]),
        ]);

        $validated = $this->post('/sharafs/import/validate', [
            'sharaf_definition_id' => $ids['definition'],
            'csv_file' => $file,
        ], ['Accept' => 'application/json']);

        $validated->assertOk();
        $this->assertTrue($validated->json('data.can_import'));
        $messages = array_column($validated->json('data.rows.0.warnings'), 'message');
        $this->assertTrue(
            collect($messages)->contains(fn ($message) => str_contains($message, 'Other Sharaf')),
            'Expected a same-miqaat warning. Got: '.implode(' | ', $messages)
        );

        $this->post('/sharafs/import', [
            'sharaf_definition_id' => $ids['definition'],
            'csv_file' => $file,
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame(2, DB::table('sharafs')->count());
    }

    public function test_duplicate_hof_in_the_same_definition_is_an_error(): void
    {
        $ids = $this->seedDefinition();
        DB::table('sharafs')->insert([
            'sharaf_definition_id' => $ids['definition'],
            'rank' => 3,
            'capacity' => 2,
            'status' => 'confirmed',
            'hof_its' => '30304640',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $headers = $this->headers($ids['definition']);
        $response = $this->post('/sharafs/import/validate', [
            'sharaf_definition_id' => $ids['definition'],
            'csv_file' => $this->csvFile($headers, [
                $this->row($headers, [
                    'capacity' => '2',
                    'Head of Family 1 ITS' => '30304640',
                    'Head of Family 1 Name' => 'Taken',
                ]),
            ]),
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertFalse($response->json('data.can_import'));
        $this->assertStringContainsString('already assigned', $response->json('data.rows.0.errors.0.message'));
    }

    /**
     * @return array{miqaat: int, event: int, definition: int, hof_position: int, member_position: int, payment: int}
     */
    private function seedDefinition(): array
    {
        $miqaat = DB::table('miqaats')->insertGetId([
            'name' => 'Test Miqaat',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $event = DB::table('events')->insertGetId([
            'miqaat_id' => $miqaat,
            'date' => '2026-10-10',
            'name' => 'Ashara',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $definition = DB::table('sharaf_definitions')->insertGetId([
            'event_id' => $event,
            'name' => 'FMB',
            'sort_order' => 1,
            'default_capacity' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $hof = DB::table('sharaf_positions')->insertGetId([
            'sharaf_definition_id' => $definition,
            'name' => 'HOF',
            'display_name' => 'Head of Family',
            'capacity' => 1,
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $member = DB::table('sharaf_positions')->insertGetId([
            'sharaf_definition_id' => $definition,
            'name' => 'FM',
            'display_name' => 'Family Member',
            'capacity' => 2,
            'order' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $payment = DB::table('payment_definitions')->insertGetId([
            'sharaf_definition_id' => $definition,
            'name' => 'Lagat',
            'default_amount' => 1000,
            'default_currency' => 'LKR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'miqaat' => $miqaat,
            'event' => $event,
            'definition' => $definition,
            'hof_position' => $hof,
            'member_position' => $member,
            'payment' => $payment,
        ];
    }

    /**
     * @return list<string>
     */
    private function headers(int $definitionId): array
    {
        $columns = $this->getJson('/sharafs/import/columns?sharaf_definition_id='.$definitionId)
            ->assertOk()
            ->json('data.columns');

        return array_column($columns, 'header');
    }

    /**
     * @param  list<string>  $headers
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    private function row(array $headers, array $values): array
    {
        $row = [];
        foreach ($headers as $header) {
            $row[$header] = $values[$header] ?? '';
        }

        return $row;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<string, string>>  $rows
     */
    private function csvFile(array $headers, array $rows): UploadedFile
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $header) {
                $line[] = $row[$header] ?? '';
            }
            fputcsv($handle, $line);
        }
        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        return UploadedFile::fake()->createWithContent('sharafs.csv', $contents === false ? '' : $contents);
    }
}
