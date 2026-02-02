<?php

namespace Database\Seeders;

use App\Models\CurrencyConversion;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CurrencyConversionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $conversions = [
            [
                'from_currency' => 'INR',
                'to_currency' => 'LKR',
                'rate' => 3.400000,
                'effective_date' => '2026-01-01',
                'expiry_date' => null,
                'is_active' => true,
                'source' => 'manual',
                'notes' => null,
            ],
        ];

        foreach ($conversions as $conversion) {
            CurrencyConversion::updateOrCreate(
                [
                    'from_currency' => $conversion['from_currency'],
                    'to_currency' => $conversion['to_currency'],
                    'effective_date' => $conversion['effective_date'],
                ],
                $conversion
            );
        }
    }
}
