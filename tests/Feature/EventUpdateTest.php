<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Miqaat;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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
            $table->unsignedBigInteger('miqaat_id');
            $table->date('date');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function test_put_updates_an_event(): void
    {
        $miqaat = Miqaat::create([
            'name' => 'Ashara',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-10',
            'active_status' => true,
            'archived' => false,
        ]);
        $event = Event::create([
            'miqaat_id' => $miqaat->id,
            'date' => '2026-06-02',
            'name' => 'Day 1',
            'description' => 'Original',
        ]);

        $response = $this->putJson("/events/{$event->id}", [
            'miqaat_id' => $miqaat->id,
            'date' => '2026-06-03',
            'name' => 'Day 2',
            'description' => 'Updated',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Day 2')
            ->assertJsonPath('data.description', 'Updated');

        $this->assertSame('Day 2', $event->fresh()->name);
        $this->assertSame('2026-06-03', $event->fresh()->date->toDateString());
    }

    public function test_put_rejects_a_date_outside_the_miqaat(): void
    {
        $miqaat = Miqaat::create([
            'name' => 'Ashara',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-10',
            'active_status' => true,
            'archived' => false,
        ]);
        $event = Event::create([
            'miqaat_id' => $miqaat->id,
            'date' => '2026-06-02',
            'name' => 'Day 1',
        ]);

        $response = $this->putJson("/events/{$event->id}", [
            'date' => '2026-07-01',
            'name' => 'Day 1',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'VALIDATION_ERROR');
        $this->assertSame('Day 1', $event->fresh()->name);
        $this->assertSame('2026-06-02', $event->fresh()->date->toDateString());
    }

    public function test_put_missing_event_returns_404(): void
    {
        $this->putJson('/events/999', [
            'name' => 'Missing',
        ])->assertStatus(404)
            ->assertJsonPath('error', 'NOT_FOUND');
    }
}
