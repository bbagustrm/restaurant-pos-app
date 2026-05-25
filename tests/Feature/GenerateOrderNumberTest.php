<?php

use App\Actions\Pos\GenerateOrderNumber;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;

use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RoleSeeder::class);

    $this->cashier = User::create([
        'name' => 'Kasir',
        'email' => 'kasir-'.uniqid().'@test.local',
        'password' => 'password',
    ]);
    $this->cashier->assignRole('cashier');

    $this->category = Category::create([
        'name' => 'Kopi',
        'slug' => 'kopi-'.uniqid(),
        'sort_order' => 1,
    ]);
    $this->product = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Latte',
        'slug' => 'latte-'.uniqid(),
        'price' => 25000,
    ]);
});

it('formats the order number as ORD-YYYYMMDD-0001 on first order of the day', function () {
    $now = Carbon::create(2026, 1, 15, 9, 0, 0);

    expect((new GenerateOrderNumber)->handle($now))->toBe('ORD-20260115-0001');
});

it('increments the daily counter sequentially', function () {
    $now = Carbon::create(2026, 1, 15, 10, 0, 0);

    $generator = new GenerateOrderNumber;

    expect($generator->handle($now))->toBe('ORD-20260115-0001');

    Order::create([
        'order_number' => $generator->handle($now),
        'cashier_id' => $this->cashier->id,
        'subtotal' => 0, 'tax_amount' => 0, 'total_amount' => 0,
    ])->forceFill(['created_at' => $now, 'updated_at' => $now])->save();

    expect($generator->handle($now))->toBe('ORD-20260115-0002');
});

it('resets the counter on a new day', function () {
    $day1 = Carbon::create(2026, 1, 15, 9, 0, 0);
    $day2 = Carbon::create(2026, 1, 16, 9, 0, 0);

    foreach (['ORD-20260115-0001', 'ORD-20260115-0002'] as $orderNumber) {
        Order::create([
            'order_number' => $orderNumber,
            'cashier_id' => $this->cashier->id,
            'subtotal' => 0, 'tax_amount' => 0, 'total_amount' => 0,
        ])->forceFill(['created_at' => $day1, 'updated_at' => $day1])->save();
    }

    expect((new GenerateOrderNumber)->handle($day2))->toBe('ORD-20260116-0001');
});

it('pads the counter to 4 digits', function () {
    $now = Carbon::create(2026, 1, 15, 9, 0, 0);

    // Insert 9 orders for today.
    foreach (range(1, 9) as $i) {
        Order::create([
            'order_number' => sprintf('ORD-20260115-%04d', $i),
            'cashier_id' => $this->cashier->id,
            'subtotal' => 0, 'tax_amount' => 0, 'total_amount' => 0,
        ])->forceFill(['created_at' => $now, 'updated_at' => $now])->save();
    }

    expect((new GenerateOrderNumber)->handle($now))->toBe('ORD-20260115-0010');
});
