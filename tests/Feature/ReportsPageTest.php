<?php

use App\Filament\Pages\Reports;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RoleSeeder::class);
    seed();

    $this->admin = User::create([
        'name' => 'Admin',
        'email' => 'admin-'.uniqid().'@test.local',
        'password' => 'password',
    ]);
    $this->admin->assignRole('super_admin');

    $this->cashier = User::create([
        'name' => 'Kasir',
        'email' => 'kasir-'.uniqid().'@test.local',
        'password' => 'password',
    ]);
    $this->cashier->assignRole('cashier');

    $category = Category::create(['name' => 'Kopi', 'slug' => 'kopi-'.uniqid(), 'sort_order' => 1]);
    $this->product = Product::create([
        'category_id' => $category->id,
        'name' => 'Latte',
        'slug' => 'latte-'.uniqid(),
        'price' => 25000,
    ]);

    actingAs($this->admin);
});

function makeReportOrder(array $overrides = []): Order
{
    $order = Order::create(array_merge([
        'order_number' => 'ORD-R-'.substr(uniqid(), -8),
        'cashier_id' => test()->cashier->id,
        'status' => 'completed',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'subtotal' => 25000, 'tax_amount' => 0, 'total_amount' => 25000,
    ], $overrides));

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => test()->product->id,
        'product_name' => 'Latte',
        'product_price' => 25000,
        'quantity' => 1,
    ]);

    return $order;
}

it('renders the reports page for super_admin', function () {
    Livewire::test(Reports::class)->assertOk();
});

it('blocks non super_admin from accessing the reports page', function () {
    actingAs($this->cashier);
    expect(Reports::canAccess())->toBeFalse();
});

it('exposes the page under the "Laporan" navigation group', function () {
    expect(Reports::getNavigationGroup())->toBe('Laporan');
});

it('computes stats for the current filter range', function () {
    makeReportOrder(['total_amount' => 50000]);
    makeReportOrder(['total_amount' => 30000]);
    makeReportOrder(['status' => 'cancelled', 'total_amount' => 999]); // excluded

    $page = new Reports;
    $page->filters['from'] = now()->toDateString();
    $page->filters['until'] = now()->toDateString();

    $stats = $page->getStats();
    expect($stats['revenue'])->toBe(80000)
        ->and($stats['transactions'])->toBe(2)
        ->and($stats['average'])->toBe(40000)
        ->and($stats['itemsSold'])->toBe(2);
});

it('filters by cashier', function () {
    $other = User::create([
        'name' => 'Other',
        'email' => 'other-'.uniqid().'@test.local',
        'password' => 'password',
    ]);
    $other->assignRole('cashier');

    makeReportOrder(); // by $this->cashier
    makeReportOrder(['cashier_id' => $other->id, 'order_number' => 'OR-O-'.substr(uniqid(), -8)]);

    $page = new Reports;
    $page->filters['from'] = now()->toDateString();
    $page->filters['until'] = now()->toDateString();
    $page->filters['cashier_id'] = $this->cashier->id;

    expect($page->buildOrdersQuery()->count())->toBe(1);
});

it('filters by payment method', function () {
    makeReportOrder(['payment_method' => 'cash']);
    makeReportOrder(['payment_method' => 'qris']);

    $page = new Reports;
    $page->filters['from'] = now()->toDateString();
    $page->filters['until'] = now()->toDateString();
    $page->filters['payment_method'] = 'cash';

    expect($page->buildOrdersQuery()->count())->toBe(1);
});

it('produces chart buckets matching the date range', function () {
    $page = new Reports;
    $page->filters['from'] = now()->subDays(6)->toDateString();
    $page->filters['until'] = now()->toDateString();

    $chart = $page->getChartData();
    expect($chart['labels'])->toHaveCount(7)
        ->and($chart['data'])->toHaveCount(7);
});
