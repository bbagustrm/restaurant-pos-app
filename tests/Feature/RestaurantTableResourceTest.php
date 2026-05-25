<?php

use App\Filament\Resources\RestaurantTables\Pages\CreateRestaurantTable;
use App\Filament\Resources\RestaurantTables\Pages\EditRestaurantTable;
use App\Filament\Resources\RestaurantTables\Pages\ListRestaurantTables;
use App\Filament\Resources\RestaurantTables\RestaurantTableResource;
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

    actingAs($this->admin);
});

it('renders the table list page', function () {
    RestaurantTable::create(['name' => 'Meja 1', 'capacity' => 4]);

    Livewire::test(ListRestaurantTables::class)
        ->assertOk()
        ->assertCanSeeTableRecords(RestaurantTable::all());
});

it('creates a restaurant table', function () {
    Livewire::test(CreateRestaurantTable::class)
        ->fillForm([
            'name' => 'Meja VIP',
            'capacity' => 6,
            'status' => 'available',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(RestaurantTable::where('name', 'Meja VIP')->exists())->toBeTrue();
});

it('validates required fields on create', function () {
    Livewire::test(CreateRestaurantTable::class)
        ->fillForm(['name' => null, 'capacity' => null, 'status' => null])
        ->call('create')
        ->assertHasFormErrors(['name', 'capacity', 'status']);
});

it('edits an existing table', function () {
    $table = RestaurantTable::create(['name' => 'Meja 1', 'capacity' => 4]);

    Livewire::test(EditRestaurantTable::class, ['record' => $table->getRouteKey()])
        ->fillForm(['name' => 'Meja 1A', 'capacity' => 8])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($table->fresh())
        ->name->toBe('Meja 1A')
        ->capacity->toBe(8);
});

it('filters tables by status', function () {
    $available = RestaurantTable::create(['name' => 'Free', 'capacity' => 4, 'status' => 'available']);
    $occupied = RestaurantTable::create(['name' => 'Busy', 'capacity' => 4, 'status' => 'occupied']);

    Livewire::test(ListRestaurantTables::class)
        ->filterTable('status', 'available')
        ->assertCanSeeTableRecords([$available])
        ->assertCanNotSeeTableRecords([$occupied]);
});

it('exposes the resource under "Manajemen Menu" navigation group', function () {
    expect(RestaurantTableResource::getNavigationGroup())->toBe('Manajemen Menu')
        ->and(RestaurantTableResource::getNavigationLabel())->toBe('Meja');
});
