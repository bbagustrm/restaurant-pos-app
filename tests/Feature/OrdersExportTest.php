<?php

use App\Exports\OrdersDetailExport;
use App\Exports\OrdersExport;
use App\Exports\OrdersSummaryExport;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

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

    $cat = Category::create(['name' => 'Kopi', 'slug' => 'kopi-'.uniqid(), 'sort_order' => 1]);
    $this->product = Product::create([
        'category_id' => $cat->id,
        'name' => 'Latte',
        'slug' => 'latte-'.uniqid(),
        'price' => 25000,
    ]);

    $this->order = Order::create([
        'order_number' => 'ORD-EXP-'.substr(uniqid(), -6),
        'cashier_id' => $this->cashier->id,
        'status' => 'completed',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'subtotal' => 25000,
        'tax_amount' => 0,
        'total_amount' => 25000,
    ]);
    OrderItem::create([
        'order_id' => $this->order->id,
        'product_id' => $this->product->id,
        'product_name' => 'Latte',
        'product_price' => 25000,
        'quantity' => 1,
    ]);
});

it('OrdersExport implements WithMultipleSheets and ShouldQueue', function () {
    $export = new OrdersExport(['from' => null, 'until' => null]);
    expect($export)->toBeInstanceOf(WithMultipleSheets::class)
        ->and($export)->toBeInstanceOf(ShouldQueue::class)
        ->and($export->sheets())->toHaveCount(2);
});

it('OrdersSummaryExport produces one row per day in the range', function () {
    $filters = [
        'from' => now()->subDays(2)->toDateString(),
        'until' => now()->toDateString(),
        'cashier_id' => null,
        'payment_method' => null,
    ];
    $export = new OrdersSummaryExport($filters);
    $rows = $export->collection();
    expect($rows)->toHaveCount(3);

    $today = now()->toDateString();
    $todayRow = $rows->firstWhere('day', $today);

    expect($todayRow['revenue'])->toBe(25000)
        ->and($todayRow['transactions'])->toBe(1)
        ->and($todayRow['average'])->toBe(25000);
});

it('OrdersSummaryExport headings match the spec', function () {
    $export = new OrdersSummaryExport(['from' => null, 'until' => null]);
    expect($export->headings())->toBe(['Tanggal', 'Revenue', 'Jumlah Transaksi', 'Rata-rata Transaksi'])
        ->and($export->title())->toBe('Ringkasan');
});

it('OrdersDetailExport returns the filtered order query', function () {
    $detail = new OrdersDetailExport([
        'from' => now()->toDateString(),
        'until' => now()->toDateString(),
        'cashier_id' => null,
        'payment_method' => null,
    ]);

    expect($detail->query()->count())->toBe(1)
        ->and($detail->title())->toBe('Detail Transaksi');
});
