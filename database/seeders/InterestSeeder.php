<?php

namespace Database\Seeders;

use App\Models\Interest;
use Illuminate\Database\Seeder;

class InterestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $interests = [
            ['name' => 'Music', 'icon' => 'music'],
            ['name' => 'Travel', 'icon' => 'travel'],
            ['name' => 'Photography', 'icon' => 'photography'],
            ['name' => 'Art', 'icon' => 'art'],
            ['name' => 'Fashion', 'icon' => 'fashion'],
            ['name' => 'Food', 'icon' => 'food'],
            ['name' => 'Sports', 'icon' => 'sports'],
            ['name' => 'Gaming', 'icon' => 'gaming'],
            ['name' => 'Reading', 'icon' => 'reading'],
            ['name' => 'Movies', 'icon' => 'movies'],
            ['name' => 'Fitness', 'icon' => 'fitness'],
            ['name' => 'Technology', 'icon' => 'technology'],
            ['name' => 'Nature', 'icon' => 'nature'],
            ['name' => 'Cooking', 'icon' => 'cooking'],
            ['name' => 'Dancing', 'icon' => 'dancing'],
            ['name' => 'Writing', 'icon' => 'writing'],
            ['name' => 'Gardening', 'icon' => 'gardening'],
            ['name' => 'Pets', 'icon' => 'pets'],
            ['name' => 'DIY', 'icon' => 'diy'],
            ['name' => 'Volunteering', 'icon' => 'volunteering'],
        ];

        foreach ($interests as $interest) {
            Interest::firstOrCreate(
                ['name' => $interest['name']],
                ['icon' => $interest['icon']]
            );
        }
    }
}
