<?php

namespace Database\Seeders;

use App\Enums\SharafStatus;
use App\Models\Event;
use App\Models\Miqaat;
use App\Models\Sharaf;
use App\Models\SharafClearance;
use App\Models\SharafDefinition;
use App\Models\SharafMember;
use App\Models\SharafPosition;
use App\Models\SharafType;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(SharafTypeSeeder::class);
        $this->call(UserRoleSeeder::class);

        // Seed Users
        $testUser = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        $testUser->roles()->attach(UserRole::where('name', 'Master')->first()->id);

        $adminUser = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);
        $adminUser->roles()->attach(UserRole::where('name', 'Admin')->first()->id);

        // Seed Miqaats
        $miqaat1 = Miqaat::create([
            'name' => 'Annual Ijtema 2024',
            'start_date' => '2024-03-15',
            'end_date' => '2024-03-17',
            'description' => 'Annual community gathering with various programs and activities.',
        ]);

        $miqaat2 = Miqaat::create([
            'name' => 'Eid Celebration 2024',
            'start_date' => '2024-04-10',
            'end_date' => '2024-04-12',
            'description' => 'Eid celebration with special programs and sharaf allocations.',
        ]);

        // Seed Events
        $event1 = Event::create([
            'miqaat_id' => $miqaat1->id,
            'date' => '2024-03-15',
            'name' => 'Opening Ceremony',
            'description' => 'Official opening ceremony of the miqaat.',
        ]);

        $event2 = Event::create([
            'miqaat_id' => $miqaat1->id,
            'date' => '2024-03-16',
            'name' => 'Main Program',
            'description' => 'Main program with sharaf allocations.',
        ]);

        $event3 = Event::create([
            'miqaat_id' => $miqaat2->id,
            'date' => '2024-04-10',
            'name' => 'Eid Prayer',
            'description' => 'Eid prayer program with sharaf.',
        ]);

        // Seed Sharaf Definitions
        $sharafDef1 = SharafDefinition::create([
            'event_id' => $event1->id,
            'name' => 'Opening Ceremony Sharaf',
            'description' => 'Sharaf positions for opening ceremony.',
        ]);

        $sharafDef2 = SharafDefinition::create([
            'event_id' => $event2->id,
            'name' => 'Main Program Sharaf',
            'description' => 'Sharaf positions for main program.',
        ]);

        $sharafDef3 = SharafDefinition::create([
            'event_id' => $event3->id,
            'name' => 'Eid Prayer Sharaf',
            'description' => 'Sharaf positions for Eid prayer.',
        ]);

        // Seed Sharaf Positions
        $positions1 = [
            ['name' => 'HOF', 'display_name' => 'Head of Family', 'capacity' => 1, 'order' => 1],
            ['name' => 'FM', 'display_name' => 'Family Member', 'capacity' => 3, 'order' => 2],
            ['name' => 'CH', 'display_name' => 'Child', 'capacity' => 2, 'order' => 3],
        ];

        foreach ($positions1 as $pos) {
            SharafPosition::create([
                'sharaf_definition_id' => $sharafDef1->id,
                'name' => $pos['name'],
                'display_name' => $pos['display_name'],
                'capacity' => $pos['capacity'],
                'order' => $pos['order'],
            ]);
        }

        $positions2 = [
            ['name' => 'HOF', 'display_name' => 'Head of Family', 'capacity' => 1, 'order' => 1],
            ['name' => 'FM', 'display_name' => 'Family Member', 'capacity' => 4, 'order' => 2],
            ['name' => 'CH', 'display_name' => 'Child', 'capacity' => 3, 'order' => 3],
        ];

        foreach ($positions2 as $pos) {
            SharafPosition::create([
                'sharaf_definition_id' => $sharafDef2->id,
                'name' => $pos['name'],
                'display_name' => $pos['display_name'],
                'capacity' => $pos['capacity'],
                'order' => $pos['order'],
            ]);
        }

        $positions3 = [
            ['name' => 'HOF', 'display_name' => 'Head of Family', 'capacity' => 1, 'order' => 1],
            ['name' => 'FM', 'display_name' => 'Family Member', 'capacity' => 2, 'order' => 2],
        ];

        foreach ($positions3 as $pos) {
            SharafPosition::create([
                'sharaf_definition_id' => $sharafDef3->id,
                'name' => $pos['name'],
                'display_name' => $pos['display_name'],
                'capacity' => $pos['capacity'],
                'order' => $pos['order'],
            ]);
        }

        // Seed Sharafs
        $sharaf1 = Sharaf::create([
            'sharaf_definition_id' => $sharafDef1->id,
            'rank' => 1,
            'name' => 'Sharaf Group 1',
            'capacity' => 6,
            'status' => SharafStatus::PENDING,
            'hof_its' => 'ITS001',
        ]);

        $sharaf2 = Sharaf::create([
            'sharaf_definition_id' => $sharafDef1->id,
            'rank' => 2,
            'name' => 'Sharaf Group 2',
            'capacity' => 6,
            'status' => SharafStatus::BS_APPROVED,
            'hof_its' => 'ITS002',
        ]);

        $sharaf3 = Sharaf::create([
            'sharaf_definition_id' => $sharafDef2->id,
            'rank' => 1,
            'name' => 'Main Program Sharaf 1',
            'capacity' => 8,
            'status' => SharafStatus::PENDING,
            'hof_its' => 'ITS003',
        ]);

        $sharaf4 = Sharaf::create([
            'sharaf_definition_id' => $sharafDef3->id,
            'rank' => 1,
            'name' => 'Eid Prayer Sharaf 1',
            'capacity' => 3,
            'status' => SharafStatus::CONFIRMED,
            'hof_its' => 'ITS004',
        ]);

        // Seed Sharaf Members
        // Get positions for sharaf1
        $hofPos1 = SharafPosition::where('sharaf_definition_id', $sharafDef1->id)
            ->where('name', 'HOF')->first();
        $fmPos1 = SharafPosition::where('sharaf_definition_id', $sharafDef1->id)
            ->where('name', 'FM')->first();
        $chPos1 = SharafPosition::where('sharaf_definition_id', $sharafDef1->id)
            ->where('name', 'CH')->first();

        // Members for sharaf1
        SharafMember::create([
            'sharaf_id' => $sharaf1->id,
            'sharaf_position_id' => $hofPos1->id,
            'its_id' => 'ITS001',
            'sp_keyno' => 1,
        ]);

        SharafMember::create([
            'sharaf_id' => $sharaf1->id,
            'sharaf_position_id' => $fmPos1->id,
            'its_id' => 'ITS005',
            'sp_keyno' => 2,
        ]);

        SharafMember::create([
            'sharaf_id' => $sharaf1->id,
            'sharaf_position_id' => $fmPos1->id,
            'its_id' => 'ITS006',
            'sp_keyno' => 3,
        ]);

        SharafMember::create([
            'sharaf_id' => $sharaf1->id,
            'sharaf_position_id' => $chPos1->id,
            'its_id' => 'ITS007',
            'sp_keyno' => 4,
        ]);

        // Members for sharaf2
        $hofPos2 = SharafPosition::where('sharaf_definition_id', $sharafDef1->id)
            ->where('name', 'HOF')->first();

        SharafMember::create([
            'sharaf_id' => $sharaf2->id,
            'sharaf_position_id' => $hofPos2->id,
            'its_id' => 'ITS002',
            'sp_keyno' => 1,
        ]);

        SharafMember::create([
            'sharaf_id' => $sharaf2->id,
            'sharaf_position_id' => $fmPos1->id,
            'its_id' => 'ITS008',
            'sp_keyno' => 2,
        ]);

        // Members for sharaf3
        $hofPos3 = SharafPosition::where('sharaf_definition_id', $sharafDef2->id)
            ->where('name', 'HOF')->first();
        $fmPos3 = SharafPosition::where('sharaf_definition_id', $sharafDef2->id)
            ->where('name', 'FM')->first();

        SharafMember::create([
            'sharaf_id' => $sharaf3->id,
            'sharaf_position_id' => $hofPos3->id,
            'its_id' => 'ITS003',
            'sp_keyno' => 1,
        ]);

        SharafMember::create([
            'sharaf_id' => $sharaf3->id,
            'sharaf_position_id' => $fmPos3->id,
            'its_id' => 'ITS009',
            'sp_keyno' => 2,
        ]);

        // Members for sharaf4
        $hofPos4 = SharafPosition::where('sharaf_definition_id', $sharafDef3->id)
            ->where('name', 'HOF')->first();
        $fmPos4 = SharafPosition::where('sharaf_definition_id', $sharafDef3->id)
            ->where('name', 'FM')->first();

        SharafMember::create([
            'sharaf_id' => $sharaf4->id,
            'sharaf_position_id' => $hofPos4->id,
            'its_id' => 'ITS004',
            'sp_keyno' => 1,
        ]);

        SharafMember::create([
            'sharaf_id' => $sharaf4->id,
            'sharaf_position_id' => $fmPos4->id,
            'its_id' => 'ITS010',
            'sp_keyno' => 2,
        ]);

        // Seed Sharaf Clearances
        SharafClearance::create([
            'sharaf_id' => $sharaf2->id,
            'hof_its' => 'ITS002',
            'is_cleared' => true,
            'cleared_by_its' => 'ITS999',
            'cleared_at' => now()->subDays(2),
            'notes' => 'All clearances verified and approved.',
        ]);

        SharafClearance::create([
            'sharaf_id' => $sharaf4->id,
            'hof_its' => 'ITS004',
            'is_cleared' => true,
            'cleared_by_its' => 'ITS999',
            'cleared_at' => now()->subDays(1),
            'notes' => 'Cleared for Eid program.',
        ]);

        SharafClearance::create([
            'sharaf_id' => $sharaf1->id,
            'hof_its' => 'ITS001',
            'is_cleared' => false,
            'cleared_by_its' => null,
            'cleared_at' => null,
            'notes' => 'Pending clearance verification.',
        ]);

        SharafClearance::create([
            'sharaf_id' => $sharaf3->id,
            'hof_its' => 'ITS003',
            'is_cleared' => false,
            'cleared_by_its' => null,
            'cleared_at' => null,
            'notes' => null,
        ]);
    }
}
