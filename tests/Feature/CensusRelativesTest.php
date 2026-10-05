<?php

namespace Tests\Feature;

use App\Models\Census;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CensusRelativesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('census');
        Schema::create('census', function (Blueprint $table) {
            $table->id();
            $table->string('its_id')->unique();
            $table->string('hof_id');
            $table->string('father_its')->nullable();
            $table->string('mother_its')->nullable();
            $table->string('spouse_its')->nullable();
            $table->string('name')->nullable();
            $table->string('gender')->nullable();
            $table->string('mobile')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('census');
        parent::tearDown();
    }

    public function test_relatives_include_direct_spouse_and_parents(): void
    {
        $this->person('10000001', 'Father', 'male', '111');
        $this->person('10000002', 'Mother', 'female', '222');
        $this->person('10000003', 'Wife', 'female', '333', ['spouse_its' => '10000004']);
        $this->person('10000004', 'Husband', 'male', '444', [
            'spouse_its' => '10000003',
            'father_its' => '10000001',
            'mother_its' => '10000002',
        ]);

        $this->getJson('/api/census/10000004/relatives')
            ->assertOk()
            ->assertJsonPath('data.person.name', 'Husband')
            ->assertJsonPath('data.person.mobile', '444')
            ->assertJsonPath('data.spouse.its_id', '10000003')
            ->assertJsonPath('data.spouse.name', 'Wife')
            ->assertJsonPath('data.spouse.mobile', '333')
            ->assertJsonPath('data.father.its_id', '10000001')
            ->assertJsonPath('data.mother.its_id', '10000002');
    }

    public function test_relatives_resolve_spouse_from_the_other_row(): void
    {
        $this->person('20000001', 'Husband', 'male', '555', ['spouse_its' => '20000002']);
        $this->person('20000002', 'Wife', 'female', '666');

        $this->getJson('/api/census/20000002/relatives')
            ->assertOk()
            ->assertJsonPath('data.spouse.its_id', '20000001')
            ->assertJsonPath('data.spouse.name', 'Husband')
            ->assertJsonPath('data.father', null)
            ->assertJsonPath('data.mother', null);
    }

    public function test_missing_parent_row_still_returns_its(): void
    {
        $this->person('30000001', 'Child', 'male', '777', [
            'father_its' => '30000009',
        ]);

        $this->getJson('/api/census/30000001/relatives')
            ->assertOk()
            ->assertJsonPath('data.father.its_id', '30000009')
            ->assertJsonPath('data.father.name', null)
            ->assertJsonPath('data.spouse', null);
    }

    public function test_unknown_its_returns_404(): void
    {
        $this->getJson('/api/census/40404040/relatives')
            ->assertStatus(404)
            ->assertJsonPath('error', 'NOT_FOUND');
    }

    private function person(string $its, string $name, string $gender, string $mobile, array $extra = []): void
    {
        Census::query()->create(array_merge([
            'its_id' => $its,
            'hof_id' => $its,
            'name' => $name,
            'gender' => $gender,
            'mobile' => $mobile,
        ], $extra));
    }
}
