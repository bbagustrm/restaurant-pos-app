<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
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
        'icon' => 'fire',
    ]);

    $this->latte = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Latte',
        'slug' => 'latte-'.uniqid(),
        'price' => 25000,
        'sort_order' => 1,
    ]);

    $this->americano = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Americano',
        'slug' => 'americano-'.uniqid(),
        'price' => 22000,
        'sort_order' => 2,
    ]);

    $this->habis = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Out of Stock',
        'slug' => 'oos-'.uniqid(),
        'price' => 30000,
        'is_available' => false,
        'sort_order' => 3,
    ]);
});

it('redirects unauthenticated users to login', function () {
    get('/pos')->assertRedirect('/login');
});

it('renders the POS page for cashier', function () {
    actingAs($this->cashier);

    get('/pos')->assertOk();
});

it('forbids users without cashier or super_admin role', function () {
    $kitchen = User::create([
        'name' => 'Dapur',
        'email' => 'dapur-'.uniqid().'@test.local',
        'password' => 'password',
    ]);
    $kitchen->assignRole('kitchen');

    actingAs($kitchen)->get('/pos')->assertForbidden();
});

it('lists active categories with product counts', function () {
    actingAs($this->cashier);

    $component = Livewire::test('pages::pos');
    $categories = $component->instance()->categories;

    expect($categories)->toHaveCount(1)
        ->and($categories->first()->id)->toBe($this->category->id)
        ->and($categories->first()->products_count)->toBe(3);
});

it('shows all products by default', function () {
    actingAs($this->cashier);

    Livewire::test('pages::pos')
        ->assertSee('Latte')
        ->assertSee('Americano')
        ->assertSee('Out of Stock');
});

it('filters products by selected category', function () {
    $other = Category::create(['name' => 'Dessert', 'slug' => 'dessert-'.uniqid(), 'sort_order' => 2]);
    Product::create([
        'category_id' => $other->id,
        'name' => 'Tiramisu',
        'slug' => 'tiramisu-'.uniqid(),
        'price' => 38000,
    ]);

    actingAs($this->cashier);

    Livewire::test('pages::pos')
        ->call('selectCategory', $this->category->id)
        ->assertSee('Latte')
        ->assertDontSee('Tiramisu');
});

it('filters products by search term in real time', function () {
    actingAs($this->cashier);

    Livewire::test('pages::pos')
        ->set('search', 'Lat')
        ->assertSee('Latte')
        ->assertDontSee('Americano');
});

it('adds an available product to the cart', function () {
    actingAs($this->cashier);

    $component = Livewire::test('pages::pos')
        ->call('addToCart', $this->latte->id);

    $cart = $component->instance()->cart;

    expect($cart)->toHaveKey($this->latte->id)
        ->and($cart[$this->latte->id]['quantity'])->toBe(1)
        ->and($cart[$this->latte->id]['name'])->toBe('Latte')
        ->and($cart[$this->latte->id]['price'])->toBe(25000);
});

it('increments quantity when adding the same product again', function () {
    actingAs($this->cashier);

    $component = Livewire::test('pages::pos')
        ->call('addToCart', $this->latte->id)
        ->call('addToCart', $this->latte->id)
        ->call('addToCart', $this->latte->id);

    expect($component->instance()->cart[$this->latte->id]['quantity'])->toBe(3);
});

it('refuses to add unavailable products', function () {
    actingAs($this->cashier);

    $component = Livewire::test('pages::pos')
        ->call('addToCart', $this->habis->id);

    expect($component->instance()->cart)->toBeEmpty();
});

it('exposes a cart items count computed property', function () {
    actingAs($this->cashier);

    $component = Livewire::test('pages::pos')
        ->call('addToCart', $this->latte->id)
        ->call('addToCart', $this->latte->id)
        ->call('addToCart', $this->americano->id);

    expect($component->instance()->cartItemsCount)->toBe(3);
});

it('preserves URL state for the search and category filters', function () {
    actingAs($this->cashier);

    Livewire::test('pages::pos')
        ->set('search', 'Lat')
        ->set('selectedCategoryId', $this->category->id)
        ->assertSet('search', 'Lat')
        ->assertSet('selectedCategoryId', $this->category->id);
});
