<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Seed the three application roles: super_admin, cashier, kitchen.
     */
    public function run(): void
    {
        // Reset cached roles & permissions before creating to keep tests deterministic.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['super_admin', 'cashier', 'kitchen'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
