<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Seed 22 realistic café/resto products with Indonesian Rupiah prices.
     * 5 of them are flagged as featured.
     */
    public function run(): void
    {
        $categories = Category::pluck('id', 'slug');

        $products = [
            // Kopi Panas
            ['cat' => 'kopi-panas', 'name' => 'Espresso', 'price' => 18000, 'featured' => false, 'desc' => 'Single shot espresso, bold and rich.'],
            ['cat' => 'kopi-panas', 'name' => 'Americano', 'price' => 22000, 'featured' => false, 'desc' => 'Espresso diluted with hot water.'],
            ['cat' => 'kopi-panas', 'name' => 'Cappuccino', 'price' => 28000, 'featured' => true, 'desc' => 'Espresso with steamed milk and foam.'],
            ['cat' => 'kopi-panas', 'name' => 'Caffe Latte', 'price' => 30000, 'featured' => true, 'desc' => 'Smooth espresso with steamed milk.'],

            // Kopi Dingin
            ['cat' => 'kopi-dingin', 'name' => 'Iced Americano', 'price' => 25000, 'featured' => false, 'desc' => 'Refreshing cold americano over ice.'],
            ['cat' => 'kopi-dingin', 'name' => 'Iced Latte', 'price' => 32000, 'featured' => false, 'desc' => 'Latte over ice for a cool boost.'],
            ['cat' => 'kopi-dingin', 'name' => 'Es Kopi Susu Gula Aren', 'price' => 28000, 'featured' => true, 'desc' => 'Iced milk coffee with palm sugar.'],

            // Non-Kopi
            ['cat' => 'non-kopi', 'name' => 'Matcha Latte', 'price' => 35000, 'featured' => true, 'desc' => 'Premium matcha with creamy milk.'],
            ['cat' => 'non-kopi', 'name' => 'Chocolate', 'price' => 30000, 'featured' => false, 'desc' => 'Hot chocolate with whipped cream.'],
            ['cat' => 'non-kopi', 'name' => 'Lemon Tea', 'price' => 22000, 'featured' => false, 'desc' => 'Fresh brewed tea with lemon.'],

            // Makanan Berat
            ['cat' => 'makanan-berat', 'name' => 'Nasi Goreng Spesial', 'price' => 42000, 'featured' => true, 'desc' => 'Fried rice with chicken, prawn, and egg.'],
            ['cat' => 'makanan-berat', 'name' => 'Mie Goreng', 'price' => 38000, 'featured' => false, 'desc' => 'Stir-fried noodles with vegetables.'],
            ['cat' => 'makanan-berat', 'name' => 'Ayam Geprek', 'price' => 35000, 'featured' => false, 'desc' => 'Crispy fried chicken smashed with sambal.'],
            ['cat' => 'makanan-berat', 'name' => 'Beef Burger', 'price' => 55000, 'featured' => false, 'desc' => 'Juicy beef patty with cheese and lettuce.'],

            // Makanan Ringan
            ['cat' => 'makanan-ringan', 'name' => 'Kentang Goreng', 'price' => 25000, 'featured' => false, 'desc' => 'Crispy golden french fries.'],
            ['cat' => 'makanan-ringan', 'name' => 'Pisang Goreng Keju', 'price' => 22000, 'featured' => false, 'desc' => 'Fried banana topped with cheese.'],
            ['cat' => 'makanan-ringan', 'name' => 'Roti Bakar Cokelat', 'price' => 20000, 'featured' => false, 'desc' => 'Toasted bread with chocolate spread.'],

            // Dessert
            ['cat' => 'dessert', 'name' => 'Tiramisu', 'price' => 38000, 'featured' => false, 'desc' => 'Classic Italian coffee dessert.'],
            ['cat' => 'dessert', 'name' => 'Cheesecake', 'price' => 35000, 'featured' => false, 'desc' => 'Rich and creamy cheesecake slice.'],

            // Paket
            ['cat' => 'paket', 'name' => 'Paket Hemat Kopi + Kentang', 'price' => 45000, 'featured' => false, 'desc' => 'Cappuccino plus fries combo.'],
            ['cat' => 'paket', 'name' => 'Paket Brunch', 'price' => 65000, 'featured' => false, 'desc' => 'Caffe latte, roti bakar, plus salad.'],

            // Add-on
            ['cat' => 'add-on', 'name' => 'Extra Shot Espresso', 'price' => 8000, 'featured' => false, 'desc' => 'Add an extra espresso shot.'],
            ['cat' => 'add-on', 'name' => 'Extra Whipped Cream', 'price' => 5000, 'featured' => false, 'desc' => 'Add a generous whipped cream topping.'],
        ];

        foreach ($products as $index => $product) {
            $slug = str($product['name'])->slug()->value();

            Product::firstOrCreate(
                ['slug' => $slug],
                [
                    'category_id' => $categories[$product['cat']],
                    'name' => $product['name'],
                    'description' => $product['desc'],
                    'price' => $product['price'],
                    'is_available' => true,
                    'is_featured' => $product['featured'],
                    'sort_order' => $index + 1,
                ],
            );
        }
    }
}
