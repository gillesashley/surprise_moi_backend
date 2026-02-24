<?php

namespace Database\Seeders;

use App\Models\BespokeService;
use Illuminate\Database\Seeder;

class BespokeServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            [
                'name' => 'Proposal Setup',
                'slug' => 'proposal-setup',
                'description' => 'Romantic, unique, and unforgettable, without the stress of planning it yourself',
                'icon' => '❤️',
                'image' => 'https://images.unsplash.com/photo-1519741497674-611481863552?w=800&h=600&fit=crop',
                'sort_order' => 1,
            ],
            [
                'name' => 'Same-Day Surprise',
                'slug' => 'same-day-surprise',
                'description' => 'Fast and reliable delivery to ensure surprises stay timely and fresh',
                'icon' => '⚡',
                'image' => 'https://images.unsplash.com/photo-1513885535751-8b9238bd345a?w=800&h=600&fit=crop',
                'sort_order' => 2,
            ],
            [
                'name' => 'Midnight Delivery',
                'slug' => 'midnight-delivery',
                'description' => 'Create unforgettable moments by delivering right at midnight.',
                'icon' => '🌙',
                'image' => 'https://images.unsplash.com/photo-1522673607200-164d1b6ce486?w=800&h=600&fit=crop',
                'sort_order' => 3,
            ],
            [
                'name' => 'Gift Wrapping',
                'slug' => 'gift-wrapping',
                'description' => 'Beautifully wrapped gifts that make the presentation as special as the gift itself',
                'icon' => '🎁',
                'image' => 'https://images.unsplash.com/photo-1513885535751-8b9238bd345a?w=800&h=600&fit=crop',
                'sort_order' => 4,
            ],
            [
                'name' => 'Personalized Cards',
                'slug' => 'personalized-cards',
                'description' => 'Custom greeting cards with your personal message',
                'icon' => '💌',
                'image' => 'https://images.unsplash.com/photo-1453738773917-9c3eff1db985?w=800&h=600&fit=crop',
                'sort_order' => 5,
            ],
        ];

        foreach ($services as $service) {
            BespokeService::updateOrCreate(
                ['slug' => $service['slug']],
                array_merge($service, ['is_active' => true])
            );
        }
    }
}
