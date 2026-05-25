<?php

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
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

    actingAs($this->admin);
});

it('renders the category list page', function () {
    Category::create(['name' => 'Kopi', 'slug' => 'kopi', 'sort_order' => 1]);

    Livewire::test(ListCategories::class)
        ->assertOk()
        ->assertCanSeeTableRecords(Category::all());
});

it('creates a category and persists the data', function () {
    Livewire::test(CreateCategory::class)
        ->fillForm([
            'name' => 'Minuman Segar',
            'slug' => 'minuman-segar',
            'icon' => 'beaker',
            'color' => '#10B981',
            'sort_order' => 5,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Category::where('slug', 'minuman-segar')->exists())->toBeTrue();
});

it('validates required fields on create', function () {
    Livewire::test(CreateCategory::class)
        ->fillForm(['name' => null, 'slug' => null])
        ->call('create')
        ->assertHasFormErrors(['name', 'slug']);
});

it('rejects duplicate slug on create', function () {
    Category::create(['name' => 'Kopi', 'slug' => 'kopi', 'sort_order' => 1]);

    Livewire::test(CreateCategory::class)
        ->fillForm([
            'name' => 'Kopi Lain',
            'slug' => 'kopi',
            'sort_order' => 2,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['slug' => 'unique']);
});

it('edits an existing category', function () {
    $category = Category::create(['name' => 'Old', 'slug' => 'old', 'sort_order' => 1]);

    Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
        ->fillForm(['name' => 'New Name', 'slug' => 'new-name'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($category->fresh())
        ->name->toBe('New Name')
        ->slug->toBe('new-name');
});

it('filters categories by is_active', function () {
    Category::create(['name' => 'Active', 'slug' => 'active', 'is_active' => true, 'sort_order' => 1]);
    Category::create(['name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false, 'sort_order' => 2]);

    Livewire::test(ListCategories::class)
        ->filterTable('is_active', true)
        ->assertCanSeeTableRecords(Category::where('is_active', true)->get())
        ->assertCanNotSeeTableRecords(Category::where('is_active', false)->get());
});

it('exposes the resource under "Manajemen Menu" navigation group', function () {
    expect(CategoryResource::getNavigationGroup())->toBe('Manajemen Menu')
        ->and(CategoryResource::getNavigationLabel())->toBe('Kategori');
});

it('configures the table to be reorderable on sort_order', function () {
    Category::create(['name' => 'A', 'slug' => 'a', 'sort_order' => 1]);

    $component = Livewire::test(ListCategories::class);
    $table = $component->instance()->getTable();

    expect($table->getReorderColumn())->toBe('sort_order');
});

it('shows the products count column', function () {
    $category = Category::create(['name' => 'Kopi', 'slug' => 'kopi', 'sort_order' => 1]);
    Product::create([
        'category_id' => $category->id,
        'name' => 'Latte',
        'slug' => 'latte-'.uniqid(),
        'price' => 25000,
    ]);

    Livewire::test(ListCategories::class)
        ->assertCanRenderTableColumn('products_count');
});
