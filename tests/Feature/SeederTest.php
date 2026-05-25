<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\seed;

beforeEach(fn () => seed());

it('seeds the three demo users with correct roles and password "password"', function () {
    $admin = User::where('email', 'admin@pos.test')->first();
    $cashier = User::where('email', 'kasir@pos.test')->first();
    $kitchen = User::where('email', 'dapur@pos.test')->first();

    expect($admin)->not->toBeNull()
        ->and($admin->hasRole('super_admin'))->toBeTrue()
        ->and(Hash::check('password', $admin->password))->toBeTrue();

    expect($cashier)->not->toBeNull()
        ->and($cashier->hasRole('cashier'))->toBeTrue();

    expect($kitchen)->not->toBeNull()
        ->and($kitchen->hasRole('kitchen'))->toBeTrue();
});

it('seeds the eight required menu categories', function () {
    expect(Category::count())->toBe(8);

    $expectedSlugs = ['kopi-panas', 'kopi-dingin', 'non-kopi', 'makanan-berat',
        'makanan-ringan', 'dessert', 'paket', 'add-on'];

    expect(Category::pluck('slug')->all())->toEqualCanonicalizing($expectedSlugs);
});

it('seeds at least 20 products with exactly 5 featured', function () {
    expect(Product::count())->toBeGreaterThanOrEqual(20)
        ->and(Product::where('is_featured', true)->count())->toBe(5);
});

it('seeds the nine restaurant tables', function () {
    expect(RestaurantTable::count())->toBe(9)
        ->and(RestaurantTable::pluck('name')->all())->toEqualCanonicalizing([
            'Meja 1', 'Meja 2', 'Meja 3', 'Meja 4', 'Meja 5', 'Meja 6',
            'Bar 1', 'Bar 2', 'Takeaway Counter',
        ])
        ->and(RestaurantTable::pluck('status')->unique()->all())->toBe(['available']);
});

it('seeders are idempotent — running twice does not duplicate rows', function () {
    seed();

    expect(User::count())->toBe(3)
        ->and(Category::count())->toBe(8)
        ->and(RestaurantTable::count())->toBe(9);
});
