<?php

namespace Database\Seeders;

use App\Models\PersonalityTrait;
use Illuminate\Database\Seeder;

class PersonalityTraitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $traits = [
            ['name' => 'Adventurous', 'icon' => 'adventurous'],
            ['name' => 'Creative', 'icon' => 'creative'],
            ['name' => 'Outgoing', 'icon' => 'outgoing'],
            ['name' => 'Introverted', 'icon' => 'introverted'],
            ['name' => 'Romantic', 'icon' => 'romantic'],
            ['name' => 'Practical', 'icon' => 'practical'],
            ['name' => 'Spontaneous', 'icon' => 'spontaneous'],
            ['name' => 'Organized', 'icon' => 'organized'],
            ['name' => 'Minimalist', 'icon' => 'minimalist'],
            ['name' => 'Luxury-Lover', 'icon' => 'luxury'],
            ['name' => 'Eco-Conscious', 'icon' => 'eco'],
            ['name' => 'Tech-Savvy', 'icon' => 'tech'],
            ['name' => 'Traditional', 'icon' => 'traditional'],
            ['name' => 'Modern', 'icon' => 'modern'],
            ['name' => 'Fun-Loving', 'icon' => 'fun'],
            ['name' => 'Sophisticated', 'icon' => 'sophisticated'],
            ['name' => 'Laid-Back', 'icon' => 'laid_back'],
            ['name' => 'Health-Conscious', 'icon' => 'health'],
            ['name' => 'Family-Oriented', 'icon' => 'family'],
            ['name' => 'Career-Focused', 'icon' => 'career'],
        ];

        foreach ($traits as $trait) {
            PersonalityTrait::firstOrCreate(
                ['name' => $trait['name']],
                ['icon' => $trait['icon']]
            );
        }
    }
}
