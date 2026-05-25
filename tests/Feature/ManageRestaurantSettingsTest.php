<?php

use App\Filament\Pages\ManageRestaurantSettings;
use App\Models\User;
use App\Settings\RestaurantSettings;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RoleSeeder::class);
    // Persist default settings so the form can hydrate.
    seed();

    $this->admin = User::create([
        'name' => 'Admin',
        'email' => 'admin-'.uniqid().'@test.local',
        'password' => 'password',
    ]);
    $this->admin->assignRole('super_admin');
});

it('renders the settings page for super_admin', function () {
    actingAs($this->admin);

    Livewire::test(ManageRestaurantSettings::class)
        ->assertOk();
});

it('hydrates the form with current settings', function () {
    actingAs($this->admin);

    $settings = app(RestaurantSettings::class);

    Livewire::test(ManageRestaurantSettings::class)
        ->assertFormSet([
            'restaurant_name' => $settings->restaurant_name,
            'tax_percentage' => $settings->tax_percentage,
            'currency_symbol' => $settings->currency_symbol,
            'auto_print_receipt' => $settings->auto_print_receipt,
        ]);
});

it('saves form values and notifies on success', function () {
    actingAs($this->admin);

    Livewire::test(ManageRestaurantSettings::class)
        ->fillForm([
            'restaurant_name' => 'Updated Cafe',
            'restaurant_address' => 'Jl. Updated 99, Yogya',
            'restaurant_phone' => '+62 21-9999',
            'tax_percentage' => 12.5,
            'currency_symbol' => 'IDR',
            'receipt_footer' => 'Sampai jumpa!',
            'auto_print_receipt' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $settings = app(RestaurantSettings::class)->refresh();

    expect($settings->restaurant_name)->toBe('Updated Cafe')
        ->and($settings->tax_percentage)->toBe(12.5)
        ->and($settings->currency_symbol)->toBe('IDR')
        ->and($settings->auto_print_receipt)->toBeTrue()
        ->and($settings->receipt_footer)->toBe('Sampai jumpa!');
});

it('validates required fields on save', function () {
    actingAs($this->admin);

    Livewire::test(ManageRestaurantSettings::class)
        ->fillForm([
            'restaurant_name' => null,
            'restaurant_address' => null,
            'restaurant_phone' => null,
            'currency_symbol' => null,
            'receipt_footer' => null,
        ])
        ->call('save')
        ->assertHasFormErrors([
            'restaurant_name',
            'restaurant_address',
            'restaurant_phone',
            'currency_symbol',
            'receipt_footer',
        ]);
});

it('rejects tax_percentage outside 0..100', function () {
    actingAs($this->admin);

    Livewire::test(ManageRestaurantSettings::class)
        ->fillForm(['tax_percentage' => 150])
        ->call('save')
        ->assertHasFormErrors(['tax_percentage']);
});

it('blocks non super_admin users from accessing the page', function () {
    $cashier = User::create([
        'name' => 'Kasir',
        'email' => 'kasir-'.uniqid().'@test.local',
        'password' => 'password',
    ]);
    $cashier->assignRole('cashier');

    actingAs($cashier);

    expect(ManageRestaurantSettings::canAccess())->toBeFalse();
});

it('allows super_admin via canAccess()', function () {
    actingAs($this->admin);

    expect(ManageRestaurantSettings::canAccess())->toBeTrue();
});

it('exposes the page under "Pengaturan" navigation group', function () {
    expect(ManageRestaurantSettings::getNavigationGroup())->toBe('Pengaturan')
        ->and(ManageRestaurantSettings::getNavigationLabel())->toBe('Restoran');
});
