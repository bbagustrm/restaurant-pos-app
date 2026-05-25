<?php

use App\Filament\Widgets\OrdersChart;
use App\Filament\Widgets\RecentOrdersTable;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\TopProductsTable;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RoleSeeder::class);

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

    actingAs($this->admin);
});

function makeCompletedOrder(int $totalAmount, ?Carbon $createdAt = null, int $itemQty = 1): Order
{
    $order = Order::create([
        'order_number' => 'ORD-'.substr(uniqid(), -8),
        'cashier_id' => test()->cashier->id,
        'status' => 'completed',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'subtotal' => $totalAmount,
        'tax_amount' => 0,
        'total_amount' => $totalAmount,
    ]);

    if ($createdAt) {
        $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
    }

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => test()->product->id,
        'product_name' => test()->product->name,
        'product_price' => test()->product->price,
        'quantity' => $itemQty,
    ]);

    return $order;
}

it('renders the StatsOverview widget without errors', function () {
    makeCompletedOrder(100000);

    Livewire::test(StatsOverview::class)->assertOk();
});

it('shows today revenue from completed paid orders only', function () {
    // Today: 2 completed (counted) + 1 cancelled (not counted)
    makeCompletedOrder(50000);
    makeCompletedOrder(30000);
    Order::create([
        'order_number' => 'ORD-CANCEL'.substr(uniqid(), -3),
        'cashier_id' => $this->cashier->id,
        'status' => 'cancelled',
        'payment_status' => 'unpaid',
        'subtotal' => 999999,
        'tax_amount' => 0,
        'total_amount' => 999999,
    ]);

    Livewire::test(StatsOverview::class)
        ->assertSeeText(rupiah(80000));
});

it('counts today orders excluding cancelled', function () {
    makeCompletedOrder(10000);
    makeCompletedOrder(20000);
    Order::create([
        'order_number' => 'ORD-X'.substr(uniqid(), -6),
        'cashier_id' => $this->cashier->id,
        'status' => 'cancelled',
        'payment_status' => 'unpaid',
        'subtotal' => 0, 'tax_amount' => 0, 'total_amount' => 0,
    ]);

    Livewire::test(StatsOverview::class)
        ->assertSeeText('Order Hari Ini')
        ->assertSeeText('2'); // count of non-cancelled today
});

it('renders the OrdersChart widget without errors', function () {
    makeCompletedOrder(50000);

    Livewire::test(OrdersChart::class)->assertOk();
});

it('produces 30 data points for the OrdersChart', function () {
    makeCompletedOrder(50000);

    $widget = new OrdersChart;
    $reflection = new ReflectionMethod($widget, 'getData');
    $reflection->setAccessible(true);
    $data = $reflection->invoke($widget);

    expect($data['datasets'][0]['data'])->toHaveCount(30)
        ->and($data['labels'])->toHaveCount(30);
});

it('OrdersChart only sums completed paid orders', function () {
    makeCompletedOrder(100000);
    Order::create([
        'order_number' => 'ORD-P'.substr(uniqid(), -6),
        'cashier_id' => $this->cashier->id,
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'subtotal' => 999999,
        'tax_amount' => 0,
        'total_amount' => 999999,
    ]);

    $widget = new OrdersChart;
    $reflection = new ReflectionMethod($widget, 'getData');
    $reflection->setAccessible(true);
    $data = $reflection->invoke($widget);

    // Today's bucket (last index) should equal exactly 100000.
    expect($data['datasets'][0]['data'][29])->toBe(100000);
});

it('renders the TopProductsTable widget with month-to-date sums', function () {
    makeCompletedOrder(50000, itemQty: 3);
    makeCompletedOrder(50000, itemQty: 2);

    Livewire::test(TopProductsTable::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$this->product]);
});

it('TopProductsTable limits to 5 records', function () {
    // Create 6 distinct products with sales
    $products = [];
    for ($i = 0; $i < 6; $i++) {
        $p = Product::create([
            'category_id' => $this->category->id,
            'name' => "Product {$i}",
            'slug' => "product-{$i}-".uniqid(),
            'price' => 10000,
        ]);
        $products[] = $p;

        $o = Order::create([
            'order_number' => 'ORD-X'.$i.'-'.substr(uniqid(), -3),
            'cashier_id' => $this->cashier->id,
            'status' => 'completed',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'subtotal' => 10000, 'tax_amount' => 0, 'total_amount' => 10000,
        ]);

        OrderItem::create([
            'order_id' => $o->id,
            'product_id' => $p->id,
            'product_name' => $p->name,
            'product_price' => $p->price,
            'quantity' => $i + 1, // higher index = more sold
        ]);
    }

    // Top 5 by qty are products 1..5 (the ones with highest qty); product 0 is the 6th.
    $top = collect($products)->slice(1, 5)->all();
    $bottom = $products[0];

    Livewire::test(TopProductsTable::class)
        ->assertCanSeeTableRecords($top)
        ->assertCanNotSeeTableRecords([$bottom]);
});

it('renders the RecentOrdersTable widget with pagination set to 10 per page', function () {
    foreach (range(1, 12) as $n) {
        makeCompletedOrder(10000 * $n);
    }

    $component = Livewire::test(RecentOrdersTable::class)->assertOk();
    $table = $component->instance()->getTable();

    expect($table->getPaginationPageOptions())->toBe([10])
        ->and($table->getDefaultPaginationPageOption())->toBe(10);
});

it('exposes correct widget sort order', function () {
    expect(StatsOverview::getSort())->toBe(1)
        ->and(OrdersChart::getSort())->toBe(2)
        ->and(TopProductsTable::getSort())->toBe(3)
        ->and(RecentOrdersTable::getSort())->toBe(4);
});
