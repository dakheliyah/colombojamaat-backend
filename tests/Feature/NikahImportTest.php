<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use ZipArchive;

class NikahImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('sharaf_members');
        Schema::dropIfExists('sharafs');
        Schema::dropIfExists('sharaf_positions');
        Schema::dropIfExists('sharaf_definitions');
        Schema::dropIfExists('events');
        Schema::dropIfExists('foreign_census');
        Schema::dropIfExists('census');
        Schema::dropIfExists('miqaats');

        Schema::create('miqaats', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('census', function (Blueprint $table) {
            $table->id();
            $table->string('its_id')->unique();
            $table->string('hof_id');
            $table->string('name')->nullable();
            $table->unsignedInteger('age')->nullable();
            $table->string('gender')->nullable();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->string('arabic_name')->nullable();
            $table->string('jamaat')->nullable();
            $table->string('jamiat')->nullable();
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
            $table->timestamps();
        });

        Schema::create('sharaf_positions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_definition_id');
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->integer('capacity')->nullable();
            $table->integer('order');
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
            $table->unique(['sharaf_id', 'its_id']);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('sharaf_members');
        Schema::dropIfExists('sharafs');
        Schema::dropIfExists('sharaf_positions');
        Schema::dropIfExists('sharaf_definitions');
        Schema::dropIfExists('events');
        Schema::dropIfExists('foreign_census');
        Schema::dropIfExists('census');
        Schema::dropIfExists('miqaats');

        parent::tearDown();
    }

    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        URL::forceRootUrl(config('app.url'));

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    public function test_preview_does_not_write_and_census_maps_gender_words(): void
    {
        $definitionId = $this->seedDefinition('2026-10-16', 'Nikah Morning');
        DB::table('census')->insert([
            'its_id' => '30359451',
            'hof_id' => '30359451',
            'name' => 'Old Name',
            'gender' => 'Male',
            'mobile' => '+94000000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $file = $this->workbook([
            $this->person('480652', '30359451', 'Taha bhai', 'Dulha', '+94766466484', '23', 'M'),
            $this->person('480652', '60474915', 'Fatema bai', 'Dulhan', '+94766466484', '22', 'F', '30359451'),
        ]);

        $preview = $this->post('/api/nikah-import/preview', [
            'sharaf_definition_id' => $definitionId,
            'file' => $file,
        ], ['Accept' => 'application/json']);

        $preview->assertOk()
            ->assertJsonPath('data.can_apply_census', true)
            ->assertJsonPath('data.can_apply_sharafs', true)
            ->assertJsonPath('data.party_count', 1)
            ->assertJsonPath('data.person_count', 2)
            ->assertJsonPath('data.parties.0.action', 'create')
            ->assertJsonPath('data.parties.0.token', '480652');

        $creates = $preview->json('data.census.creates');
        $this->assertCount(1, $creates);
        $this->assertSame('60474915', $creates[0]['its']);
        $this->assertSame('Female', $creates[0]['fields']['gender']);
        $this->assertNull($creates[0]['fields']['mobile']);

        $updates = $preview->json('data.census.updates');
        $this->assertCount(1, $updates);
        $fields = array_column($updates[0]['changes'], 'field');
        $this->assertContains('name', $fields);
        $this->assertContains('mobile', $fields);
        $this->assertNotContains('gender', $fields);

        $this->assertSame(0, DB::table('sharafs')->count());
        $this->assertSame('Old Name', DB::table('census')->where('its_id', '30359451')->value('name'));
    }

    public function test_apply_census_then_sharafs_is_idempotent(): void
    {
        $definitionId = $this->seedDefinition('2026-10-16', 'Nikah Morning');
        DB::table('census')->insert([
            'its_id' => '30359451',
            'hof_id' => '11111111',
            'name' => 'Old Name',
            'gender' => 'Male',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $rows = [
            $this->person('480652', '30359451', 'Taha bhai', 'Dulha', '+94766466484', '23', 'M'),
            $this->person('480652', '60474915', 'Fatema bai', 'Dulhan', '+94766466484', '22', 'F', '30359451'),
        ];

        $this->post('/api/nikah-import/census', [
            'sharaf_definition_id' => $definitionId,
            'file' => $this->workbook($rows),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.updated', 1);

        $dulha = DB::table('census')->where('its_id', '30359451')->first();
        $this->assertSame('Taha bhai', $dulha->name);
        $this->assertSame('+94766466484', $dulha->mobile);
        $this->assertSame('11111111', $dulha->hof_id);
        $this->assertSame('COLOMBO', $dulha->jamaat);
        $this->assertSame('Far East', $dulha->jamiat);

        $dulhan = DB::table('census')->where('its_id', '60474915')->first();
        $this->assertSame('60474915', $dulhan->hof_id);
        $this->assertNull($dulhan->mobile);
        $this->assertSame('Female', $dulhan->gender);

        $this->post('/api/nikah-import/sharafs', [
            'sharaf_definition_id' => $definitionId,
            'file' => $this->workbook($rows),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.created', 1);

        $sharaf = DB::table('sharafs')->first();
        $this->assertSame('pending', $sharaf->status);
        $this->assertSame('480652', $sharaf->token);
        $this->assertSame('30359451', $sharaf->hof_its);
        $this->assertSame(2, (int) $sharaf->capacity);
        $this->assertSame(2, DB::table('sharaf_members')->where('sharaf_id', $sharaf->id)->count());
        $this->assertSame('+94766466484', DB::table('sharaf_members')->where('its_id', '30359451')->value('phone'));
        $this->assertNull(DB::table('sharaf_members')->where('its_id', '60474915')->value('phone'));

        $this->post('/api/nikah-import/census', [
            'sharaf_definition_id' => $definitionId,
            'file' => $this->workbook($rows),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.updated', 0);

        $this->post('/api/nikah-import/sharafs', [
            'sharaf_definition_id' => $definitionId,
            'file' => $this->workbook($rows),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.unchanged', 1);
        $this->assertSame(1, DB::table('sharafs')->count());
    }

    public function test_removal_and_pending_hof_change_need_confirmation(): void
    {
        $definitionId = $this->seedDefinition('2026-10-16', 'Nikah Morning');
        $rows = [
            $this->person('480652', '30359451', 'Taha bhai', 'Dulha', '+94766466484', '23', 'M'),
            $this->person('480652', '60474915', 'Fatema bai', 'Dulhan', '+94766466484', '22', 'F', '30359451'),
        ];
        $this->post('/api/nikah-import/sharafs', [
            'sharaf_definition_id' => $definitionId,
            'file' => $this->workbook($rows),
        ], ['Accept' => 'application/json'])->assertCreated();

        $withoutDulhan = [
            $this->person('480652', '30359451', 'Taha bhai', 'Dulha', '+94766466484', '23', 'M'),
        ];
        $this->post('/api/nikah-import/sharafs', [
            'sharaf_definition_id' => $definitionId,
            'file' => $this->workbook($withoutDulhan),
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame(2, DB::table('sharaf_members')->count());

        $this->post('/api/nikah-import/sharafs', [
            'sharaf_definition_id' => $definitionId,
            'confirm_removals' => ['480652'],
            'file' => $this->workbook($withoutDulhan),
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.removals', 1);
        $this->assertSame(1, DB::table('sharaf_members')->count());

        $newDulha = [
            $this->person('480652', '30359639', 'Mufaddal bhai', 'Dulha', '+94722243000', '24', 'M'),
        ];
        $this->post('/api/nikah-import/sharafs', [
            'sharaf_definition_id' => $definitionId,
            'confirm_hof_changes' => ['480652'],
            'file' => $this->workbook($newDulha),
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.hof_changes', 1);

        $sharaf = DB::table('sharafs')->first();
        $this->assertSame('30359639', $sharaf->hof_its);
        $this->assertSame('30359639', DB::table('sharaf_members')->value('its_id'));
    }

    public function test_confirmed_hof_change_stays_blocked(): void
    {
        $definitionId = $this->seedDefinition('2026-10-16', 'Nikah Morning');
        $rows = [
            $this->person('480652', '30359451', 'Taha bhai', 'Dulha', '+94766466484', '23', 'M'),
        ];
        $this->post('/api/nikah-import/sharafs', [
            'sharaf_definition_id' => $definitionId,
            'file' => $this->workbook($rows),
        ], ['Accept' => 'application/json'])->assertCreated();
        DB::table('sharafs')->update(['status' => 'confirmed']);

        $newDulha = [
            $this->person('480652', '30359639', 'Mufaddal bhai', 'Dulha', '+94722243000', '24', 'M'),
        ];
        $this->post('/api/nikah-import/sharafs', [
            'sharaf_definition_id' => $definitionId,
            'confirm_hof_changes' => ['480652'],
            'file' => $this->workbook($newDulha),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.hof_changes', 0)
            ->assertJsonPath('data.blocked_hof_changes.0.token', '480652');

        $this->assertSame('30359451', DB::table('sharafs')->value('hof_its'));
        $this->assertSame('30359451', DB::table('sharaf_members')->value('its_id'));
    }

    public function test_date_mismatch_warns_and_still_applies_sharafs_and_census(): void
    {
        $definitionId = $this->seedDefinition('2026-10-17', 'Nikah Morning');
        $file = $this->workbook([
            $this->person('480652', '30359451', 'Taha bhai', 'Dulha', '+94766466484', '23', 'M'),
        ]);

        $this->post('/api/nikah-import/preview', [
            'sharaf_definition_id' => $definitionId,
            'file' => $file,
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.can_apply_census', true)
            ->assertJsonPath('data.can_apply_sharafs', true)
            ->assertJsonPath('data.definition_errors', [])
            ->assertJsonPath('data.warnings.0', 'Bethak date 2026-10-16 does not match the event date 2026-10-17.');

        $this->post('/api/nikah-import/sharafs', [
            'sharaf_definition_id' => $definitionId,
            'file' => $this->workbook([
                $this->person('480652', '30359451', 'Taha bhai', 'Dulha', '+94766466484', '23', 'M'),
            ]),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.created', 1);

        $this->assertSame(1, DB::table('sharafs')->count());

        $this->post('/api/nikah-import/census', [
            'sharaf_definition_id' => $definitionId,
            'file' => $this->workbook([
                $this->person('480652', '30359451', 'Taha bhai', 'Dulha', '+94766466484', '23', 'M'),
            ]),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.created', 1);
    }

    public function test_session_word_in_the_event_name_is_not_required(): void
    {
        $definitionId = $this->seedDefinition('2026-10-16', 'Jummah Afternoon Bethak');

        $this->post('/api/nikah-import/preview', [
            'sharaf_definition_id' => $definitionId,
            'file' => $this->workbook([
                $this->person('480652', '30359451', 'Taha bhai', 'Dulha', '+94766466484', '23', 'M'),
            ]),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.definition_errors', [])
            ->assertJsonPath('data.can_apply_sharafs', true);
    }

    public function test_role_map_places_people_and_preview_lists_them(): void
    {
        $definitionId = $this->seedDefinition('2026-10-16', 'Nikah Morning');
        $headId = (int) DB::table('sharaf_positions')->insertGetId([
            'sharaf_definition_id' => $definitionId,
            'name' => 'Head',
            'display_name' => 'Head',
            'capacity' => 1,
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('sharaf_positions')->where('sharaf_definition_id', $definitionId)->where('name', 'Dulha')->update(['order' => 9]);
        $guestId = (int) DB::table('sharaf_positions')->where('sharaf_definition_id', $definitionId)->where('name', 'Extra Member')->value('id');

        $rows = [
            $this->person('480652', '30359451', 'Taha bhai', 'Dulha', '+94766466484', '23', 'M'),
            $this->person('480652', '60474915', 'Fatema bai', 'Dulhan', '+94766466484', '22', 'F', '30359451'),
        ];

        $this->post('/api/nikah-import/preview', [
            'sharaf_definition_id' => $definitionId,
            'file' => $this->workbook($rows),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.can_apply_sharafs', false)
            ->assertJsonPath('data.parties.0.people.0.name', 'Taha bhai');

        $this->post('/api/nikah-import/preview', [
            'sharaf_definition_id' => $definitionId,
            'file' => $this->workbook($rows),
            'role_map' => json_encode([
                'Dulha' => $headId,
                'Dulhan' => $guestId,
            ]),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.can_apply_sharafs', true)
            ->assertJsonPath('data.parties.0.people.0.position', 'Head');

        $this->post('/api/nikah-import/sharafs', [
            'sharaf_definition_id' => $definitionId,
            'file' => $this->workbook($rows),
            'role_map' => json_encode([
                'Dulha' => $headId,
                'Dulhan' => $guestId,
            ]),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame($headId, (int) DB::table('sharaf_members')->where('its_id', '30359451')->value('sharaf_position_id'));
        $this->assertSame($guestId, (int) DB::table('sharaf_members')->where('its_id', '60474915')->value('sharaf_position_id'));
    }

    public function test_misaq_sheet_groups_by_incharge_and_maps_members(): void
    {
        $definitionId = $this->seedDefinition('2026-10-16', 'Nikah Morning');
        $inchargePosition = (int) DB::table('sharaf_positions')->where('sharaf_definition_id', $definitionId)->where('name', 'Dulha')->value('id');
        $memberPosition = (int) DB::table('sharaf_positions')->where('sharaf_definition_id', $definitionId)->where('name', 'Extra Member')->value('id');
        $rows = [
            ['16 Oct 2026 - Morning', '30114302', '30114302', 'Insiyah bai', '+923322555153', '13', 'F', 'COLOMBO', 'Pakistan', 'insi@example.com', 'انسية'],
            ['16 Oct 2026 - Morning', '30114302', '40454323', 'Maryam bai', '+923322555153', '45', 'F', 'COLOMBO', 'Pakistan', 'insi@example.com', 'مريم'],
        ];
        $file = $this->misaqWorkbook($rows);
        $roleMap = json_encode([
            'Group incharge' => $inchargePosition,
            'Member' => $memberPosition,
        ]);

        $preview = $this->post('/api/nikah-import/preview', [
            'sharaf_definition_id' => $definitionId,
            'module' => 'misaq',
            'file' => $file,
            'role_map' => $roleMap,
        ], ['Accept' => 'application/json']);

        $preview->assertOk()
            ->assertJsonPath('data.can_apply_census', true)
            ->assertJsonPath('data.can_apply_sharafs', true)
            ->assertJsonPath('data.party_count', 1)
            ->assertJsonPath('data.parties.0.token', '30114302')
            ->assertJsonPath('data.parties.0.people.0.role', 'Group incharge')
            ->assertJsonPath('data.parties.0.people.1.role', 'Member');

        $creates = collect($preview->json('data.census.creates'))->keyBy('its');
        $this->assertSame('+923322555153', $creates['30114302']['fields']['mobile']);
        $this->assertSame('insi@example.com', $creates['30114302']['fields']['email']);
        $this->assertSame('انسية', $creates['30114302']['fields']['arabic_name']);
        $this->assertNull($creates['40454323']['fields']['mobile']);
        $this->assertNull($creates['40454323']['fields']['email']);
        $this->assertSame('مريم', $creates['40454323']['fields']['arabic_name']);

        $this->post('/api/nikah-import/sharafs', [
            'sharaf_definition_id' => $definitionId,
            'module' => 'misaq',
            'file' => $this->misaqWorkbook($rows),
            'role_map' => $roleMap,
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame('30114302', DB::table('sharafs')->value('token'));
        $this->assertSame('30114302', DB::table('sharafs')->value('hof_its'));
        $this->assertSame($inchargePosition, (int) DB::table('sharaf_members')->where('its_id', '30114302')->value('sharaf_position_id'));
        $this->assertSame($memberPosition, (int) DB::table('sharaf_members')->where('its_id', '40454323')->value('sharaf_position_id'));
        $this->assertSame('+923322555153', DB::table('sharaf_members')->where('its_id', '30114302')->value('phone'));
        $this->assertNull(DB::table('sharaf_members')->where('its_id', '40454323')->value('phone'));
    }

    public function test_deselected_party_is_left_unchanged(): void
    {
        $definitionId = $this->seedDefinition('2026-10-16', 'Nikah Morning');
        $original = [
            $this->person('480652', '30359451', 'Taha bhai', 'Dulha', '+94766466484', '23', 'M'),
            $this->person('480652', '60474915', 'Fatema bai', 'Dulhan', '+94766466484', '22', 'F', '30359451'),
        ];
        $this->post('/api/nikah-import/sharafs', [
            'sharaf_definition_id' => $definitionId,
            'file' => $this->workbook($original),
        ], ['Accept' => 'application/json'])->assertCreated();

        $renamed = [
            $this->person('480652', '30359451', 'Taha updated', 'Dulha', '+94766466484', '23', 'M'),
            $this->person('480652', '60474915', 'Fatema bai', 'Dulhan', '+94766466484', '22', 'F', '30359451'),
        ];
        $this->post('/api/nikah-import/sharafs', [
            'sharaf_definition_id' => $definitionId,
            'file' => $this->workbook($renamed),
            'skip_tokens' => json_encode(['480652']),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.updated', 0)
            ->assertJsonPath('data.skipped_parties.0', '480652');

        $this->assertSame('Taha bhai', DB::table('sharaf_members')->where('its_id', '30359451')->value('name'));
        $this->assertSame('Taha bhai', DB::table('sharafs')->value('name'));
    }

    public function test_census_apply_updates_sharaf_name_member_name_and_phone(): void
    {
        $definitionId = $this->seedDefinition('2026-10-16', 'Nikah Morning');
        $original = [
            $this->person('480652', '30359451', 'Taha bhai', 'Dulha', '+94766466484', '23', 'M'),
            $this->person('480652', '60474915', 'Fatema bai', 'Dulhan', '+94766466484', '22', 'F', '30359451'),
        ];
        $this->post('/api/nikah-import/sharafs', [
            'sharaf_definition_id' => $definitionId,
            'file' => $this->workbook($original),
        ], ['Accept' => 'application/json'])->assertCreated();

        $renamed = [
            $this->person('480652', '30359451', 'Taha updated', 'Dulha', '+94111111111', '23', 'M'),
            $this->person('480652', '60474915', 'Fatema changed', 'Dulhan', '+94111111111', '22', 'F', '30359451'),
        ];
        $this->post('/api/nikah-import/census', [
            'sharaf_definition_id' => $definitionId,
            'file' => $this->workbook($renamed),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.sharaf_names', 1)
            ->assertJsonPath('data.member_names', 2)
            ->assertJsonPath('data.member_phones', 1);

        $this->assertSame('Taha updated', DB::table('sharafs')->value('name'));
        $this->assertSame('Taha updated', DB::table('sharaf_members')->where('its_id', '30359451')->value('name'));
        $this->assertSame('+94111111111', DB::table('sharaf_members')->where('its_id', '30359451')->value('phone'));
        $this->assertSame('Fatema changed', DB::table('sharaf_members')->where('its_id', '60474915')->value('name'));
        $this->assertNull(DB::table('sharaf_members')->where('its_id', '60474915')->value('phone'));
    }

    public function test_census_map_and_skip_leave_records_untouched(): void
    {
        $definitionId = $this->seedDefinition('2026-10-16', 'Nikah Morning');
        $now = now();
        DB::table('census')->insert([
            [
                'its_id' => '30359451',
                'hof_id' => '30359451',
                'name' => 'Old Name',
                'gender' => 'Male',
                'jamiat' => 'Old Jamiat',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'its_id' => '60474915',
                'hof_id' => '30359451',
                'name' => 'Fatema bai',
                'gender' => 'Female',
                'jamiat' => 'Keep Jamiat',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
        $original = [
            $this->person('480652', '30359451', 'Taha bhai', 'Dulha', '+94766466484', '23', 'M'),
            $this->person('480652', '60474915', 'Fatema bai', 'Dulhan', '+94766466484', '22', 'F', '30359451'),
        ];
        $this->post('/api/nikah-import/sharafs', [
            'sharaf_definition_id' => $definitionId,
            'file' => $this->workbook($original),
        ], ['Accept' => 'application/json'])->assertCreated();

        $next = [
            $this->person('480652', '30359451', 'Taha updated', 'Dulha', '+94766466484', '23', 'M'),
            $this->person('480652', '60474915', 'Fatema changed', 'Dulhan', '+94766466484', '22', 'F', '30359451'),
            $this->person('480652', '70000001', 'Extra Person', 'Extra Member', '+94766466484', '30', 'M', '30359451'),
        ];
        $payload = [
            'sharaf_definition_id' => $definitionId,
            'census_map' => json_encode([
                'FullName' => 'name',
                'Age' => 'age',
                'Gender' => 'gender',
                'Jamaat' => 'jamaat',
                'Jamiaat' => '',
                'Idara' => 'jamiat',
                'Mobile_No' => 'mobile',
            ]),
            'skip_its' => json_encode(['60474915', '70000001']),
        ];

        $preview = $this->post('/api/nikah-import/preview', array_merge($payload, [
            'file' => $this->workbook($next),
        ]), ['Accept' => 'application/json']);

        $preview->assertOk()
            ->assertJsonPath('data.can_apply_census', true)
            ->assertJsonPath('data.can_apply_sharafs', true)
            ->assertJsonPath('data.census.skipped', 2)
            ->assertJsonPath('data.parties.0.people.1.its', '60474915')
            ->assertJsonPath('data.parties.0.people.1.skipped', true)
            ->assertJsonPath('data.parties.0.people.2.skipped', true);

        $updates = $preview->json('data.census.updates');
        $this->assertCount(1, $updates);
        $this->assertSame('30359451', $updates[0]['its']);
        $jamiat = collect($updates[0]['changes'])->firstWhere('field', 'jamiat');
        $this->assertSame('Ignored', $jamiat['to']);
        $this->assertSame([], $preview->json('data.census.creates'));

        $changeIts = array_filter(array_column($preview->json('data.parties.0.changes'), 'its'));
        $this->assertNotContains('60474915', $changeIts);
        $this->assertNotContains('70000001', $changeIts);

        $this->post('/api/nikah-import/census', array_merge($payload, [
            'file' => $this->workbook($next),
        ]), ['Accept' => 'application/json'])->assertOk();

        $dulha = DB::table('census')->where('its_id', '30359451')->first();
        $this->assertSame('Taha updated', $dulha->name);
        $this->assertSame('Ignored', $dulha->jamiat);
        $dulhan = DB::table('census')->where('its_id', '60474915')->first();
        $this->assertSame('Fatema bai', $dulhan->name);
        $this->assertSame('Keep Jamiat', $dulhan->jamiat);
        $this->assertNull(DB::table('census')->where('its_id', '70000001')->first());

        $this->post('/api/nikah-import/sharafs', array_merge($payload, [
            'file' => $this->workbook($next),
        ]), ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame(2, DB::table('sharaf_members')->count());
        $this->assertSame('Taha updated', DB::table('sharaf_members')->where('its_id', '30359451')->value('name'));
        $this->assertSame('Fatema bai', DB::table('sharaf_members')->where('its_id', '60474915')->value('name'));
        $this->assertNull(DB::table('sharaf_members')->where('its_id', '70000001')->first());
    }

    public function test_deselected_error_party_does_not_block_the_rest(): void
    {
        $definitionId = $this->seedDefinition('2026-10-16', 'Nikah Morning');
        $rows = [
            $this->person('111', '30359451', 'Taha bhai', 'Dulha', '+94766466484', '23', 'M'),
            $this->person('222', '30359639', 'Other bhai', 'Dulha', '+94700000000', '24', 'M'),
        ];
        for ($i = 0; $i < 6; $i++) {
            $rows[] = $this->person('222', (string) (70000001 + $i), 'Extra '.$i, 'Extra Member', '+94700000000', '30', 'M', '30359639');
        }

        $this->post('/api/nikah-import/sharafs', [
            'sharaf_definition_id' => $definitionId,
            'file' => $this->workbook($rows),
        ], ['Accept' => 'application/json'])->assertStatus(422);
        $this->assertSame(0, DB::table('sharafs')->count());

        $this->post('/api/nikah-import/sharafs', [
            'sharaf_definition_id' => $definitionId,
            'file' => $this->workbook($rows),
            'skip_tokens' => json_encode(['222']),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.skipped_parties.0', '222');

        $this->assertSame(1, DB::table('sharafs')->count());
        $this->assertSame('111', DB::table('sharafs')->value('token'));
    }

    public function test_same_census_field_can_be_mapped_from_two_columns(): void
    {
        $definitionId = $this->seedDefinition('2026-10-16', 'Nikah Morning');
        $payload = [
            'sharaf_definition_id' => $definitionId,
            'census_map' => json_encode([
                'FullName' => 'name',
                'Mobile_No' => 'mobile',
                'Idara' => 'mobile',
            ]),
        ];
        $rows = [
            $this->person('480652', '30359451', 'Taha bhai', 'Dulha', '', '23', 'M'),
        ];

        $this->post('/api/nikah-import/preview', array_merge($payload, [
            'file' => $this->workbook($rows),
        ]), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.can_apply_census', true)
            ->assertJsonPath('data.census_errors', []);

        $this->post('/api/nikah-import/census', array_merge($payload, [
            'file' => $this->workbook($rows),
        ]), ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.created', 1);

        $this->assertSame('Ignored', DB::table('census')->where('its_id', '30359451')->value('mobile'));

        $filled = [
            $this->person('480652', '30359451', 'Taha bhai', 'Dulha', '+94766466484', '23', 'M'),
        ];
        $this->post('/api/nikah-import/census', array_merge($payload, [
            'file' => $this->workbook($filled),
        ]), ['Accept' => 'application/json'])->assertOk();

        $this->assertSame('+94766466484', DB::table('census')->where('its_id', '30359451')->value('mobile'));
    }

    public function test_anjuman_census_members_are_left_and_others_go_to_foreign_census(): void
    {
        Schema::table('census', function (Blueprint $table) {
            $table->string('source')->nullable();
        });
        Schema::create('foreign_census', function (Blueprint $table) {
            $table->id();
            $table->string('its_id')->unique();
            $table->string('hof_id');
            $table->string('name')->nullable();
            $table->string('arabic_name')->nullable();
            $table->unsignedInteger('age')->nullable();
            $table->string('gender')->nullable();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->string('jamaat')->nullable();
            $table->string('jamiat')->nullable();
        });
        $definitionId = $this->seedDefinition('2026-10-16', 'Nikah Morning');
        $now = now();
        DB::table('census')->insert([
            [
                'its_id' => '30359451',
                'hof_id' => '30359451',
                'name' => 'Local Name',
                'source' => 'census',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'its_id' => '60474915',
                'hof_id' => '30359451',
                'name' => 'Foreign Name',
                'source' => 'foreign_census',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
        DB::table('foreign_census')->insert([
            'its_id' => '60474915',
            'hof_id' => '30359451',
            'name' => 'Foreign Name',
        ]);

        $rows = [
            $this->person('480652', '30359451', 'Local Changed', 'Dulha', '+94111111111', '23', 'M'),
            $this->person('480652', '60474915', 'Foreign Changed', 'Dulhan', '+94111111111', '22', 'F', '30359451'),
            $this->person('480652', '70000001', 'New Person', 'Extra Member', '+94111111111', '30', 'M', '30359451'),
        ];

        $this->post('/api/nikah-import/preview', [
            'sharaf_definition_id' => $definitionId,
            'file' => $this->workbook($rows),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.census.local', 1)
            ->assertJsonPath('data.census.creates.0.its', '70000001');

        $this->post('/api/nikah-import/census', [
            'sharaf_definition_id' => $definitionId,
            'file' => $this->workbook($rows),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame('Local Name', DB::table('census')->where('its_id', '30359451')->value('name'));
        $this->assertNull(DB::table('foreign_census')->where('its_id', '30359451')->first());
        $this->assertSame('Foreign Changed', DB::table('foreign_census')->where('its_id', '60474915')->value('name'));
        $this->assertSame('New Person', DB::table('foreign_census')->where('its_id', '70000001')->value('name'));
        $this->assertSame('70000001', DB::table('foreign_census')->where('its_id', '70000001')->value('hof_id'));
    }

    /**
     * @param  list<string>  $extra
     * @return list<string>
     */
    private function person(
        string $token,
        string $its,
        string $name,
        string $role,
        string $mobile,
        string $age,
        string $gender,
        ?string $groupId = null,
    ): array {
        return [$token, '16 Oct 2026 - Morning', $groupId ?? $its, $its, $name, $role, $mobile, $age, $gender, 'COLOMBO', 'Far East'];
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function workbook(array $rows): UploadedFile
    {
        $headers = ['Token', 'Bethak_Date', 'Group_ID', 'Mumin_ID', 'FullName', 'Mumin_Type', 'Mobile_No', 'Age', 'Gender', 'Jamaat', 'Jamiaat', 'Idara'];
        $sheetRows = '';
        $line = 1;
        $sheetRows .= $this->xmlRow($line, $headers);
        foreach ($rows as $row) {
            $line++;
            $row[] = 'Ignored';
            $sheetRows .= $this->xmlRow($line, $row);
        }

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .$sheetRows
            .'</sheetData></worksheet>';

        $path = tempnam(sys_get_temp_dir(), 'nikah');
        $xlsx = $path.'.xlsx';
        rename($path, $xlsx);
        $zip = new ZipArchive();
        $zip->open($xlsx, ZipArchive::CREATE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        return new UploadedFile($xlsx, 'nikah.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function misaqWorkbook(array $rows): UploadedFile
    {
        $headers = ['Bethak_Date', 'Group_ID', 'Mumin_ID', 'FullName', 'Mobile_No', 'Age', 'Gender', 'Jamaat', 'Jamiaat', 'Email', 'Arabic_FullName'];
        $sheetRows = $this->xmlRow(1, $headers);
        $line = 1;
        foreach ($rows as $row) {
            $line++;
            $sheetRows .= $this->xmlRow($line, $row);
        }

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .$sheetRows
            .'</sheetData></worksheet>';

        $path = tempnam(sys_get_temp_dir(), 'misaq');
        $xlsx = $path.'.xlsx';
        rename($path, $xlsx);
        $zip = new ZipArchive();
        $zip->open($xlsx, ZipArchive::CREATE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        return new UploadedFile($xlsx, 'misaq.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    /**
     * @param  list<string>  $cells
     */
    private function xmlRow(int $line, array $cells): string
    {
        $letters = range('A', 'Z');
        $xml = '<row r="'.$line.'">';
        foreach ($cells as $index => $value) {
            $ref = $letters[$index].$line;
            $text = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $xml .= '<c r="'.$ref.'" t="inlineStr"><is><t>'.$text.'</t></is></c>';
        }
        $xml .= '</row>';

        return $xml;
    }

    private function seedDefinition(string $date, string $name): int
    {
        $miqaatId = DB::table('miqaats')->insertGetId([
            'name' => 'Ashara',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $eventId = DB::table('events')->insertGetId([
            'miqaat_id' => $miqaatId,
            'date' => $date,
            'name' => $name,
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

        $roles = [
            'Dulha' => [1, 1],
            'Dulhan' => [1, 2],
            'Wali' => [1, 3],
            'Shahid 1' => [1, 4],
            'Shahid 2' => [1, 5],
            'Dulha Maa' => [1, 6],
            'Dulhan Maa' => [1, 7],
            'Extra Member' => [5, 8],
        ];
        foreach ($roles as $role => [$capacity, $order]) {
            DB::table('sharaf_positions')->insert([
                'sharaf_definition_id' => $definitionId,
                'name' => $role,
                'display_name' => $role,
                'capacity' => $capacity,
                'order' => $order,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return (int) $definitionId;
    }
}
