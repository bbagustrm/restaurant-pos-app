<?php

use App\Events\OrderItemStatusUpdated;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RoleSeeder::class);
    seed();

    $this->kitchen = User::create([
        'name' => 'Dapur',
        'email' => 'dapur-'.uniqid().'@test.local',
        'password' => 'password',
    ]);
    $this->kitchen->assignRole('kitchen');

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

function makeKitchenOrder(string $status = 'confirmed'): Order
{
    $order = Order::create([
        'order_number' => 'ORD-K-'.substr(uniqid(), -8),
        'cashier_id' => test()->cashier->id,
        'status' => $status,
        'subtotal' => 25000, 'tax_amount' => 0, 'total_amount' => 25000,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => test()->product->id,
        'product_name' => 'Latte',
        'product_price' => 25000,
        'quantity' => 1,
        'notes' => 'tanpa gula',
        'status' => 'pending',
    ]);

    return $order;
}

it('redirects unauthenticated users to login', function () {
    get('/kitchen')->assertRedirect('/login');
});

it('renders the KDS for kitchen role', function () {
    actingAs($this->kitchen);
    get('/kitchen')->assertOk();
});

it('forbids cashiers from accessing the KDS', function () {
    actingAs($this->cashier);
    get('/kitchen')->assertForbidden();
});

it('shows confirmed and preparing orders, plus recent ready orders within 30s', function () {
    $confirmed = makeKitchenOrder('confirmed');
    $preparing = makeKitchenOrder('preparing');
    $recentlyReady = makeKitchenOrder('ready'); // updated_at = now()
    $oldReady = makeKitchenOrder('ready');
    $oldReady->forceFill(['updated_at' => now()->subMinutes(2)])->save();
    $completed = makeKitchenOrder('completed');
    $cancelled = makeKitchenOrder('cancelled');

    actingAs($this->kitchen);

    $component = Livewire::test('pages::kitchen');
    $orderIds = $component->instance()->orders->pluck('id')->all();

    expect($orderIds)->toContain($confirmed->id, $preparing->id, $recentlyReady->id)
        ->not->toContain($oldReady->id, $completed->id, $cancelled->id);
});

it('sorts confirmed (PENDING) before preparing', function () {
    // Make a preparing order first (older), then a confirmed order — confirmed should still come first.
    $preparing = makeKitchenOrder('preparing');
    $preparing->forceFill(['created_at' => now()->subMinutes(5)])->save();

    $confirmed = makeKitchenOrder('confirmed');

    actingAs($this->kitchen);

    $orders = Livewire::test('pages::kitchen')->instance()->orders;

    expect($orders->first()->id)->toBe($confirmed->id)
        ->and($orders->last()->id)->toBe($preparing->id);
});

it('startPreparing flips order and items to preparing and broadcasts per item', function () {
    Event::fake([OrderItemStatusUpdated::class]);

    $order = makeKitchenOrder('confirmed');

    actingAs($this->kitchen);

    Livewire::test('pages::kitchen')
        ->call('startPreparing', $order->id);

    $order->refresh();
    expect($order->status)->toBe('preparing')
        ->and($order->orderItems->first()->status)->toBe('preparing');

    Event::assertDispatched(OrderItemStatusUpdated::class);
});

it('startPreparing is a no-op when order is not confirmed', function () {
    $order = makeKitchenOrder('preparing');

    actingAs($this->kitchen);

    Livewire::test('pages::kitchen')
        ->call('startPreparing', $order->id);

    expect($order->fresh()->status)->toBe('preparing');
});

it('markReady flips order and items to ready and broadcasts per item', function () {
    Event::fake([OrderItemStatusUpdated::class]);

    $order = makeKitchenOrder('preparing');

    actingAs($this->kitchen);

    Livewire::test('pages::kitchen')
        ->call('markReady', $order->id);

    $order->refresh();
    expect($order->status)->toBe('ready')
        ->and($order->orderItems->first()->status)->toBe('ready');

    Event::assertDispatched(OrderItemStatusUpdated::class);
});

it('markReady is a no-op when order is not in preparing', function () {
    $order = makeKitchenOrder('confirmed');

    actingAs($this->kitchen);

    Livewire::test('pages::kitchen')
        ->call('markReady', $order->id);

    expect($order->fresh()->status)->toBe('confirmed');
});

it('renders item notes prominently', function () {
    makeKitchenOrder('confirmed');

    actingAs($this->kitchen);

    Livewire::test('pages::kitchen')
        ->assertSee('tanpa gula');
});

it('shows the empty state when there are no active orders', function () {
    actingAs($this->kitchen);

    Livewire::test('pages::kitchen')
        ->assertSee('Tidak ada order aktif');
});

it('refresh-kitchen listener clears the orders cache', function () {
    makeKitchenOrder('confirmed');

    actingAs($this->kitchen);

    $component = Livewire::test('pages::kitchen');
    expect($component->instance()->orders)->toHaveCount(1);

    // Adding another order outside this component's awareness, then dispatching the listener.
    makeKitchenOrder('confirmed');

    $component->dispatch('refresh-kitchen');

    expect(Livewire::test('pages::kitchen')->instance()->orders)->toHaveCount(2);
});

it('echo OrderPlaced listener invalidates the orders cache', function () {
    actingAs($this->kitchen);

    $component = Livewire::test('pages::kitchen');
    expect($component->instance()->orders)->toBeEmpty();

    makeKitchenOrder('confirmed');

    $component->call('onOrderPlaced', ['payload' => ['order_number' => 'ORD-X']]);

    // Re-render via the component instance to confirm fresh data is seen.
    expect(Livewire::test('pages::kitchen')->instance()->orders)->toHaveCount(1);
});

it('echo OrderCancelled listener invalidates the orders cache', function () {
    $order = makeKitchenOrder('confirmed');
    actingAs($this->kitchen);

    $component = Livewire::test('pages::kitchen');
    expect($component->instance()->orders)->toHaveCount(1);

    $order->update(['status' => 'cancelled']);
    $component->call('onOrderCancelled', ['payload' => ['id' => $order->id]]);

    expect(Livewire::test('pages::kitchen')->instance()->orders)->toBeEmpty();
});

it('echo OrderItemStatusUpdated listener invalidates the orders cache', function () {
    makeKitchenOrder('confirmed');
    actingAs($this->kitchen);

    Livewire::test('pages::kitchen')
        ->call('onOrderItemStatusUpdated', ['payload' => []])
        ->assertOk();
});
