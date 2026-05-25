<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;

it('formats rupiah helper as Rp 25.000', function () {
    expect(rupiah(25000))->toBe('Rp 25.000')
        ->and(rupiah(1500000))->toBe('Rp 1.500.000')
        ->and(rupiah(0))->toBe('Rp 0');
});

it('uses UUID primary keys on all domain models', function () {
    $category = Category::create(['name' => 'Kopi', 'slug' => 'kopi']);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Latte',
        'slug' => 'latte',
        'price' => 25000,
    ]);
    $table = RestaurantTable::create(['name' => 'Meja 1']);
    $user = User::create([
        'name' => 'Kasir',
        'email' => 'kasir-'.uniqid().'@test.local',
        'password' => 'secret',
    ]);
    $order = Order::create([
        'order_number' => 'ORD-T-'.uniqid(),
        'cashier_id' => $user->id,
        'table_id' => $table->id,
    ]);
    $item = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_price' => $product->price,
        'quantity' => 1,
    ]);

    foreach ([$category, $product, $table, $user, $order, $item] as $model) {
        expect($model->id)->toBeString()->toHaveLength(36);
    }
});

it('wires up Category relationships and casts', function () {
    $category = Category::create([
        'name' => 'Minuman',
        'slug' => 'minuman',
        'is_active' => 1,
    ]);
    Product::create([
        'category_id' => $category->id,
        'name' => 'Espresso',
        'slug' => 'espresso',
        'price' => 18000,
    ]);

    expect($category->is_active)->toBeBool()->toBeTrue()
        ->and($category->products)->toHaveCount(1)
        ->and(in_array(SoftDeletes::class, class_uses_recursive($category), true))->toBeFalse();
});

it('wires up Product relationships, casts, soft deletes, and accessor', function () {
    $category = Category::create(['name' => 'Kopi', 'slug' => 'kopi']);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Cappuccino',
        'slug' => 'cappuccino',
        'price' => 30000,
        'is_available' => 1,
        'is_featured' => 0,
    ]);

    expect($product->category->id)->toBe($category->id)
        ->and($product->is_available)->toBeBool()->toBeTrue()
        ->and($product->is_featured)->toBeBool()->toBeFalse()
        ->and($product->price)->toBe('30000.00')
        ->and($product->formatted_price)->toBe('Rp 30.000')
        ->and(in_array(SoftDeletes::class, class_uses_recursive($product), true))->toBeTrue();

    $product->delete();
    expect(Product::find($product->id))->toBeNull()
        ->and(Product::withTrashed()->find($product->id))->not->toBeNull();
});

it('wires up RestaurantTable hasMany orders', function () {
    $table = RestaurantTable::create(['name' => 'Bar 1', 'capacity' => 2]);
    $user = User::create([
        'name' => 'Kasir',
        'email' => 'kasir-'.uniqid().'@test.local',
        'password' => 'secret',
    ]);
    Order::create([
        'order_number' => 'ORD-T-'.uniqid(),
        'cashier_id' => $user->id,
        'table_id' => $table->id,
    ]);

    expect($table->orders)->toHaveCount(1)
        ->and($table->capacity)->toBeInt()->toBe(2);
});

it('wires up Order relationships, casts, and formatted_total accessor', function () {
    $user = User::create([
        'name' => 'Kasir',
        'email' => 'kasir-'.uniqid().'@test.local',
        'password' => 'secret',
    ]);
    $table = RestaurantTable::create(['name' => 'Meja 5']);
    $category = Category::create(['name' => 'Kopi', 'slug' => 'kopi']);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Latte',
        'slug' => 'latte',
        'price' => 25000,
    ]);
    $order = Order::create([
        'order_number' => 'ORD-T-'.uniqid(),
        'cashier_id' => $user->id,
        'table_id' => $table->id,
        'subtotal' => 25000,
        'tax_amount' => 2750,
        'total_amount' => 27750,
    ]);
    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_price' => $product->price,
        'quantity' => 1,
    ]);

    expect($order->cashier->id)->toBe($user->id)
        ->and($order->table->id)->toBe($table->id)
        ->and($order->orderItems)->toHaveCount(1)
        ->and($order->subtotal)->toBe('25000.00')
        ->and($order->total_amount)->toBe('27750.00')
        ->and($order->formatted_total)->toBe('Rp 27.750');
});

it('wires up OrderItem relationships and snapshots product details', function () {
    $user = User::create([
        'name' => 'Kasir',
        'email' => 'kasir-'.uniqid().'@test.local',
        'password' => 'secret',
    ]);
    $category = Category::create(['name' => 'Kopi', 'slug' => 'kopi']);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Americano',
        'slug' => 'americano',
        'price' => 22000,
    ]);
    $order = Order::create([
        'order_number' => 'ORD-T-'.uniqid(),
        'cashier_id' => $user->id,
    ]);
    $item = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_price' => $product->price,
        'quantity' => 2,
    ]);

    expect($item->order->id)->toBe($order->id)
        ->and($item->product->id)->toBe($product->id)
        ->and($item->product_price)->toBe('22000.00')
        ->and($item->quantity)->toBeInt()->toBe(2)
        ->and($item->product_name)->toBe('Americano');
});
