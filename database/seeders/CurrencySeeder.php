<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'LKR', 'name' => 'Sri Lankan Rupee', 'sort_order' => 1],
            ['code' => 'USD', 'name' => 'US Dollar', 'sort_order' => 2],
            ['code' => 'AED', 'name' => 'UAE Dirham', 'sort_order' => 3],
            ['code' => 'GBP', 'name' => 'British Pound', 'sort_order' => 4],
            ['code' => 'EUR', 'name' => 'Euro', 'sort_order' => 5],
            ['code' => 'SAR', 'name' => 'Saudi Riyal', 'sort_order' => 6],
            ['code' => 'INR', 'name' => 'Indian Rupee', 'sort_order' => 7],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(
                ['code' => $currency['code']],
                [
                    'name' => $currency['name'],
                    'is_active' => true,
                    'sort_order' => $currency['sort_order'],
                ]
            );
        }
    }
}
