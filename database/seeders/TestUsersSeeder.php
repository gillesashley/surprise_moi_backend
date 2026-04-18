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
        $users = [
            [
                'name' => 'Admin User',
                'email' => 'admin@surprisemoi.test',
                'phone' => '0240000000',
                'role' => 'admin',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Test Customer',
                'email' => 'customer@surprisemoi.test',
                'phone' => '0240000001',
                'role' => 'customer',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Test Vendor',
                'email' => 'vendor@surprisemoi.test',
                'phone' => '0240000002',
                'role' => 'vendor',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Unverified Customer',
                'email' => 'unverified@surprisemoi.test',
                'phone' => '0240000003',
                'role' => 'customer',
                'email_verified_at' => null,
            ],
        ];

        foreach ($users as $attributes) {
            User::updateOrCreate(
                ['email' => $attributes['email']],
                $attributes + ['password' => 'Password123!'],
            );
        }
    }
}
