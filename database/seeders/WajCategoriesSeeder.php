<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WajCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Populates waj_categories for miqaat_id=1 with INR currency.
     * Data sourced from waj_categories.csv
     */
    public function run(): void
    {
        // Categories data from CSV file
        $categories = [
            [
                'wc_id' => 2,
                'miqaat_id' => 1,
                'currency' => 'INR',
                'name' => 'Taiyebi',
                'low_bar' => 99999999.00,
                'upper_bar' => null,
                'hex_color' => null,
                'order' => 1,
                'created_at' => '2026-01-31 05:22:30',
                'updated_at' => '2026-01-31 05:22:30',
            ],
            [
                'wc_id' => 3,
                'miqaat_id' => 1,
                'currency' => 'INR',
                'name' => 'Zainee',
                'low_bar' => 70000000.00,
                'upper_bar' => 99999998.99,
                'hex_color' => null,
                'order' => 2,
                'created_at' => '2026-01-31 05:22:30',
                'updated_at' => '2026-01-31 05:22:30',
            ],
            [
                'wc_id' => 4,
                'miqaat_id' => 1,
                'currency' => 'INR',
                'name' => 'Ezzi',
                'low_bar' => 30000000.00,
                'upper_bar' => 69999999.99,
                'hex_color' => null,
                'order' => 3,
                'created_at' => '2026-01-31 05:22:30',
                'updated_at' => '2026-01-31 05:22:30',
            ],
            [
                'wc_id' => 5,
                'miqaat_id' => 1,
                'currency' => 'INR',
                'name' => 'Mohammedi',
                'low_bar' => 10000000.00,
                'upper_bar' => 29999999.99,
                'hex_color' => null,
                'order' => 4,
                'created_at' => '2026-01-31 05:22:30',
                'updated_at' => '2026-01-31 05:22:30',
            ],
            [
                'wc_id' => 6,
                'miqaat_id' => 1,
                'currency' => 'INR',
                'name' => 'Fakhri',
                'low_bar' => 6000000.00,
                'upper_bar' => 9999999.99,
                'hex_color' => null,
                'order' => 5,
                'created_at' => '2026-01-31 05:22:30',
                'updated_at' => '2026-01-31 05:22:30',
            ],
            [
                'wc_id' => 7,
                'miqaat_id' => 1,
                'currency' => 'INR',
                'name' => 'Imadi',
                'low_bar' => 3000000.00,
                'upper_bar' => 5999999.99,
                'hex_color' => null,
                'order' => 6,
                'created_at' => '2026-01-31 05:22:30',
                'updated_at' => '2026-01-31 05:22:30',
            ],
            [
                'wc_id' => 8,
                'miqaat_id' => 1,
                'currency' => 'INR',
                'name' => 'Burhani',
                'low_bar' => 1500000.00,
                'upper_bar' => 2999999.99,
                'hex_color' => null,
                'order' => 7,
                'created_at' => '2026-01-31 05:22:30',
                'updated_at' => '2026-01-31 05:22:30',
            ],
            [
                'wc_id' => 9,
                'miqaat_id' => 1,
                'currency' => 'INR',
                'name' => 'Husaini',
                'low_bar' => 700000.00,
                'upper_bar' => 1499999.99,
                'hex_color' => null,
                'order' => 8,
                'created_at' => '2026-01-31 05:22:30',
                'updated_at' => '2026-01-31 05:22:30',
            ],
            [
                'wc_id' => 10,
                'miqaat_id' => 1,
                'currency' => 'INR',
                'name' => 'Saifee',
                'low_bar' => 400000.00,
                'upper_bar' => 699999.99,
                'hex_color' => null,
                'order' => 9,
                'created_at' => '2026-01-31 05:22:30',
                'updated_at' => '2026-01-31 05:22:30',
            ],
            [
                'wc_id' => 11,
                'miqaat_id' => 1,
                'currency' => 'INR',
                'name' => 'Najmi',
                'low_bar' => 200000.00,
                'upper_bar' => 399999.99,
                'hex_color' => null,
                'order' => 10,
                'created_at' => '2026-01-31 05:22:30',
                'updated_at' => '2026-01-31 05:22:30',
            ],
            [
                'wc_id' => 12,
                'miqaat_id' => 1,
                'currency' => 'INR',
                'name' => 'Hakimi',
                'low_bar' => 100000.00,
                'upper_bar' => 199999.99,
                'hex_color' => null,
                'order' => 11,
                'created_at' => '2026-01-31 05:22:30',
                'updated_at' => '2026-01-31 05:22:30',
            ],
            [
                'wc_id' => 13,
                'miqaat_id' => 1,
                'currency' => 'INR',
                'name' => 'Badri',
                'low_bar' => 1.00,
                'upper_bar' => 99999.99,
                'hex_color' => null,
                'order' => 12,
                'created_at' => '2026-01-31 05:22:30',
                'updated_at' => '2026-01-31 05:22:30',
            ],
        ];

        foreach ($categories as $category) {
            // Check if record exists
            $exists = DB::table('waj_categories')->where('wc_id', $category['wc_id'])->exists();
            
            if ($exists) {
                // Update existing record
                DB::table('waj_categories')
                    ->where('wc_id', $category['wc_id'])
                    ->update([
                        'miqaat_id' => $category['miqaat_id'],
                        'currency' => $category['currency'],
                        'name' => $category['name'],
                        'low_bar' => $category['low_bar'],
                        'upper_bar' => $category['upper_bar'],
                        'hex_color' => $category['hex_color'],
                        'order' => $category['order'],
                        'updated_at' => $category['updated_at'],
                    ]);
            } else {
                // Insert new record with specific wc_id
                DB::table('waj_categories')->insert($category);
            }
        }
    }
}
