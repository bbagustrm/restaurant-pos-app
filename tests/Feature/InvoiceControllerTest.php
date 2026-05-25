<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RoleSeeder::class);
    seed();

    $this->cashier = User::create([
        'name' => 'Kasir',
        'email' => 'kasir-'.uniqid().'@test.local',
        'password' => 'password',
    ]);
    $this->cashier->assignRole('cashier');

    $this->admin = User::create([
        'name' => 'Admin',
        'email' => 'admin-'.uniqid().'@test.local',
        'password' => 'password',
    ]);
    $this->admin->assignRole('super_admin');

    $category = Category::create(['name' => 'Kopi', 'slug' => 'kopi-'.uniqid(), 'sort_order' => 1]);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Latte',
        'slug' => 'latte-'.uniqid(),
        'price' => 25000,
    ]);

    $this->order = Order::create([
        'order_number' => 'ORD-INV-'.substr(uniqid(), -6),
        'cashier_id' => $this->cashier->id,
        'status' => 'completed',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'subtotal' => 25000,
        'tax_amount' => 2750,
        'total_amount' => 27750,
    ]);
    OrderItem::create([
        'order_id' => $this->order->id,
        'product_id' => $product->id,
        'product_name' => 'Latte',
        'product_price' => 25000,
        'quantity' => 1,
    ]);
});

it('streams the invoice PDF inline for the cashier owner', function () {
    actingAs($this->cashier);

    $response = get(route('orders.invoice', $this->order));
    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

it('downloads the invoice PDF when ?download=1 is passed', function () {
    actingAs($this->cashier);

    $response = get(route('orders.invoice', $this->order).'?download=1');
    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
});

it('allows super_admin to view any order invoice', function () {
    actingAs($this->admin);

    get(route('orders.invoice', $this->order))->assertOk();
});

it('forbids other cashiers from viewing the invoice', function () {
    $other = User::create([
        'name' => 'Other',
        'email' => 'other-'.uniqid().'@test.local',
        'password' => 'password',
    ]);
    $other->assignRole('cashier');

    actingAs($other);
    get(route('orders.invoice', $this->order))->assertForbidden();
});

it('streams the thermal receipt for the cashier owner', function () {
    actingAs($this->cashier);
    get(route('orders.receipt', $this->order))->assertOk();
});
