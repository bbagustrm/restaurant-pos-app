<?php

use App\Actions\Pos\CheckoutOrder;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RoleSeeder::class);
    seed(); // Default RestaurantSettings (tax 11%).

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
    $this->latte = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Latte',
        'slug' => 'latte-'.uniqid(),
        'price' => 25000,
    ]);
    $this->americano = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Americano',
        'slug' => 'americano-'.uniqid(),
        'price' => 22000,
    ]);

    actingAs($this->cashier);
});

/* ============================================================
 |  CheckoutOrder action — unit-ish tests
 * ============================================================ */

it('persists an order with correct subtotal, tax, and total', function () {
    $cart = [
        $this->latte->id => [
            'product_id' => $this->latte->id,
            'name' => 'Latte', 'price' => 25000, 'quantity' => 2, 'notes' => null,
        ],
        $this->americano->id => [
            'product_id' => $this->americano->id,
            'name' => 'Americano', 'price' => 22000, 'quantity' => 1, 'notes' => 'tanpa gula',
        ],
    ];

    $order = app(CheckoutOrder::class)->handle(
        cashier: $this->cashier,
        cart: $cart,
        tableId: null,
        paymentMethod: 'cash',
    );

    // 25000*2 + 22000 = 72000; tax 11% = 7920; total = 79920
    expect($order)->toBeInstanceOf(Order::class)
        ->and((int) $order->subtotal)->toBe(72000)
        ->and((int) $order->tax_amount)->toBe(7920)
        ->and((int) $order->total_amount)->toBe(79920)
        ->and($order->payment_method)->toBe('cash')
        ->and($order->payment_status)->toBe('paid')
        ->and($order->status)->toBe('confirmed');
});

it('snapshots product_name and product_price into order_items', function () {
    $cart = [
        $this->latte->id => [
            'product_id' => $this->latte->id,
            'name' => 'Latte (stale)', 'price' => 99999, 'quantity' => 1, 'notes' => 'extra hot',
        ],
    ];

    $order = app(CheckoutOrder::class)->handle($this->cashier, $cart, null, 'cash');

    /** @var OrderItem $item */
    $item = $order->orderItems->first();

    expect($item)->not->toBeNull()
        ->and($item->product_name)->toBe('Latte') // re-fetched, not from cart
        ->and((int) $item->product_price)->toBe(25000)
        ->and($item->notes)->toBe('extra hot')
        ->and($item->status)->toBe('pending');
});

it('marks the table as occupied when one is selected', function () {
    $table = RestaurantTable::create(['name' => 'Meja Test', 'capacity' => 4, 'status' => 'available']);

    $cart = [$this->latte->id => [
        'product_id' => $this->latte->id, 'name' => 'Latte', 'price' => 25000, 'quantity' => 1, 'notes' => null,
    ]];

    app(CheckoutOrder::class)->handle($this->cashier, $cart, $table->id, 'cash');

    expect($table->fresh()->status)->toBe('occupied');
});

it('applies a percentage discount and stores discount_type', function () {
    $cart = [$this->latte->id => [
        'product_id' => $this->latte->id, 'name' => 'Latte', 'price' => 25000, 'quantity' => 4, 'notes' => null,
    ]];
    // subtotal 100000, 10% off = 10000, taxable 90000, tax 11% = 9900, total = 99900
    $order = app(CheckoutOrder::class)->handle(
        $this->cashier, $cart, null, 'qris',
        discount: ['type' => 'percentage', 'value' => 10],
    );

    expect((int) $order->subtotal)->toBe(100000)
        ->and((int) $order->discount_amount)->toBe(10000)
        ->and($order->discount_type)->toBe('percentage')
        ->and((int) $order->tax_amount)->toBe(9900)
        ->and((int) $order->total_amount)->toBe(99900);
});

it('throws on empty cart', function () {
    app(CheckoutOrder::class)->handle($this->cashier, [], null, 'cash');
})->throws(InvalidArgumentException::class);

