<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Settings\RestaurantSettings;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RoleSeeder::class);
    seed(); // Seeds RestaurantSettings defaults so $taxPercentage resolves to 11.

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

it('shows the empty state when cart is empty', function () {
    Livewire::test('pages::pos')
        ->assertSee('Cart kosong');
});

it('increments quantity via the stepper', function () {
    Livewire::test('pages::pos')
        ->call('addToCart', $this->latte->id)
        ->call('incrementQty', $this->latte->id)
        ->call('incrementQty', $this->latte->id)
        ->assertSet('cart.'.$this->latte->id.'.quantity', 3);
});

it('decrements quantity via the stepper', function () {
    Livewire::test('pages::pos')
        ->call('addToCart', $this->latte->id)
        ->call('addToCart', $this->latte->id)
        ->call('addToCart', $this->latte->id)
        ->call('decrementQty', $this->latte->id)
        ->assertSet('cart.'.$this->latte->id.'.quantity', 2);
});

it('removes item when decrementing quantity to zero', function () {
    $component = Livewire::test('pages::pos')
        ->call('addToCart', $this->latte->id)
        ->call('decrementQty', $this->latte->id);

    expect($component->instance()->cart)->toBeEmpty();
});

it('removes an item directly via removeItem', function () {
    $component = Livewire::test('pages::pos')
        ->call('addToCart', $this->latte->id)
        ->call('addToCart', $this->americano->id)
        ->call('removeItem', $this->latte->id);

    expect($component->instance()->cart)
        ->toHaveKey($this->americano->id)
        ->not->toHaveKey($this->latte->id);
});

it('stores per-item notes', function () {
    $component = Livewire::test('pages::pos')
        ->call('addToCart', $this->latte->id)
        ->call('setItemNotes', $this->latte->id, 'tanpa gula');

    expect($component->instance()->cart[$this->latte->id]['notes'])->toBe('tanpa gula');
});

it('clears notes when an empty string is passed', function () {
    $component = Livewire::test('pages::pos')
        ->call('addToCart', $this->latte->id)
        ->call('setItemNotes', $this->latte->id, 'tanpa gula')
        ->call('setItemNotes', $this->latte->id, '   ');

    expect($component->instance()->cart[$this->latte->id]['notes'])->toBeNull();
});

it('calculates subtotal as sum of price × quantity', function () {
    // 25000 * 2 + 22000 * 1 = 72000
    $component = Livewire::test('pages::pos')
        ->call('addToCart', $this->latte->id)
        ->call('addToCart', $this->latte->id)
        ->call('addToCart', $this->americano->id);

    expect($component->instance()->subtotal)->toBe(72000);
});

it('applies a percentage discount and caps it at 100%', function () {
    $component = Livewire::test('pages::pos')
        ->call('addToCart', $this->latte->id) // subtotal = 25000
        ->call('applyDiscount', 'percentage', 10);

    expect($component->instance()->discountAmount)->toBe(2500);

    $component->call('applyDiscount', 'percentage', 250);
    expect($component->instance()->discountValue)->toBe(100.0)
        ->and($component->instance()->discountAmount)->toBe(25000);
});

it('applies a fixed discount and caps it at the subtotal', function () {
    $component = Livewire::test('pages::pos')
        ->call('addToCart', $this->latte->id) // subtotal = 25000
        ->call('applyDiscount', 'fixed', 5000);

    expect($component->instance()->discountAmount)->toBe(5000);

    $component->call('applyDiscount', 'fixed', 999999);
    expect($component->instance()->discountAmount)->toBe(25000);
});

it('rejects invalid discount input', function () {
    $component = Livewire::test('pages::pos')
        ->call('addToCart', $this->latte->id)
        ->call('applyDiscount', 'fixed', 0)
        ->call('applyDiscount', 'something', 10);

    expect($component->instance()->discountType)->toBeNull()
        ->and($component->instance()->discountAmount)->toBe(0);
});

it('resets the discount', function () {
    $component = Livewire::test('pages::pos')
        ->call('addToCart', $this->latte->id)
        ->call('applyDiscount', 'percentage', 20)
        ->call('resetDiscount');

    expect($component->instance()->discountType)->toBeNull()
        ->and($component->instance()->discountValue)->toBe(0.0)
        ->and($component->instance()->discountAmount)->toBe(0);
});

it('uses tax_percentage from RestaurantSettings', function () {
    $settings = app(RestaurantSettings::class);
    $settings->tax_percentage = 8.0;
    $settings->save();

    $component = Livewire::test('pages::pos');
    expect($component->instance()->taxPercentage)->toBe(8.0);
});

it('calculates tax on (subtotal - discount) and computes total correctly', function () {
    // subtotal 50000, discount 10% = 5000 → taxable 45000, tax 11% = 4950, total = 49950
    $component = Livewire::test('pages::pos')
        ->call('addToCart', $this->latte->id)
        ->call('addToCart', $this->latte->id)
        ->call('applyDiscount', 'percentage', 10);

    expect($component->instance()->subtotal)->toBe(50000)
        ->and($component->instance()->discountAmount)->toBe(5000)
        ->and($component->instance()->taxAmount)->toBe(4950)
        ->and($component->instance()->total)->toBe(49950);
});

it('lists tables ordered alphabetically', function () {
    // Beyond the seeded 9 tables, add a clearly out-of-order entry.
    RestaurantTable::create(['name' => 'Zona VIP', 'capacity' => 8]);

    $component = Livewire::test('pages::pos');
    $names = $component->instance()->tables->pluck('name')->all();

    // Already-seeded tables come first; "Zona VIP" must end up at the end.
    expect($names[0])->toBe('Bar 1')
        ->and($names[1])->toBe('Bar 2')
        ->and(end($names))->toBe('Zona VIP');
});

it('persists the selected table', function () {
    $table = RestaurantTable::create(['name' => 'Meja 1', 'capacity' => 4]);

    $component = Livewire::test('pages::pos')
        ->call('setTable', $table->id);

    expect($component->instance()->tableId)->toBe($table->id);

    $component->call('setTable', '');
    expect($component->instance()->tableId)->toBeNull();
});

it('clearCart resets cart, discount, and table', function () {
    $table = RestaurantTable::create(['name' => 'Meja 1', 'capacity' => 4]);

    $component = Livewire::test('pages::pos')
        ->call('setTable', $table->id)
        ->call('addToCart', $this->latte->id)
        ->call('applyDiscount', 'percentage', 10)
        ->call('clearCart');

    expect($component->instance()->cart)->toBeEmpty()
        ->and($component->instance()->discountType)->toBeNull()
        ->and($component->instance()->tableId)->toBeNull();
});

it('startCheckout dispatches the checkout:open event when cart has items', function () {
    Livewire::test('pages::pos')
        ->call('addToCart', $this->latte->id)
        ->call('startCheckout')
        ->assertDispatched('checkout:open');
});

it('startCheckout does nothing when cart is empty', function () {
    Livewire::test('pages::pos')
        ->call('startCheckout')
        ->assertNotDispatched('checkout:open');
});
