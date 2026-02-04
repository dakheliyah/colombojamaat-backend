<?php

namespace Database\Seeders;

use App\Models\SharafType;
use Illuminate\Database\Seeder;

class SharafTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            'Ziyafat',
            'Nikah',
            'Misaq',
            'Nisbat',
            'Hadiyat',
            'Mafsuhiyat',
            'Rahat',
            'General',
            'Other',
        ];

        foreach ($types as $name) {
            SharafType::firstOrCreate(
                ['name' => $name],
                ['name' => $name]
            );
        }
    }
}
