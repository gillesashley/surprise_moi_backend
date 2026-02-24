<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the super admin user for dashboard access.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'xylaray37@gmail.com'],
            [
                'name' => 'Gilles Ashley',
                'phone' => '0240000099',
                'password' => 'Gilash@123',
                'role' => 'super_admin',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]
        );
    }
}
