<?php

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\User;
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

    $this->table = RestaurantTable::create(['name' => 'Meja 1', 'capacity' => 4]);

    $category = Category::create(['name' => 'Kopi', 'slug' => 'kopi-'.uniqid(), 'sort_order' => 1]);
    $this->product = Product::create([
        'category_id' => $category->id,
        'name' => 'Latte',
        'slug' => 'latte-'.uniqid(),
        'price' => 25000,
    ]);

    actingAs($this->admin);
});

function makeOrder(array $overrides = []): Order
{
    $order = Order::create(array_merge([
        'order_number' => 'ORD-T-'.uniqid(),
        'cashier_id' => test()->cashier->id,
        'table_id' => test()->table->id,
        'status' => 'completed',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'subtotal' => 25000,
        'tax_amount' => 2750,
        'total_amount' => 27750,
    ], $overrides));

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => test()->product->id,
        'product_name' => test()->product->name,
        'product_price' => test()->product->price,
        'quantity' => 1,
    ]);

    return $order;
}

it('renders the order list page', function () {
    makeOrder();

    Livewire::test(ListOrders::class)
        ->assertOk()
        ->assertCanSeeTableRecords(Order::all());
});

it('disables create, edit, and delete on OrderResource', function () {
    expect(OrderResource::canCreate())->toBeFalse()
        ->and(OrderResource::canEdit(makeOrder()))->toBeFalse()
        ->and(OrderResource::canDelete(makeOrder()))->toBeFalse()
        ->and(OrderResource::canDeleteAny())->toBeFalse();
});

it('does not register an edit or create page', function () {
    $pages = OrderResource::getPages();

    expect($pages)->toHaveKey('index')
        ->and($pages)->not->toHaveKey('create')
        ->and($pages)->not->toHaveKey('edit');
});

it('list page header has no create action', function () {
    $page = new ListOrders;
    $reflection = new ReflectionClass($page);
    $method = $reflection->getMethod('getHeaderActions');
    $method->setAccessible(true);

    expect($method->invoke($page))->toBe([]);
});

it('filters orders by status', function () {
    $completed = makeOrder(['order_number' => 'ORD-A-'.uniqid(), 'status' => 'completed']);
    $cancelled = makeOrder(['order_number' => 'ORD-B-'.uniqid(), 'status' => 'cancelled']);

    Livewire::test(ListOrders::class)
        ->filterTable('status', ['completed'])
        ->assertCanSeeTableRecords([$completed])
        ->assertCanNotSeeTableRecords([$cancelled]);
});

it('filters orders by payment method', function () {
    $cash = makeOrder(['order_number' => 'ORD-A-'.uniqid(), 'payment_method' => 'cash']);
    $qris = makeOrder(['order_number' => 'ORD-B-'.uniqid(), 'payment_method' => 'qris']);

    Livewire::test(ListOrders::class)
        ->filterTable('payment_method', ['cash'])
        ->assertCanSeeTableRecords([$cash])
        ->assertCanNotSeeTableRecords([$qris]);
});

it('filters orders by cashier', function () {
    $other = User::create([
        'name' => 'Kasir Lain',
        'email' => 'other-'.uniqid().'@test.local',
        'password' => 'password',
    ]);
    $other->assignRole('cashier');

    $myOrder = makeOrder(['order_number' => 'ORD-MY-'.uniqid()]);
    $otherOrder = Order::create([
        'order_number' => 'ORD-OT-'.uniqid(),
        'cashier_id' => $other->id,
        'status' => 'completed',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'subtotal' => 0, 'tax_amount' => 0, 'total_amount' => 0,
    ]);

    Livewire::test(ListOrders::class)
        ->filterTable('cashier_id', $this->cashier->id)
        ->assertCanSeeTableRecords([$myOrder])
        ->assertCanNotSeeTableRecords([$otherOrder]);
});

it('filters orders by date range', function () {
    $oldOrder = makeOrder(['order_number' => 'OLD-'.uniqid()]);
    $oldOrder->forceFill(['created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10)])->save();

    $recentOrder = makeOrder(['order_number' => 'NEW-'.uniqid()]);

    Livewire::test(ListOrders::class)
        ->filterTable('created_at', ['from' => now()->subDay()->toDateString()])
        ->assertCanSeeTableRecords([$recentOrder])
        ->assertCanNotSeeTableRecords([$oldOrder]);
});

it('exposes the resource under "Transaksi" navigation group', function () {
    expect(OrderResource::getNavigationGroup())->toBe('Transaksi')
        ->and(OrderResource::getNavigationLabel())->toBe('Order');
});
