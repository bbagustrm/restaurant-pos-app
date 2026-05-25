<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed the three demo users matching the project specification:
     *  - admin@pos.test  / password → super_admin
     *  - kasir@pos.test  / password → cashier
     *  - dapur@pos.test  / password → kitchen
     */
    public function run(): void
    {
        $accounts = [
            ['name' => 'Admin', 'email' => 'admin@pos.test', 'role' => 'super_admin'],
            ['name' => 'Kasir', 'email' => 'kasir@pos.test', 'role' => 'cashier'],
            ['name' => 'Dapur', 'email' => 'dapur@pos.test', 'role' => 'kitchen'],
        ];

        foreach ($accounts as $account) {
            $user = User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );

            if (! $user->hasRole($account['role'])) {
                $user->assignRole($account['role']);
            }
        }
    }
}
