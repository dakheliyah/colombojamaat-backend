<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Miqaat;
use App\Models\SharafDefinition;
use App\Models\SharafPosition;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CascadeDeleteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('sharaf_positions');
        Schema::dropIfExists('sharaf_definitions');
        Schema::dropIfExists('events');
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

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('miqaat_id')->constrained('miqaats')->cascadeOnDelete();
            $table->date('date');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('sharaf_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('sharaf_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sharaf_definition_id')->constrained('sharaf_definitions')->cascadeOnDelete();
            $table->string('name');
            $table->string('display_name');
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
    }

    public function test_delete_miqaat_cascades_its_events(): void
    {
        $miqaat = Miqaat::create([
            'name' => 'Ashara',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-10',
            'active_status' => true,
        ]);
        $event = Event::create([
            'miqaat_id' => $miqaat->id,
            'date' => '2026-06-02',
            'name' => 'Day 1',
        ]);

        $this->deleteJson("/miqaats/{$miqaat->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNull(Miqaat::find($miqaat->id));
        $this->assertNull(Event::find($event->id));
    }

    public function test_delete_sharaf_definition_cascades_its_positions(): void
    {
        $miqaat = Miqaat::create([
            'name' => 'Ashara',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-10',
        ]);
        $event = Event::create([
            'miqaat_id' => $miqaat->id,
            'date' => '2026-06-02',
            'name' => 'Day 1',
        ]);
        $definition = SharafDefinition::create([
            'event_id' => $event->id,
            'name' => 'Gate',
        ]);
        $position = SharafPosition::create([
            'sharaf_definition_id' => $definition->id,
            'name' => 'hof',
            'display_name' => 'HOF',
            'order' => 1,
        ]);

        $this->deleteJson("/sharaf-definitions/{$definition->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNull(SharafDefinition::find($definition->id));
        $this->assertNull(SharafPosition::find($position->id));
    }

    public function test_delete_sharaf_position(): void
    {
        $miqaat = Miqaat::create([
            'name' => 'Ashara',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-10',
        ]);
        $event = Event::create([
            'miqaat_id' => $miqaat->id,
            'date' => '2026-06-02',
            'name' => 'Day 1',
        ]);
        $definition = SharafDefinition::create([
            'event_id' => $event->id,
            'name' => 'Gate',
        ]);
        $position = SharafPosition::create([
            'sharaf_definition_id' => $definition->id,
            'name' => 'hof',
            'display_name' => 'HOF',
            'order' => 1,
        ]);

        $this->deleteJson("/sharaf-positions/{$position->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNull(SharafPosition::find($position->id));
        $this->assertNotNull(SharafDefinition::find($definition->id));
    }
}
