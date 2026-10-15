<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Miqaat;
use App\Models\SharafDefinition;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SharafDefinitionEventMoveTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('sharaf_definitions');
        Schema::dropIfExists('events');
        Schema::dropIfExists('miqaats');
        Schema::dropIfExists('audit_logs');

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

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('miqaat_id');
            $table->date('date');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('sharaf_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('sharaf_type_id')->nullable();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('key', 20)->nullable();
            $table->unsignedInteger('default_capacity')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('entity', 64);
            $table->unsignedBigInteger('auditable_id');
            $table->string('action', 32);
            $table->string('actor_its')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('summary')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
            $table->string('parent_entity', 64)->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function test_put_allows_moving_a_definition_to_another_event_in_the_same_miqaat(): void
    {
        [$definition, $sameMiqaatEvent] = $this->definitionWithEvents();

        $response = $this->putJson("/api/sharaf-definitions/{$definition->id}", [
            'event_id' => $sameMiqaatEvent->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.event_id', $sameMiqaatEvent->id);
        $this->assertSame($sameMiqaatEvent->id, $definition->fresh()->event_id);
    }

    public function test_put_rejects_moving_a_definition_to_an_event_in_another_miqaat(): void
    {
        [$definition, , $otherMiqaatEvent] = $this->definitionWithEvents();

        $response = $this->putJson("/api/sharaf-definitions/{$definition->id}", [
            'event_id' => $otherMiqaatEvent->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'VALIDATION_ERROR');
        $this->assertNotSame($otherMiqaatEvent->id, $definition->fresh()->event_id);
    }

    /**
     * @return array{0: SharafDefinition, 1: Event, 2: Event}
     */
    private function definitionWithEvents(): array
    {
        $miqaat = Miqaat::create([
            'name' => 'Jamadal Ula',
            'start_date' => '2026-10-16',
            'end_date' => '2026-10-18',
            'active_status' => true,
            'archived' => false,
        ]);
        $otherMiqaat = Miqaat::create([
            'name' => 'Ashara',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-10',
            'active_status' => false,
            'archived' => false,
        ]);

        $sourceEvent = Event::create([
            'miqaat_id' => $miqaat->id,
            'date' => '2026-10-16',
            'name' => 'Morning Bethak',
        ]);
        $sameMiqaatEvent = Event::create([
            'miqaat_id' => $miqaat->id,
            'date' => '2026-10-16',
            'name' => 'Evening Bethak',
        ]);
        $otherMiqaatEvent = Event::create([
            'miqaat_id' => $otherMiqaat->id,
            'date' => '2026-06-02',
            'name' => 'Day 1',
        ]);

        $definition = SharafDefinition::create([
            'event_id' => $sourceEvent->id,
            'name' => 'Takht',
            'sort_order' => 1,
        ]);

        return [$definition, $sameMiqaatEvent, $otherMiqaatEvent];
    }
}
