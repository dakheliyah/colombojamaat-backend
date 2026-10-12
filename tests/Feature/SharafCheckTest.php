<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SharafCheckTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('sharaf_checks');
        Schema::dropIfExists('sharaf_check_definitions');
        Schema::dropIfExists('sharafs');
        Schema::dropIfExists('sharaf_definitions');

        Schema::create('sharaf_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->default(1);
            $table->string('name');
            $table->integer('sort_order')->nullable();
            $table->timestamps();
        });

        Schema::create('sharafs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_definition_id');
            $table->integer('rank')->default(0);
            $table->integer('capacity')->default(1);
            $table->string('status')->default('pending');
            $table->string('hof_its');
            $table->timestamps();
        });

        Schema::create('sharaf_check_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_definition_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('user_type')->default('Finance');
            $table->string('status_api_url', 500)->nullable();
            $table->string('webhook_url', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('sharaf_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sharaf_id');
            $table->unsignedBigInteger('sharaf_check_definition_id');
            $table->boolean('is_done')->default(false);
            $table->string('checked_by_its')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('sharaf_checks');
        Schema::dropIfExists('sharaf_check_definitions');
        Schema::dropIfExists('sharafs');
        Schema::dropIfExists('sharaf_definitions');
        parent::tearDown();
    }

    public function test_definition_crud_and_toggle_follow_payment_definition_shape(): void
    {
        $definitionId = DB::table('sharaf_definitions')->insertGetId([
            'name' => 'Nikah',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sharafId = DB::table('sharafs')->insertGetId([
            'sharaf_definition_id' => $definitionId,
            'hof_its' => '111',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $created = $this->postJson('/api/sharaf-check-definitions', [
            'sharaf_definition_id' => $definitionId,
            'name' => 'Documents',
            'description' => 'Passport copies',
            'user_type' => 'Anjuman',
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.name', 'Documents')
            ->assertJsonPath('data.user_type', 'Anjuman');

        $checkDefinitionId = $created->json('data.id');

        $this->postJson('/api/sharaf-check-definitions', [
            'sharaf_definition_id' => $definitionId,
            'name' => 'Documents',
        ])->assertStatus(422)->assertJsonPath('error', 'DUPLICATE_ERROR');

        $this->getJson("/api/sharaf-definitions/{$definitionId}/check-definitions")
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Documents');

        $this->withCredentials()
            ->withUnencryptedCookie('user', '30304640')
            ->patchJson("/api/sharafs/{$sharafId}/checks/{$checkDefinitionId}", ['done' => true])
            ->assertOk()
            ->assertJsonPath('data.is_done', true)
            ->assertJsonPath('data.checked_by_its', '30304640');

        $this->patchJson("/api/sharafs/{$sharafId}/checks/{$checkDefinitionId}", ['done' => false])
            ->assertOk()
            ->assertJsonPath('data.is_done', false)
            ->assertJsonPath('data.checked_by_its', null);

        $this->patchJson("/api/sharafs/{$sharafId}/checks/999", ['done' => true])
            ->assertNotFound();
    }

    public function test_status_api_refresh_stores_the_flag_and_toggle_notifies_the_webhook(): void
    {
        $definitionId = DB::table('sharaf_definitions')->insertGetId([
            'name' => 'Nikah',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sharafId = DB::table('sharafs')->insertGetId([
            'sharaf_definition_id' => $definitionId,
            'hof_its' => '111',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'https://status.test/*' => Http::response(['cleared' => true], 200),
            'https://hooks.test/*' => Http::response(['ok' => true], 200),
        ]);

        $created = $this->postJson('/api/sharaf-check-definitions', [
            'sharaf_definition_id' => $definitionId,
            'name' => 'FMB',
            'status_api_url' => 'https://status.test/clearance',
            'webhook_url' => 'https://hooks.test/sharaf-check',
        ])->assertCreated();

        $checkDefinitionId = $created->json('data.id');

        $this->postJson("/api/sharafs/{$sharafId}/checks/{$checkDefinitionId}/refresh")
            ->assertOk()
            ->assertJsonPath('data.is_done', true)
            ->assertJsonPath('data.webhook_notified', true);

        Http::assertSent(function ($request) use ($sharafId, $checkDefinitionId) {
            return $request->url() === 'https://hooks.test/sharaf-check'
                && $request['event'] === 'sharaf_check.updated'
                && $request['sharaf_id'] === $sharafId
                && $request['sharaf_check_definition_id'] === $checkDefinitionId
                && $request['hof_its'] === '111'
                && $request['is_done'] === true;
        });

        $this->patchJson("/api/sharafs/{$sharafId}/checks/{$checkDefinitionId}", ['done' => false])
            ->assertOk()
            ->assertJsonPath('data.is_done', false)
            ->assertJsonPath('data.webhook_notified', true);

        $this->postJson('/api/sharaf-check-definitions', [
            'sharaf_definition_id' => $definitionId,
            'name' => 'Bad URL',
            'webhook_url' => 'not-a-url',
        ])->assertStatus(422);
    }
}
