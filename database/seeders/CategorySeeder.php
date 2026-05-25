<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Seed the 8 menu categories (icon names from Heroicons).
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Kopi Panas', 'slug' => 'kopi-panas', 'icon' => 'fire', 'color' => '#7C2D12', 'sort_order' => 1],
            ['name' => 'Kopi Dingin', 'slug' => 'kopi-dingin', 'icon' => 'cube-transparent', 'color' => '#0EA5E9', 'sort_order' => 2],
            ['name' => 'Non-Kopi', 'slug' => 'non-kopi', 'icon' => 'beaker', 'color' => '#10B981', 'sort_order' => 3],
            ['name' => 'Makanan Berat', 'slug' => 'makanan-berat', 'icon' => 'cake', 'color' => '#DC2626', 'sort_order' => 4],
            ['name' => 'Makanan Ringan', 'slug' => 'makanan-ringan', 'icon' => 'sparkles', 'color' => '#F59E0B', 'sort_order' => 5],
            ['name' => 'Dessert', 'slug' => 'dessert', 'icon' => 'heart', 'color' => '#EC4899', 'sort_order' => 6],
            ['name' => 'Paket', 'slug' => 'paket', 'icon' => 'gift', 'color' => '#8B5CF6', 'sort_order' => 7],
            ['name' => 'Add-on', 'slug' => 'add-on', 'icon' => 'plus-circle', 'color' => '#6B7280', 'sort_order' => 8],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
