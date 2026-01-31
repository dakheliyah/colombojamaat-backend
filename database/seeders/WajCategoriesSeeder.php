<?php

namespace Database\Seeders;

use App\Models\WajCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WajCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Populates waj_categories for miqaat_id=1 with INR currency.
     * Order: highest amount = 1, lowest amount = n
     */
    public function run(): void
    {
        $miqaatId = 1;
        $currency = 'INR';

        // Categories ordered from highest amount (order=1) to lowest (order=12)
        $categories = [
            [
                'name' => 'Taiyebi',
                'low_bar' => 99999999,
                'upper_bar' => null, // No upper limit for highest category
                'order' => 1,
            ],
            [
                'name' => 'Zainee',
                'low_bar' => 70000000,
                'upper_bar' => 99999998.99,
                'order' => 2,
            ],
            [
                'name' => 'Ezzi',
                'low_bar' => 30000000,
                'upper_bar' => 69999999.99,
                'order' => 3,
            ],
            [
                'name' => 'Mohammedi',
                'low_bar' => 10000000,
                'upper_bar' => 29999999.99,
                'order' => 4,
            ],
            [
                'name' => 'Fakhri',
                'low_bar' => 6000000,
                'upper_bar' => 9999999.99,
                'order' => 5,
            ],
            [
                'name' => 'Imadi',
                'low_bar' => 3000000,
                'upper_bar' => 5999999.99,
                'order' => 6,
            ],
            [
                'name' => 'Burhani',
                'low_bar' => 1500000,
                'upper_bar' => 2999999.99,
                'order' => 7,
            ],
            [
                'name' => 'Husaini',
                'low_bar' => 700000,
                'upper_bar' => 1499999.99,
                'order' => 8,
            ],
            [
                'name' => 'Saifee',
                'low_bar' => 400000,
                'upper_bar' => 699999.99,
                'order' => 9,
            ],
            [
                'name' => 'Najmi',
                'low_bar' => 200000,
                'upper_bar' => 399999.99,
                'order' => 10,
            ],
            [
                'name' => 'Hakimi',
                'low_bar' => 100000,
                'upper_bar' => 199999.99,
                'order' => 11,
            ],
            [
                'name' => 'Badri',
                'low_bar' => 1,
                'upper_bar' => 99999.99,
                'order' => 12,
            ],
        ];

        foreach ($categories as $category) {
            WajCategory::updateOrCreate(
                [
                    'miqaat_id' => $miqaatId,
                    'name' => $category['name'],
                ],
                [
                    'currency' => $currency,
                    'low_bar' => $category['low_bar'],
                    'upper_bar' => $category['upper_bar'],
                    'hex_color' => null,
                    'order' => $category['order'],
                ]
            );
        }
    }
}
