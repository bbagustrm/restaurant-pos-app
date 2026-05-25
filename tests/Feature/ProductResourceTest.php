<?php

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Category;
use App\Models\Product;
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

    $this->category = Category::create([
        'name' => 'Kopi Panas',
        'slug' => 'kopi-panas-'.uniqid(),
        'sort_order' => 1,
    ]);

    actingAs($this->admin);
});

it('renders the product list page with eager-loaded category', function () {
    Product::create([
        'category_id' => $this->category->id,
        'name' => 'Latte',
        'slug' => 'latte-'.uniqid(),
        'price' => 25000,
    ]);

    Livewire::test(ListProducts::class)
        ->assertOk()
        ->assertCanSeeTableRecords(Product::all());
});

it('creates a product with all sections filled', function () {
    Livewire::test(CreateProduct::class)
        ->fillForm([
            'category_id' => $this->category->id,
            'name' => 'Cappuccino Spesial',
            'slug' => 'cappuccino-spesial',
            'description' => '<p>Cappuccino dengan susu organik.</p>',
            'price' => 32000,
            'is_available' => true,
            'is_featured' => true,
            'sort_order' => 5,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Product::where('slug', 'cappuccino-spesial')->exists())->toBeTrue();
});

it('validates required fields on create', function () {
    Livewire::test(CreateProduct::class)
        ->fillForm(['name' => null, 'category_id' => null, 'price' => null])
        ->call('create')
        ->assertHasFormErrors(['name', 'category_id', 'price']);
});

it('rejects duplicate slug on create', function () {
    Product::create([
        'category_id' => $this->category->id,
        'name' => 'Latte',
        'slug' => 'duplicate-slug',
        'price' => 25000,
    ]);

    Livewire::test(CreateProduct::class)
        ->fillForm([
            'category_id' => $this->category->id,
            'name' => 'Latte 2',
            'slug' => 'duplicate-slug',
            'price' => 30000,
        ])
        ->call('create')
        ->assertHasFormErrors(['slug' => 'unique']);
});

it('edits an existing product', function () {
    $product = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Old Name',
        'slug' => 'old-name-'.uniqid(),
        'price' => 20000,
    ]);

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->fillForm(['name' => 'New Name', 'price' => 28000])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->fresh())
        ->name->toBe('New Name')
        ->price->toBe('28000.00');
});

it('filters products by category', function () {
    $other = Category::create(['name' => 'Dessert', 'slug' => 'dessert-'.uniqid(), 'sort_order' => 2]);

    $kopi = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Latte',
        'slug' => 'latte-'.uniqid(),
        'price' => 25000,
    ]);
    $cake = Product::create([
        'category_id' => $other->id,
        'name' => 'Tiramisu',
        'slug' => 'tiramisu-'.uniqid(),
        'price' => 38000,
    ]);

    Livewire::test(ListProducts::class)
        ->filterTable('category_id', [$this->category->id])
        ->assertCanSeeTableRecords([$kopi])
        ->assertCanNotSeeTableRecords([$cake]);
});

it('filters products by is_available', function () {
    $available = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Available',
        'slug' => 'available-'.uniqid(),
        'price' => 25000,
        'is_available' => true,
    ]);
    $unavailable = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Unavailable',
        'slug' => 'unavailable-'.uniqid(),
        'price' => 25000,
        'is_available' => false,
    ]);

    Livewire::test(ListProducts::class)
        ->filterTable('is_available', true)
        ->assertCanSeeTableRecords([$available])
        ->assertCanNotSeeTableRecords([$unavailable]);
});

it('filters products by is_featured', function () {
    $featured = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Featured',
        'slug' => 'featured-'.uniqid(),
        'price' => 25000,
        'is_featured' => true,
    ]);
    $regular = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Regular',
        'slug' => 'regular-'.uniqid(),
        'price' => 25000,
        'is_featured' => false,
    ]);

    Livewire::test(ListProducts::class)
        ->filterTable('is_featured', true)
        ->assertCanSeeTableRecords([$featured])
        ->assertCanNotSeeTableRecords([$regular]);
});

it('soft deletes a product so it disappears from default list', function () {
    $product = Product::create([
        'category_id' => $this->category->id,
        'name' => 'To Delete',
        'slug' => 'to-delete-'.uniqid(),
        'price' => 20000,
    ]);

    $product->delete();

    expect(Product::find($product->id))->toBeNull()
        ->and(Product::withTrashed()->find($product->id))->not->toBeNull();
});

it('restores a soft-deleted product through the trashed scope', function () {
    $product = Product::create([
        'category_id' => $this->category->id,
        'name' => 'To Restore',
        'slug' => 'to-restore-'.uniqid(),
        'price' => 20000,
    ]);
    $product->delete();
    Product::withTrashed()->find($product->id)->restore();

    expect(Product::find($product->id))->not->toBeNull();
});

it('runs the mark_available bulk action', function () {
    $product = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Stale',
        'slug' => 'stale-'.uniqid(),
        'price' => 20000,
        'is_available' => false,
    ]);

    Livewire::test(ListProducts::class)
        ->callTableBulkAction('mark_available', [$product->getKey()]);

    expect($product->fresh()->is_available)->toBeTrue();
});

it('runs the mark_unavailable bulk action', function () {
    $product = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Fresh',
        'slug' => 'fresh-'.uniqid(),
        'price' => 20000,
        'is_available' => true,
    ]);

    Livewire::test(ListProducts::class)
        ->callTableBulkAction('mark_unavailable', [$product->getKey()]);

    expect($product->fresh()->is_available)->toBeFalse();
});

it('exposes the resource under "Manajemen Menu" navigation group', function () {
    expect(ProductResource::getNavigationGroup())->toBe('Manajemen Menu')
        ->and(ProductResource::getNavigationLabel())->toBe('Produk');
});
