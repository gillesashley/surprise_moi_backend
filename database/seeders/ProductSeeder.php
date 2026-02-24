<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Truncate existing data
        \App\Models\ProductImage::truncate();
        \App\Models\ProductVariant::truncate();
        DB::table('product_tag')->truncate();
        \App\Models\Product::truncate();
        \App\Models\Service::truncate();
        \App\Models\Tag::truncate();
        \App\Models\Category::truncate();

        // Create categories
        $giftBoxes = \App\Models\Category::create([
            'name' => 'Gift Boxes',
            'slug' => 'gift-boxes',
            'description' => 'Curated gift packages for every occasion',
            'icon' => 'storage/icons/gift-box.png',
            'image' => 'storage/categories/gift-boxes.jpg',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $flowers = \App\Models\Category::create([
            'name' => 'Flowers & Bouquets',
            'slug' => 'flowers',
            'description' => 'Fresh flowers for every emotion',
            'icon' => 'storage/icons/flower.png',
            'image' => 'storage/categories/flowers.jpg',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $hampers = \App\Models\Category::create([
            'name' => 'Food & Hampers',
            'slug' => 'food-hampers',
            'description' => 'Delicious treats and food baskets',
            'icon' => 'storage/icons/hamper.png',
            'image' => 'storage/categories/hampers.jpg',
            'is_active' => true,
            'sort_order' => 3,
        ]);

        $services = \App\Models\Category::create([
            'name' => 'Bespoke Services',
            'slug' => 'bespoke-services',
            'description' => 'Custom services for special moments',
            'icon' => 'storage/icons/service.png',
            'image' => 'storage/categories/services.jpg',
            'is_active' => true,
            'sort_order' => 4,
        ]);

        // Create vendors (users with vendor role)
        $vendors = \App\Models\User::factory(10)->create([
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);

        // Create tags
        $tags = collect(['roses', 'premium', 'gift', 'romantic', 'birthday', 'anniversary', 'luxury', 'handmade'])
            ->map(fn ($name) => \App\Models\Tag::create([
                'name' => $name,
                'slug' => \Illuminate\Support\Str::slug($name),
            ]));

        // Create products for each category
        foreach ([$giftBoxes, $flowers, $hampers, $services] as $category) {
            for ($i = 0; $i < 20; $i++) {
                $product = \App\Models\Product::factory()->create([
                    'category_id' => $category->id,
                    'vendor_id' => $vendors->random()->id,
                ]);

                // Attach random tags
                $product->tags()->attach($tags->random(rand(2, 4)));

                // Create images
                for ($j = 0; $j < rand(2, 5); $j++) {
                    \App\Models\ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => 'storage/products/product-'.$product->id.'-'.$j.'.jpg',
                        'sort_order' => $j,
                        'is_primary' => $j === 0,
                    ]);
                }

                // Create variants
                if ($product->sizes && $product->colors) {
                    foreach ($product->sizes as $size) {
                        foreach ($product->colors as $color) {
                            \App\Models\ProductVariant::create([
                                'product_id' => $product->id,
                                'name' => "$size - $color",
                                'price' => $product->price,
                                'stock' => rand(5, 20),
                                'size' => $size,
                                'color' => $color,
                            ]);
                        }
                    }
                }
            }
        }

        // Create services
        \App\Models\Service::factory(30)
            ->create([
                'vendor_id' => $vendors->random()->id,
            ]);
    }
}
