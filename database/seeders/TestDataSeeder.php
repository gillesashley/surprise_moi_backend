<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Review;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or create categories
        $categories = $this->ensureCategories();

        // Update some existing vendors to be popular or create new ones
        $vendors = $this->createPopularVendors();

        // Create shops for each vendor
        $shops = $this->createShops($vendors, $categories);

        // Create products with images for each shop
        $products = $this->createProductsWithImages($shops, $categories, $vendors);

        // Create reviews for products
        $this->createReviewsForProducts($products);

        $this->command->info('Test data seeded successfully!');
        $this->command->info("Created {$vendors->count()} vendors (some popular)");
        $this->command->info("Created {$shops->count()} shops");
        $this->command->info("Created {$products->count()} products with images");
        $this->command->info('Created reviews for products');
    }

    private function ensureCategories()
    {
        $categoryData = [
            [
                'name' => 'Gift Packages',
                'slug' => 'gift-packages',
                'type' => 'product',
                'description' => 'Curated gift packages for every occasion',
                'icon' => 'storage/icons/gift-box.png',
                'image' => 'storage/categories/gift-packages.jpg',
            ],
            [
                'name' => 'Flowers & Bouquets',
                'slug' => 'flowers-bouquets',
                'type' => 'product',
                'description' => 'Fresh flowers for every emotion',
                'icon' => 'storage/icons/flower.png',
                'image' => 'storage/categories/flowers.jpg',
            ],
            [
                'name' => 'Hampers & Food Baskets',
                'slug' => 'hampers-food-baskets',
                'type' => 'product',
                'description' => 'Delicious treats and food baskets',
                'icon' => 'storage/icons/hamper.png',
                'image' => 'storage/categories/hampers.jpg',
            ],
        ];

        $categories = collect();
        foreach ($categoryData as $data) {
            $category = Category::updateOrCreate(
                ['slug' => $data['slug']],
                array_merge($data, [
                    'is_active' => true,
                    'sort_order' => $categories->count() + 1,
                ])
            );
            $categories->push($category);
        }

        return $categories;
    }

    private function createPopularVendors()
    {
        $vendorEmails = [
            'vendor1@surprisemoi.com' => ['name' => 'Premium Gifts Ghana', 'is_popular' => true],
            'vendor2@surprisemoi.com' => ['name' => 'Elite Flowers & More', 'is_popular' => true],
            'vendor3@surprisemoi.com' => ['name' => 'Luxury Hampers Co', 'is_popular' => true],
            'vendor4@surprisemoi.com' => ['name' => 'Accra Gift Shop', 'is_popular' => false],
            'vendor5@surprisemoi.com' => ['name' => 'Kumasi Flowers', 'is_popular' => false],
        ];

        $vendors = collect();
        foreach ($vendorEmails as $email => $data) {
            $vendor = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $data['name'],
                    'role' => 'vendor',
                    'is_popular' => $data['is_popular'],
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ]
            );
            $vendors->push($vendor);
        }

        return $vendors;
    }

    private function createShops($vendors, $categories)
    {
        $shops = collect();

        foreach ($vendors as $vendor) {
            $category = $categories->random();

            $shop = Shop::updateOrCreate(
                [
                    'vendor_id' => $vendor->id,
                    'slug' => \Illuminate\Support\Str::slug($vendor->name.'-shop'),
                ],
                [
                    'category_id' => $category->id,
                    'name' => $vendor->name.' Shop',
                    'owner_name' => $vendor->name,
                    'description' => "Official shop for {$vendor->name}. We offer premium quality products with fast delivery.",
                    'logo' => 'storage/shops/'.strtolower(str_replace(' ', '-', $vendor->name)).'-logo.png',
                    'is_active' => true,
                    'location' => fake()->randomElement(['Accra', 'Kumasi', 'Takoradi', 'Tema']),
                    'phone' => fake()->phoneNumber(),
                    'email' => $vendor->email,
                ]
            );

            $shops->push($shop);
        }

        return $shops;
    }

    private function createProductsWithImages($shops, $categories, $vendors)
    {
        $products = collect();
        $imageUrls = $this->getSampleImageUrls();

        foreach ($shops as $shop) {
            $productsCount = rand(5, 15);

            for ($i = 0; $i < $productsCount; $i++) {
                $category = $categories->random();
                $price = fake()->randomFloat(2, 50, 1000);
                $hasDiscount = fake()->boolean(40);

                $product = Product::create([
                    'category_id' => $category->id,
                    'vendor_id' => $shop->vendor_id,
                    'shop_id' => $shop->id,
                    'name' => $this->generateProductName($category->name),
                    'description' => fake()->sentence(10),
                    'detailed_description' => fake()->paragraph(3),
                    'price' => $price,
                    'discount_price' => $hasDiscount ? round($price * 0.8, 2) : null,
                    'discount_percentage' => $hasDiscount ? 20 : null,
                    'currency' => 'GHS',
                    'thumbnail' => $imageUrls->random(),
                    'stock' => fake()->numberBetween(10, 100),
                    'is_available' => true,
                    'is_featured' => fake()->boolean(30),
                    'rating' => 0,
                    'reviews_count' => 0,
                    'sizes' => fake()->optional(0.3)->randomElement([
                        ['Small', 'Medium', 'Large'],
                        ['S', 'M', 'L', 'XL'],
                    ]),
                    'colors' => fake()->optional(0.3)->randomElement([
                        ['Red', 'Blue', 'Gold'],
                        ['White', 'Pink', 'Purple'],
                    ]),
                    'free_delivery' => fake()->boolean(20),
                    'delivery_fee' => fake()->randomFloat(2, 10, 50),
                    'estimated_delivery_days' => fake()->randomElement(['Same day', '1-2 days', '2-3 days']),
                    'return_policy' => '7 days return policy',
                ]);

                // Create 3-5 product images
                $imageCount = rand(3, 5);
                for ($j = 0; $j < $imageCount; $j++) {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $imageUrls->random(),
                        'sort_order' => $j,
                        'is_primary' => $j === 0,
                    ]);
                }

                $products->push($product);
            }
        }

        return $products;
    }

    private function createReviewsForProducts($products)
    {
        // Create some regular users for reviews
        $customers = User::factory(20)->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        foreach ($products as $product) {
            // Create 2-8 reviews per product
            $reviewCount = rand(2, 8);

            for ($i = 0; $i < $reviewCount; $i++) {
                $rating = fake()->numberBetween(3, 5); // Mostly positive reviews

                Review::create([
                    'user_id' => $customers->random()->id,
                    'reviewable_type' => Product::class,
                    'reviewable_id' => $product->id,
                    'rating' => $rating,
                    'comment' => $this->generateReviewComment($rating),
                    'images' => fake()->optional(0.3)->randomElements([
                        'storage/reviews/review-1.jpg',
                        'storage/reviews/review-2.jpg',
                        'storage/reviews/review-3.jpg',
                    ], rand(1, 2)),
                    'is_verified_purchase' => fake()->boolean(80),
                    'created_at' => now()->subDays(rand(1, 60)),
                ]);
            }

            // Update product rating and reviews count
            $avgRating = $product->reviews()->avg('rating');
            $reviewsCount = $product->reviews()->count();

            $product->update([
                'rating' => round($avgRating, 2),
                'reviews_count' => $reviewsCount,
            ]);
        }
    }

    private function generateProductName($category)
    {
        $prefixes = [
            'Premium',
            'Deluxe',
            'Luxury',
            'Classic',
            'Elegant',
            'Beautiful',
            'Exquisite',
            'Special',
        ];

        $suffixes = [
            'Collection',
            'Package',
            'Bundle',
            'Set',
            'Box',
            'Basket',
            'Arrangement',
        ];

        $prefix = fake()->randomElement($prefixes);
        $suffix = fake()->randomElement($suffixes);

        return "$prefix $category $suffix";
    }

    private function generateReviewComment($rating)
    {
        $comments = [
            5 => [
                'Absolutely amazing! Exceeded my expectations. The quality is outstanding and delivery was quick.',
                'Best purchase ever! Highly recommend to anyone looking for quality products.',
                'Perfect! Everything was exactly as described. Will definitely order again.',
                'Exceptional quality and service. Very impressed with this product!',
            ],
            4 => [
                'Very good product. Minor issues but overall satisfied with the purchase.',
                'Great quality! Just took a bit longer to arrive than expected.',
                'Good value for money. Would recommend with minor reservations.',
                'Nice product! Almost perfect, just a few small details could be improved.',
            ],
            3 => [
                'Decent product. Met basic expectations but nothing special.',
                'Average quality. It works but could be better for the price.',
                'Okay purchase. Some aspects good, others not so much.',
            ],
        ];

        return fake()->randomElement($comments[$rating] ?? $comments[4]);
    }

    private function getSampleImageUrls()
    {
        // Using placeholder image URLs - these can be replaced with actual images
        return collect([
            'https://images.unsplash.com/photo-1513885535751-8b9238bd345a?w=400',
            'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=400',
            'https://images.unsplash.com/photo-1546868871-0513cb84e1bb?w=400',
            'https://images.unsplash.com/photo-1512274044689-f810f1498f4f?w=400',
            'https://images.unsplash.com/photo-1606800052052-1e99e07b56a2?w=400',
            'https://images.unsplash.com/photo-1557672172-298e090bd0f1?w=400',
            'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=400',
            'https://images.unsplash.com/photo-1566354204085-44bacdbf9242?w=400',
            'https://images.unsplash.com/photo-1586495777744-4413f21062fa?w=400',
            'https://images.unsplash.com/photo-1602173574767-37ac01994b2a?w=400',
        ]);
    }
}
