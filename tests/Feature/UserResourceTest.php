<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;
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

it('renders the user list page', function () {
    Livewire::test(ListUsers::class)
        ->assertOk()
        ->assertCanSeeTableRecords(User::all());
});

it('creates a new user with role assignment', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Kasir Baru',
            'email' => 'kasir-baru-'.uniqid().'@test.local',
            'password' => 'secret123',
            'roles' => ['cashier'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = User::where('name', 'Kasir Baru')->first();
    expect($created)->not->toBeNull()
        ->and($created->hasRole('cashier'))->toBeTrue()
        ->and(Hash::check('secret123', $created->password))->toBeTrue();
});

it('requires password on create', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'No Password',
            'email' => 'no-password-'.uniqid().'@test.local',
            'password' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['password']);
});

it('rejects duplicate email', function () {
    $existing = User::create([
        'name' => 'Existing',
        'email' => 'taken-'.uniqid().'@test.local',
        'password' => 'password',
    ]);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Dup',
            'email' => $existing->email,
            'password' => 'password',
        ])
        ->call('create')
        ->assertHasFormErrors(['email' => 'unique']);
});

it('keeps the existing password when edit form leaves password empty', function () {
    $user = User::create([
        'name' => 'Edit Me',
        'email' => 'edit-'.uniqid().'@test.local',
        'password' => Hash::make('original-password'),
    ]);
    $user->assignRole('cashier');
    $originalHash = $user->password;

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['name' => 'Edited Name', 'password' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();
    expect($user->name)->toBe('Edited Name')
        ->and($user->password)->toBe($originalHash)
        ->and(Hash::check('original-password', $user->password))->toBeTrue();
});

it('updates password only when provided on edit', function () {
    $user = User::create([
        'name' => 'Pwd Change',
        'email' => 'pwd-'.uniqid().'@test.local',
        'password' => Hash::make('old-password'),
    ]);
    $user->assignRole('cashier');

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['password' => 'brand-new-pwd'])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();
    expect(Hash::check('brand-new-pwd', $user->password))->toBeTrue()
        ->and(Hash::check('old-password', $user->password))->toBeFalse();
});

it('syncs roles on edit (add and remove)', function () {
    $user = User::create([
        'name' => 'Role Sync',
        'email' => 'role-'.uniqid().'@test.local',
        'password' => 'password',
    ]);
    $user->assignRole(['cashier', 'kitchen']);
    expect($user->roles->pluck('name')->all())->toEqualCanonicalizing(['cashier', 'kitchen']);

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['roles' => ['kitchen']])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh()->load('roles');
    expect($user->roles->pluck('name')->all())->toBe(['kitchen']);
});

it('lets a super_admin access the resource', function () {
    expect(UserResource::canAccess())->toBeTrue();
});

it('blocks non super_admin users from accessing the resource', function () {
    $cashier = User::create([
        'name' => 'C',
        'email' => 'c-'.uniqid().'@test.local',
        'password' => 'password',
    ]);
    $cashier->assignRole('cashier');

    actingAs($cashier);

    expect(UserResource::canAccess())->toBeFalse();
});

it('exposes the resource under "Pengaturan" navigation group', function () {
    expect(UserResource::getNavigationGroup())->toBe('Pengaturan')
        ->and(UserResource::getNavigationLabel())->toBe('Pengguna');
});

it('filters users by role', function () {
    $cashier = User::create(['name' => 'C', 'email' => 'cf-'.uniqid().'@test.local', 'password' => 'password']);
    $cashier->assignRole('cashier');

    $kitchen = User::create(['name' => 'K', 'email' => 'kf-'.uniqid().'@test.local', 'password' => 'password']);
    $kitchen->assignRole('kitchen');

    Livewire::test(ListUsers::class)
        ->filterTable('roles', ['cashier'])
        ->assertCanSeeTableRecords([$cashier])
        ->assertCanNotSeeTableRecords([$kitchen]);
});
