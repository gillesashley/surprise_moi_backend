<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Seeder;

class ShopSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create vendors with multiple shops
        $vendor1 = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john.vendor@example.com',
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);

        $vendor2 = User::factory()->create([
            'name' => 'Jane Smith',
            'email' => 'jane.vendor@example.com',
            'role' => 'vendor',
            'email_verified_at' => now(),
        ]);

        // Get some categories
        $categories = Category::limit(5)->get();

        // Vendor 1 - Multiple shops
        $shop1 = Shop::factory()->create([
            'vendor_id' => $vendor1->id,
            'category_id' => $categories->isNotEmpty() ? $categories->random()->id : Category::factory()->create()->id,
            'name' => 'Accra Electronics Store',
            'owner_name' => 'John Doe',
            'slug' => 'accra-electronics-store',
            'description' => 'Your one-stop shop for all electronics in Accra',
            'location' => 'Accra, Ghana',
            'is_active' => true,
        ]);

        $shop2 = Shop::factory()->create([
            'vendor_id' => $vendor1->id,
            'category_id' => $categories->isNotEmpty() ? $categories->random()->id : Category::factory()->create()->id,
            'name' => 'Kumasi Fashion Boutique',
            'owner_name' => 'John Doe',
            'slug' => 'kumasi-fashion-boutique',
            'description' => 'Premium fashion and accessories in Kumasi',
            'location' => 'Kumasi, Ghana',
            'is_active' => true,
        ]);

        $shop3 = Shop::factory()->create([
            'vendor_id' => $vendor1->id,
            'category_id' => $categories->isNotEmpty() ? $categories->random()->id : Category::factory()->create()->id,
            'name' => 'Tamale Home Goods',
            'owner_name' => 'John Doe',
            'slug' => 'tamale-home-goods',
            'description' => 'Quality home goods and furniture',
            'location' => 'Tamale, Ghana',
            'is_active' => false, // Inactive shop
        ]);

        // Vendor 2 - Multiple shops
        $shop4 = Shop::factory()->create([
            'vendor_id' => $vendor2->id,
            'category_id' => $categories->isNotEmpty() ? $categories->random()->id : Category::factory()->create()->id,
            'name' => 'Cape Coast Event Services',
            'owner_name' => 'Jane Smith',
            'slug' => 'cape-coast-event-services',
            'description' => 'Professional event planning and services',
            'location' => 'Cape Coast, Ghana',
            'is_active' => true,
        ]);

        $shop5 = Shop::factory()->create([
            'vendor_id' => $vendor2->id,
            'category_id' => $categories->isNotEmpty() ? $categories->random()->id : Category::factory()->create()->id,
            'name' => 'Tema Tech Hub',
            'owner_name' => 'Jane Smith',
            'slug' => 'tema-tech-hub',
            'description' => 'Latest gadgets and tech accessories',
            'location' => 'Tema, Ghana',
            'is_active' => true,
        ]);

        // Add products to shops
        if ($categories->isNotEmpty()) {
            // Shop 1 - Electronics
            Product::factory()->count(10)->create([
                'vendor_id' => $vendor1->id,
                'shop_id' => $shop1->id,
                'category_id' => $categories->random()->id,
                'is_available' => true,
            ]);

            // Shop 2 - Fashion
            Product::factory()->count(15)->create([
                'vendor_id' => $vendor1->id,
                'shop_id' => $shop2->id,
                'category_id' => $categories->random()->id,
                'is_available' => true,
            ]);

            // Shop 3 - Home Goods (inactive shop)
            Product::factory()->count(5)->create([
                'vendor_id' => $vendor1->id,
                'shop_id' => $shop3->id,
                'category_id' => $categories->random()->id,
                'is_available' => true,
            ]);

            // Shop 5 - Tech Hub
            Product::factory()->count(8)->create([
                'vendor_id' => $vendor2->id,
                'shop_id' => $shop5->id,
                'category_id' => $categories->random()->id,
                'is_available' => true,
            ]);
        }

        // Add services to shops
        // Shop 4 - Event Services
        Service::factory()->count(6)->create([
            'vendor_id' => $vendor2->id,
            'shop_id' => $shop4->id,
            'service_type' => 'event-planner',
            'availability' => 'available',
        ]);

        Service::factory()->count(3)->create([
            'vendor_id' => $vendor2->id,
            'shop_id' => $shop4->id,
            'service_type' => 'photographer',
            'availability' => 'available',
        ]);

        // Shop 2 also has some services
        Service::factory()->count(4)->create([
            'vendor_id' => $vendor1->id,
            'shop_id' => $shop2->id,
            'service_type' => 'decorator',
            'availability' => 'available',
        ]);

        $this->command->info('✓ Created 5 shops for 2 vendors');
        $this->command->info('✓ Shop 1 (Accra Electronics): 10 products');
        $this->command->info('✓ Shop 2 (Kumasi Fashion): 15 products, 4 services');
        $this->command->info('✓ Shop 3 (Tamale Home): 5 products (inactive)');
        $this->command->info('✓ Shop 4 (Cape Coast Events): 9 services');
        $this->command->info('✓ Shop 5 (Tema Tech): 8 products');
    }
}
