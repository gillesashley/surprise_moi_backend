<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Gift Packages', 'icon' => 'images/category-icons/gift-box.png'],
            ['name' => 'Flowers & Bouquets', 'icon' => 'images/category-icons/bouquet.png'],
            ['name' => 'Hampers & Food Baskets', 'icon' => 'images/category-icons/hampers.png'],
            ['name' => 'Cakes & Pastries', 'icon' => 'images/category-icons/cake.png'],
            ['name' => 'Chocolates & Sweets', 'icon' => 'images/category-icons/necklace.png'],
            ['name' => 'Custom Engraved Gifts', 'icon' => 'images/category-icons/art.png'],
            ['name' => 'Surprise Musicians', 'icon' => 'images/category-icons/saxophone.png'],
            ['name' => 'Event Decorators', 'icon' => 'images/category-icons/party.png'],
            ['name' => 'Photo & Videographers', 'icon' => 'images/category-icons/photographer.png'],
            ['name' => 'Event Planners', 'icon' => 'images/category-icons/wedding-arch.png'],
            ['name' => 'MCs/Hosts', 'icon' => 'images/category-icons/bar.png'],
            ['name' => 'Makeup Artists', 'icon' => 'images/category-icons/facial-treatment.png'],
            ['name' => 'Same-Day Surprise Delivery', 'icon' => 'images/category-icons/fast-delivery.png'],
            ['name' => 'Midnight Delivery', 'icon' => 'images/category-icons/moon.png'],
            ['name' => 'Personal Delivery Agents', 'icon' => 'images/category-icons/courier.png'],
            ['name' => 'Poets / Spoken Word Artists', 'icon' => 'images/category-icons/poetry.png'],
            ['name' => 'Custom Handwritten Notes', 'icon' => 'images/category-icons/caligraphy.png'],
            ['name' => 'Voice / Video Messages', 'icon' => 'images/category-icons/video-message.png'],
            ['name' => 'Mascot Performers', 'icon' => 'images/category-icons/bear.png'],
            ['name' => 'Spa & Self-Care/ Aromatherapy / Candles', 'icon' => 'images/category-icons/lamp.png'],
            ['name' => 'Book Surprise Boxes', 'icon' => 'images/category-icons/books-stack-of-three.png'],
            ['name' => 'Kids Birthday Planners', 'icon' => 'images/category-icons/friends.png'],
            ['name' => 'Family-Themed Packs', 'icon' => 'images/category-icons/father.png'],
        ];

        $created = 0;
        $updated = 0;

        foreach ($categories as $category) {
            $model = \App\Models\Category::updateOrCreate(
                ['slug' => \Illuminate\Support\Str::slug($category['name'])],
                [
                    'name' => $category['name'],
                    'icon' => $category['icon'],
                ]
            );

            if ($model->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        if ($this->command) {
            $this->command->info("Categories seeded: created={$created}, updated={$updated}");
        }
    }
}