it('rolls back the transaction when an item product no longer exists', function () {
    $stale = $this->americano->id;
    $this->americano->delete(); // soft delete

    // Force the lookup to fail by hard-deleting the product
    Product::withTrashed()->find($stale)->forceDelete();

    $cart = [
        $this->latte->id => [
            'product_id' => $this->latte->id, 'name' => 'Latte', 'price' => 25000, 'quantity' => 1, 'notes' => null,
        ],
        $stale => [
            'product_id' => $stale, 'name' => 'Gone', 'price' => 999, 'quantity' => 1, 'notes' => null,
        ],
    ];

    expect(fn () => app(CheckoutOrder::class)->handle($this->cashier, $cart, null, 'cash'))
        ->toThrow(ModelNotFoundException::class);

    // Order and OrderItem rows must NOT exist.
    expect(Order::count())->toBe(0)
        ->and(OrderItem::count())->toBe(0);
});

/* ============================================================
 |  POS component — checkout flow integration
 * ============================================================ */

it('opens the checkout modal via startCheckout', function () {
    Livewire::test('pages::pos')
        ->call('addToCart', $this->latte->id)
        ->call('startCheckout')
        ->assertSet('checkoutOpen', true)
        ->assertDispatched('checkout:open');
});

it('does not open the checkout modal when cart is empty', function () {
    Livewire::test('pages::pos')
        ->call('startCheckout')
        ->assertSet('checkoutOpen', false);
});

it('switching payment method clears cashReceived for non-cash methods', function () {
    Livewire::test('pages::pos')
        ->call('addToCart', $this->latte->id)
        ->call('startCheckout')
        ->set('cashReceived', 100000)
        ->call('setPaymentMethod', 'qris')
        ->assertSet('paymentMethod', 'qris')
        ->assertSet('cashReceived', null);
});

it('blocks processCheckout when cash received is below total', function () {
    Livewire::test('pages::pos')
        ->call('addToCart', $this->latte->id)
        ->call('startCheckout')
        ->set('cashReceived', 1000)
        ->call('processCheckout')
        ->assertSet('checkoutOpen', true)
        ->assertSet('checkoutError', 'Jumlah bayar kurang dari total.');

    expect(Order::count())->toBe(0);
});

it('processCheckout persists the order, resets cart, and dispatches order:created', function () {
    $component = Livewire::test('pages::pos')
        ->call('addToCart', $this->latte->id)
        ->call('startCheckout')
        ->set('cashReceived', 100000)
        ->call('processCheckout');

    expect(Order::count())->toBe(1);

    $order = Order::first();

    $component
        ->assertSet('cart', [])
        ->assertSet('checkoutOpen', false)
        ->assertSet('cashReceived', null)
        ->assertDispatched('order:created', orderId: $order->id);
});

it('processCheckout works for non-cash methods without cashReceived', function () {
    Livewire::test('pages::pos')
        ->call('addToCart', $this->latte->id)
        ->call('startCheckout')
        ->call('setPaymentMethod', 'qris')
        ->call('processCheckout')
        ->assertSet('cart', []);

    expect(Order::first()->payment_method)->toBe('qris');
});

it('exposes change as total - cashReceived (cash only)', function () {
    $component = Livewire::test('pages::pos')
        ->call('addToCart', $this->latte->id) // total 27750
        ->call('startCheckout')
        ->set('cashReceived', 30000);

    expect($component->instance()->change)->toBe(2250);

    $component->call('setPaymentMethod', 'qris');
    expect($component->instance()->change)->toBe(0);
});

it('isCheckoutSubmittable enforces cash >= total', function () {
    $component = Livewire::test('pages::pos')
        ->call('addToCart', $this->latte->id) // total 27750
        ->call('startCheckout');

    expect($component->instance()->isCheckoutSubmittable)->toBeFalse();

    $component->set('cashReceived', 27750);
    expect($component->instance()->isCheckoutSubmittable)->toBeTrue();
});
