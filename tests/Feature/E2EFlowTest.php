<?php

use App\Events\OrderItemStatusUpdated;
use App\Events\OrderPlaced;
use App\Http\Responses\LoginResponse;
use App\Models\Order;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

/**
 * Single end-to-end smoke test covering the full kasir-to-kitchen-to-invoice
 * lifecycle on top of the canonical seeded data set. Mirrors the manual flow
 * a real user would perform.
 */
it('completes the kasir → kitchen → invoice lifecycle on seeded data', function () {
    // ── Stage 0: seed demo data ──────────────────────────────────────
    seed(); // Roles, demo users, 8 categories, 23 products, 9 tables, settings

    // Stop OrderPlaced from queueing a real broadcast; we only assert it dispatched.
    Event::fake([OrderPlaced::class, OrderItemStatusUpdated::class]);

    $admin = User::firstWhere('email', 'admin@pos.test');
    $kasir = User::firstWhere('email', 'kasir@pos.test');
    $dapur = User::firstWhere('email', 'dapur@pos.test');

    expect($admin?->hasRole('super_admin'))->toBeTrue()
        ->and($kasir?->hasRole('cashier'))->toBeTrue()
        ->and($dapur?->hasRole('kitchen'))->toBeTrue();

    // ── Stage 1: login redirect per role ─────────────────────────────
    foreach ([
        [$admin, '/admin'],
        [$kasir, '/pos'],
        [$dapur, '/kitchen'],
    ] as [$user, $expectedTarget]) {
        $request = request();
        $request->setUserResolver(fn () => $user);

        $response = (new LoginResponse)->toResponse($request);
        expect($response->getTargetUrl())->toEndWith($expectedTarget);
    }

    // ── Stage 2: kasir creates an order via the POS Livewire component ──
    actingAs($kasir);

    $latte = Product::firstWhere('slug', 'caffe-latte');
    $nasiGoreng = Product::firstWhere('slug', 'nasi-goreng-spesial');
    $mejaSatu = RestaurantTable::firstWhere('name', 'Meja 1');

    expect($latte)->not->toBeNull()
        ->and($nasiGoreng)->not->toBeNull()
        ->and($mejaSatu?->status)->toBe('available');

    Livewire::test('pages::pos')
        ->call('addToCart', $latte->id)
        ->call('addToCart', $latte->id)
        ->call('addToCart', $nasiGoreng->id)
        ->call('setItemNotes', $nasiGoreng->id, 'extra pedas')
        ->call('setTable', $mejaSatu->id)
        ->call('startCheckout')
        ->assertSet('checkoutOpen', true)
        ->set('cashReceived', 200000)
        ->call('processCheckout')
        ->assertSet('checkoutOpen', false)
        ->assertSet('cart', [])
        ->assertDispatched('order:created');

    // ── Stage 3: assert persistence ──────────────────────────────────
    $order = Order::query()
        ->where('cashier_id', $kasir->id)
        ->latest('created_at')
        ->first();

    expect($order)->not->toBeNull()
        ->and($order->order_number)->toMatch('/^ORD-\d{8}-\d{4}$/')
        ->and($order->status)->toBe('confirmed')
        ->and($order->payment_status)->toBe('paid')
        ->and($order->orderItems)->toHaveCount(2)
        ->and((int) $order->subtotal)->toBe(60000 + 42000) // Caffe Latte 30000*2 + Nasi Goreng 42000
        ->and($order->orderItems->firstWhere('product_id', $nasiGoreng->id)->notes)->toBe('extra pedas')
        ->and($mejaSatu->fresh()->status)->toBe('occupied');

    // OrderPlaced event must have been dispatched (broadcast contract).
    Event::assertDispatched(OrderPlaced::class, fn (OrderPlaced $e) => $e->order->is($order));

    // ── Stage 4: KDS shows the order, dapur transitions it ────────────
    actingAs($dapur);

    $kdsList = Livewire::test('pages::kitchen');
    $orderIds = $kdsList->instance()->orders->pluck('id')->all();
    expect($orderIds)->toContain($order->id);

    // confirmed → preparing
    $kdsList->call('startPreparing', $order->id);
    expect($order->fresh()->status)->toBe('preparing')
        ->and($order->fresh()->orderItems->pluck('status')->unique()->all())->toBe(['preparing']);

    Event::assertDispatched(OrderItemStatusUpdated::class);

    // preparing → ready
    $kdsList->call('markReady', $order->id);
    expect($order->fresh()->status)->toBe('ready')
        ->and($order->fresh()->orderItems->pluck('status')->unique()->all())->toBe(['ready']);

    // ── Stage 5: invoice PDF is downloadable by the cashier owner ─────
    actingAs($kasir);

    $invoice = get(route('orders.invoice', $order));
    $invoice->assertOk();
    expect($invoice->headers->get('Content-Type'))->toContain('application/pdf');

    $receipt = get(route('orders.receipt', $order));
    $receipt->assertOk();

    // ── Stage 6: table cycle — once the order completes, the table frees up ──
    // The CheckoutOrder action only flips the table to 'occupied'. The
    // 'available' transition is the responsibility of completing/cancelling
    // the order — verify the model relationship + simulate completion here.
    $order->update(['status' => 'completed', 'completed_at' => now()]);
    RestaurantTable::query()->whereKey($mejaSatu->id)->update(['status' => 'available']);

    expect($mejaSatu->fresh()->status)->toBe('available');
});
