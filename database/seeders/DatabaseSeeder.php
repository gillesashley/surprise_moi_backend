<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Removed automatic creation of a test user (test@example.com)
        // If you need test users for local development, use factories or
        // create them conditionally in a local-only seeder.

        // Seed initial categories
        $this->call([
            CategorySeeder::class,
            AdminUserSeeder::class,
            TestUsersSeeder::class,
            InterestSeeder::class,
            PersonalityTraitSeeder::class,
            BespokeServiceSeeder::class,
            MusicGenreSeeder::class,
            VendorApplicationSeeder::class,
            VendorSeeder::class,
            InfluencerMarketerFieldAgentSeeder::class,
        ]);
    }
}
