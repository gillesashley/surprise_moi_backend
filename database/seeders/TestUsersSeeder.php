<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class TestUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Admin user
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@surprisemoi.test',
            'phone' => '0240000000',
            'password' => 'Password123!',
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // Test customer
        User::create([
            'name' => 'Test Customer',
            'email' => 'customer@surprisemoi.test',
            'phone' => '0240000001',
            'password' => 'Password123!',
            'role' => 'customer',
            'email_verified_at' => now(),
        ]);

        // Test vendor
        User::create([
            'name' => 'Test Vendor',
            'email' => 'vendor@surprisemoi.test',
            'phone' => '0240000002',
            'password' => 'Password123!',
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);

        // Additional test customer (unverified)
        User::create([
            'name' => 'Unverified Customer',
            'email' => 'unverified@surprisemoi.test',
            'phone' => '0240000003',
            'password' => 'Password123!',
            'role' => 'customer',
            'email_verified_at' => null,
        ]);
    }
}
