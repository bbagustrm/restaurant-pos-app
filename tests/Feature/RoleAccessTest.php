<?php

use App\Http\Responses\LoginResponse;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

beforeEach(fn () => seed(RoleSeeder::class));

function makeUser(string $role): User
{
    $user = User::create([
        'name' => ucfirst($role).' User',
        'email' => $role.'-'.uniqid().'@test.local',
        'password' => 'password',
    ]);
    $user->assignRole($role);

    return $user;
}

it('seeds the three application roles', function () {
    expect(Role::pluck('name')->all())
        ->toContain('super_admin', 'cashier', 'kitchen');
});

it('redirects super_admin to /admin via LoginResponse', function () {
    $user = makeUser('super_admin');

    $response = actingAs($user)->get('/admin/test-trigger');
    // Use the response object directly: invoke the LoginResponse contract.
    $request = request();
    $request->setUserResolver(fn () => $user);

    $redirect = (new LoginResponse)->toResponse($request);
    expect($redirect->getTargetUrl())->toEndWith('/admin');
});

it('redirects cashier to /pos via LoginResponse', function () {
    $user = makeUser('cashier');
    $request = request();
    $request->setUserResolver(fn () => $user);

    $redirect = (new LoginResponse)->toResponse($request);
    expect($redirect->getTargetUrl())->toEndWith('/pos');
});

it('redirects kitchen to /kitchen via LoginResponse', function () {
    $user = makeUser('kitchen');
    $request = request();
    $request->setUserResolver(fn () => $user);

    $redirect = (new LoginResponse)->toResponse($request);
    expect($redirect->getTargetUrl())->toEndWith('/kitchen');
});

it('blocks unauthenticated access to /pos', function () {
    get('/pos')->assertRedirect('/login');
});

it('blocks unauthenticated access to /kitchen', function () {
    get('/kitchen')->assertRedirect('/login');
});

it('allows cashier to reach /pos', function () {
    actingAs(makeUser('cashier'))->get('/pos')->assertOk();
});

it('allows super_admin to reach /pos', function () {
    actingAs(makeUser('super_admin'))->get('/pos')->assertOk();
});

it('forbids kitchen from reaching /pos', function () {
    actingAs(makeUser('kitchen'))->get('/pos')->assertForbidden();
});

it('allows kitchen to reach /kitchen', function () {
    actingAs(makeUser('kitchen'))->get('/kitchen')->assertOk();
});

it('allows super_admin to reach /kitchen', function () {
    actingAs(makeUser('super_admin'))->get('/kitchen')->assertOk();
});

it('forbids cashier from reaching /kitchen', function () {
    actingAs(makeUser('cashier'))->get('/kitchen')->assertForbidden();
});

it('only permits super_admin to access the Filament admin panel', function () {
    expect(makeUser('super_admin')->canAccessPanel(filament()->getPanel('admin')))->toBeTrue()
        ->and(makeUser('cashier')->canAccessPanel(filament()->getPanel('admin')))->toBeFalse()
        ->and(makeUser('kitchen')->canAccessPanel(filament()->getPanel('admin')))->toBeFalse();
});
