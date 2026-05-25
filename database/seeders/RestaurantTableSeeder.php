<?php

namespace Database\Seeders;

use App\Models\RestaurantTable;
use Illuminate\Database\Seeder;

class RestaurantTableSeeder extends Seeder
{
    /**
     * Seed dining tables: Meja 1–6, Bar 1, Bar 2, Takeaway Counter.
     */
    public function run(): void
    {
        $tables = [
            ['name' => 'Meja 1', 'capacity' => 4],
            ['name' => 'Meja 2', 'capacity' => 4],
            ['name' => 'Meja 3', 'capacity' => 4],
            ['name' => 'Meja 4', 'capacity' => 6],
            ['name' => 'Meja 5', 'capacity' => 6],
            ['name' => 'Meja 6', 'capacity' => 8],
            ['name' => 'Bar 1', 'capacity' => 2],
            ['name' => 'Bar 2', 'capacity' => 2],
            ['name' => 'Takeaway Counter', 'capacity' => 1],
        ];

        foreach ($tables as $table) {
            RestaurantTable::firstOrCreate(['name' => $table['name']], $table + ['status' => 'available']);
        }
    }
}
