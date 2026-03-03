<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shop;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Embeddings;

class GiftProductSeeder extends Seeder
{
    /**
     * Seed realistic Ghanaian gift products for AI chat testing.
     */
    public function run(): void
    {
        // Create categories
        $categories = $this->createCategories();

        // Create vendor + shop
        $vendor = User::firstOrCreate(
            ['email' => 'vendor@surprisemoi.test'],
            [
                'name' => 'Surprise Moi Official',
                'role' => 'vendor',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ]
        );

        $shop = Shop::firstOrCreate(
            ['slug' => 'surprise-moi-gift-store'],
            [
                'vendor_id' => $vendor->id,
                'category_id' => $categories['gift-boxes']->id,
                'name' => 'Surprise Moi Gift Store',
                'owner_name' => $vendor->name,
                'description' => 'Premium curated gifts in Accra, Ghana',
                'location' => 'Accra, Ghana',
                'is_active' => true,
            ]
        );

        // Create tags
        $tags = $this->createTags();

        // Create products per category
        $products = $this->getProductData();

        foreach ($products as $product) {
            $categorySlug = $product['category'];
            $category = $categories[$categorySlug] ?? $categories['gift-boxes'];

            $price = $product['price'];
            $hasDiscount = isset($product['discount_price']);

            $model = Product::firstOrCreate(['name' => $product['name']], [
                'category_id' => $category->id,
                'vendor_id' => $vendor->id,
                'shop_id' => $shop->id,
                'name' => $product['name'],
                'description' => $product['description'],
                'detailed_description' => $product['detailed_description'],
                'price' => $price,
                'discount_price' => $hasDiscount ? $product['discount_price'] : null,
                'discount_percentage' => $hasDiscount ? (int) round((1 - $product['discount_price'] / $price) * 100) : null,
                'currency' => 'GHS',
                'thumbnail' => 'storage/products/'.Str::slug($product['name']).'.jpg',
                'stock' => rand(5, 50),
                'is_available' => true,
                'is_featured' => $product['featured'] ?? false,
                'rating' => $product['rating'] ?? round(mt_rand(35, 50) / 10, 1),
                'reviews_count' => rand(5, 200),
                'free_delivery' => $price >= 200,
                'delivery_fee' => $price >= 200 ? 0 : 15.00,
                'estimated_delivery_days' => 'Same day',
                'return_policy' => '7 days return policy',
            ]);

            if (! $model->wasRecentlyCreated) {
                continue;
            }

            // Attach tags
            $productTags = $product['tags'] ?? [];
            $tagIds = collect($productTags)
                ->map(fn ($t) => $tags[$t] ?? null)
                ->filter()
                ->pluck('id');
            $model->tags()->attach($tagIds);

            // Create primary image
            ProductImage::create([
                'product_id' => $model->id,
                'image_path' => 'storage/products/'.Str::slug($product['name']).'.jpg',
                'sort_order' => 0,
                'is_primary' => true,
            ]);
        }

        $this->command->info('Created '.count($products).' gift products.');

        // Generate embeddings
        $this->generateEmbeddings();
    }

    /**
     * @return array<string, Category>
     */
    private function createCategories(): array
    {
        $list = [
            ['name' => 'Gift Boxes', 'slug' => 'gift-boxes', 'description' => 'Curated gift packages for every occasion', 'sort_order' => 1],
            ['name' => 'Flowers & Bouquets', 'slug' => 'flowers', 'description' => 'Fresh flowers for every emotion', 'sort_order' => 2],
            ['name' => 'Food & Hampers', 'slug' => 'food-hampers', 'description' => 'Delicious treats and food baskets', 'sort_order' => 3],
            ['name' => 'Books & Stationery', 'slug' => 'books-stationery', 'description' => 'Books, journals and stationery gifts', 'sort_order' => 4],
            ['name' => 'Spa & Self-Care', 'slug' => 'spa-self-care', 'description' => 'Relaxation and pampering gifts', 'sort_order' => 5],
            ['name' => 'Fashion & Accessories', 'slug' => 'fashion-accessories', 'description' => 'Clothing, jewelry and accessories', 'sort_order' => 6],
            ['name' => 'Home & Kitchen', 'slug' => 'home-kitchen', 'description' => 'Homeware, cookware and decor', 'sort_order' => 7],
            ['name' => 'Tech & Gadgets', 'slug' => 'tech-gadgets', 'description' => 'Electronics, gadgets and accessories', 'sort_order' => 8],
            ['name' => 'Outdoor & Adventure', 'slug' => 'outdoor-adventure', 'description' => 'Gear for hiking, travel and adventure', 'sort_order' => 9],
            ['name' => 'Chocolates & Sweets', 'slug' => 'chocolates-sweets', 'description' => 'Premium chocolates and confections', 'sort_order' => 10],
        ];

        $result = [];
        foreach ($list as $cat) {
            $model = Category::updateOrCreate(
                ['slug' => $cat['slug']],
                [
                    'name' => $cat['name'],
                    'description' => $cat['description'],
                    'is_active' => true,
                    'sort_order' => $cat['sort_order'],
                    'type' => 'product',
                ]
            );
            $result[$cat['slug']] = $model;
        }

        return $result;
    }

    /**
     * @return array<string, Tag>
     */
    private function createTags(): array
    {
        $tagNames = [
            'romantic', 'birthday', 'anniversary', 'luxury', 'handmade',
            'nature', 'cooking', 'reading', 'relaxation', 'adventure',
            'tech', 'fitness', 'creative', 'premium', 'budget-friendly',
        ];

        $result = [];
        foreach ($tagNames as $name) {
            $result[$name] = Tag::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getProductData(): array
    {
        return [
            // Gift Boxes
            ['name' => 'Romantic Evening Gift Box', 'category' => 'gift-boxes', 'price' => 250.00, 'discount_price' => 220.00, 'featured' => true, 'tags' => ['romantic', 'luxury', 'anniversary'], 'description' => 'A curated romantic gift box with scented candles, chocolates and wine.', 'detailed_description' => 'Make your evening unforgettable with this premium romantic gift box. Includes Ghanaian artisan candles, imported Belgian chocolates, a bottle of rosé wine, and a handwritten love note card. Perfect for anniversaries, date nights, or just because.'],
            ['name' => 'Birthday Surprise Box', 'category' => 'gift-boxes', 'price' => 180.00, 'featured' => true, 'tags' => ['birthday', 'premium'], 'description' => 'A complete birthday surprise package with cake voucher and gifts.', 'detailed_description' => 'Everything you need for a birthday surprise! Includes a cake voucher from a top Accra bakery, party poppers, a personalised birthday card, assorted snacks, and a small wrapped gift. Suitable for all ages.'],
            ['name' => 'Self-Care Pamper Box', 'category' => 'gift-boxes', 'price' => 200.00, 'tags' => ['relaxation', 'luxury'], 'description' => 'A luxurious self-care box with bath bombs, face masks and body oils.', 'detailed_description' => 'Treat someone special (or yourself!) to the ultimate pamper session. Contains shea butter body oil, lavender bath bombs, clay face masks, a silk scrunchie set, and herbal tea. All products are locally sourced in Ghana.'],
            ['name' => 'New Baby Welcome Box', 'category' => 'gift-boxes', 'price' => 300.00, 'tags' => ['premium', 'handmade'], 'description' => 'A beautiful welcome box for newborns with organic baby essentials.', 'detailed_description' => 'Welcome the newest family member with this thoughtful box containing organic cotton onesies, a knitted baby blanket, natural baby lotion, a wooden teething ring, and a keepsake photo frame.'],

            // Flowers & Bouquets
            ['name' => 'Classic Red Rose Bouquet', 'category' => 'flowers', 'price' => 150.00, 'featured' => true, 'tags' => ['romantic', 'anniversary'], 'description' => '12 long-stem red roses beautifully arranged with greenery.', 'detailed_description' => 'Express your love with this stunning bouquet of 12 premium long-stem red roses, artfully arranged with eucalyptus greenery and tied with a satin ribbon. Delivered fresh in Accra.'],
            ['name' => 'Sunshine Sunflower Bouquet', 'category' => 'flowers', 'price' => 120.00, 'tags' => ['birthday', 'nature'], 'description' => 'Bright sunflowers to bring joy and warmth.', 'detailed_description' => 'A cheerful arrangement of 8 large sunflowers with baby\'s breath and rustic burlap wrapping. Perfect for birthdays, get-well wishes, or brightening someone\'s day.'],
            ['name' => 'Tropical Orchid Arrangement', 'category' => 'flowers', 'price' => 280.00, 'tags' => ['luxury', 'premium'], 'description' => 'Elegant tropical orchids in a decorative ceramic pot.', 'detailed_description' => 'A sophisticated arrangement of white and purple phalaenopsis orchids in a hand-painted Ghanaian ceramic pot. Long-lasting and low maintenance — the perfect statement gift.'],
            ['name' => 'Wildflower Meadow Bouquet', 'category' => 'flowers', 'price' => 95.00, 'tags' => ['nature', 'budget-friendly'], 'description' => 'A rustic mix of seasonal wildflowers for nature lovers.', 'detailed_description' => 'A natural, free-spirited bouquet featuring seasonal wildflowers, daisies, and dried grasses. Wrapped in kraft paper for a bohemian feel. Ideal for anyone who loves the outdoors.'],

            // Food & Hampers
            ['name' => 'Ghanaian Gourmet Hamper', 'category' => 'food-hampers', 'price' => 350.00, 'featured' => true, 'tags' => ['premium', 'luxury'], 'description' => 'A premium hamper with local Ghanaian delicacies and artisan products.', 'detailed_description' => 'Celebrate Ghanaian flavours with this curated hamper. Includes palm wine, dried fruits, shito (hot pepper sauce), artisan groundnut paste, smoked fish, plantain chips, and Ghana-origin chocolate. Beautifully packed in a woven basket.'],
            ['name' => 'Chocolate Lovers Hamper', 'category' => 'food-hampers', 'price' => 220.00, 'tags' => ['romantic', 'luxury'], 'description' => 'An indulgent hamper filled with premium chocolates and cocoa treats.', 'detailed_description' => 'A paradise for chocolate lovers. Includes single-origin Ghanaian cocoa chocolate bars, chocolate truffles, cocoa butter lip balm, hot cocoa mix, and chocolate-dipped strawberries. Packaged in a keepsake box.'],
            ['name' => 'Healthy Snack Basket', 'category' => 'food-hampers', 'price' => 160.00, 'tags' => ['fitness', 'nature'], 'description' => 'A curated basket of healthy, organic snacks and superfoods.', 'detailed_description' => 'For the health-conscious friend. Contains dried mango, moringa tea, tiger nuts, baobab powder, roasted cashews, and honey. All sourced from Ghanaian farms. Great for fitness enthusiasts.'],
            ['name' => 'Tea & Biscuits Gift Set', 'category' => 'food-hampers', 'price' => 110.00, 'tags' => ['relaxation', 'budget-friendly'], 'description' => 'A cosy collection of premium teas and artisan biscuits.', 'detailed_description' => 'A comforting gift for tea lovers. Features hibiscus tea, moringa green tea, ginger turmeric blend, and assorted shortbread biscuits. Comes in a beautiful tin box.'],

            // Books & Stationery
            ['name' => 'Leather-Bound Journal Set', 'category' => 'books-stationery', 'price' => 85.00, 'tags' => ['reading', 'creative', 'handmade'], 'description' => 'A handcrafted leather journal with a wooden pen.', 'detailed_description' => 'For the writer or planner in your life. This beautiful leather-bound journal features 200 pages of ivory paper, a pen loop, and comes with a hand-turned wooden pen. Handmade by Ghanaian artisans.'],
            ['name' => 'African Literature Collection', 'category' => 'books-stationery', 'price' => 130.00, 'tags' => ['reading', 'premium'], 'description' => 'A curated set of 3 bestselling African novels.', 'detailed_description' => 'A gift for bookworms. Includes three celebrated African novels: a classic by Chinua Achebe, a contemporary piece by Chimamanda Ngozi Adichie, and a Ghanaian gem by Ama Ata Aidoo. Wrapped with a custom bookmark.'],
            ['name' => 'Stationery Lovers Box', 'category' => 'books-stationery', 'price' => 75.00, 'tags' => ['creative', 'budget-friendly'], 'description' => 'A delightful box of premium stationery and art supplies.', 'detailed_description' => 'Includes watercolour postcards, fine-tip pens, washi tape, stickers, a mini sketchpad, and coloured pencils. Perfect for anyone who loves writing, journaling, or art.'],
            ['name' => 'Cookbook: Modern Ghanaian Kitchen', 'category' => 'books-stationery', 'price' => 95.00, 'tags' => ['cooking', 'reading'], 'description' => 'A beautiful cookbook featuring modern twists on Ghanaian recipes.', 'detailed_description' => 'Over 80 recipes reimagining classic Ghanaian dishes with modern techniques. Features jollof variations, kelewele twists, light soups, and fusion desserts. Stunning photography and personal stories from Ghanaian chefs.'],

            // Spa & Self-Care
            ['name' => 'Shea Butter Spa Collection', 'category' => 'spa-self-care', 'price' => 175.00, 'featured' => true, 'tags' => ['relaxation', 'handmade', 'luxury'], 'description' => 'Premium Ghanaian shea butter skincare set with body butter, soap and lip balm.', 'detailed_description' => 'Sourced from Northern Ghana, this collection includes raw shea body butter, black soap, shea lip balm, and a shea-infused hair mask. Handmade by women\'s cooperatives. Nourishes and hydrates all skin types.'],
            ['name' => 'Aromatherapy Candle Set', 'category' => 'spa-self-care', 'price' => 120.00, 'tags' => ['relaxation', 'luxury'], 'description' => 'Three hand-poured soy candles with relaxing essential oil scents.', 'detailed_description' => 'Create a calming atmosphere with this trio of soy candles: Lavender Dreams, Citrus Sunrise, and Cocoa Comfort. Hand-poured in Accra using essential oils. Each candle burns for 40+ hours.'],
            ['name' => 'Detox & Glow Face Mask Kit', 'category' => 'spa-self-care', 'price' => 90.00, 'tags' => ['relaxation', 'budget-friendly'], 'description' => 'A set of 5 natural face masks for radiant skin.', 'detailed_description' => 'Five single-use face masks made with kaolin clay, turmeric, charcoal, honey, and aloe vera. All-natural ingredients sourced in Ghana. Includes a bamboo application brush.'],

            // Fashion & Accessories
            ['name' => 'Kente Print Silk Scarf', 'category' => 'fashion-accessories', 'price' => 140.00, 'tags' => ['luxury', 'handmade'], 'description' => 'A beautiful silk scarf with traditional Kente patterns.', 'detailed_description' => 'A stunning silk scarf featuring authentic Kente-inspired patterns. Hand-printed in Kumasi. Versatile enough for formal occasions or casual styling. Comes in a gift pouch.'],
            ['name' => 'Beaded Bracelet Gift Set', 'category' => 'fashion-accessories', 'price' => 65.00, 'tags' => ['handmade', 'budget-friendly'], 'description' => 'A set of 3 handmade Ghanaian glass bead bracelets.', 'detailed_description' => 'Three uniquely coloured glass bead bracelets handmade by artisans in Krobo, Ghana. Each bracelet features recycled glass beads with traditional patterns. Comes in a hand-woven pouch.'],
            ['name' => 'African Print Tote Bag', 'category' => 'fashion-accessories', 'price' => 80.00, 'tags' => ['handmade', 'creative'], 'description' => 'A vibrant Ankara print tote bag perfect for everyday use.', 'detailed_description' => 'A sturdy cotton tote bag with a bold African wax print exterior, inner pocket, and magnetic closure. Perfect for shopping, work, or the beach. Handmade in Accra.'],

            // Home & Kitchen
            ['name' => 'Handwoven Bolga Basket', 'category' => 'home-kitchen', 'price' => 110.00, 'tags' => ['handmade', 'nature'], 'description' => 'A colourful handwoven basket from Bolgatanga, Ghana.', 'detailed_description' => 'Each basket is uniquely handwoven from elephant grass by artisans in Bolgatanga. Perfect as a fruit basket, storage, or home décor. Sturdy, sustainable, and beautiful.'],
            ['name' => 'Ceramic Spice Jar Set', 'category' => 'home-kitchen', 'price' => 95.00, 'tags' => ['cooking', 'handmade'], 'description' => 'A set of 4 hand-painted ceramic spice jars for the kitchen.', 'detailed_description' => 'Four beautiful ceramic jars with wooden lids, hand-painted with Adinkra symbols. Perfect for storing spices, herbs, or tea. A functional and decorative gift for anyone who loves cooking.'],
            ['name' => 'Premium Cooking Utensil Set', 'category' => 'home-kitchen', 'price' => 150.00, 'tags' => ['cooking', 'premium'], 'description' => 'A 5-piece wooden cooking utensil set with an organic finish.', 'detailed_description' => 'Five essential kitchen utensils hand-carved from sustainable African teak wood: a spatula, serving spoon, slotted spoon, ladle, and tongs. Treated with food-safe oil. Comes in a cotton drawstring bag.'],

            // Tech & Gadgets
            ['name' => 'Wireless Bluetooth Speaker', 'category' => 'tech-gadgets', 'price' => 180.00, 'tags' => ['tech', 'premium'], 'description' => 'A portable waterproof Bluetooth speaker with 12-hour battery.', 'detailed_description' => 'Take your music anywhere. This compact Bluetooth 5.0 speaker delivers rich bass and crystal-clear sound. Waterproof (IPX7), 12-hour battery life, and built-in microphone for calls. Perfect for outdoor adventures.'],
            ['name' => 'Smart Fitness Tracker Band', 'category' => 'tech-gadgets', 'price' => 200.00, 'tags' => ['tech', 'fitness'], 'description' => 'A sleek fitness tracker with heart rate, sleep and step monitoring.', 'detailed_description' => 'Track your fitness goals with this lightweight band. Features heart rate monitoring, sleep tracking, step counter, notification alerts, and 7-day battery life. Water-resistant and compatible with Android and iOS.'],
            ['name' => 'LED Reading Lamp', 'category' => 'tech-gadgets', 'price' => 75.00, 'tags' => ['reading', 'tech', 'budget-friendly'], 'description' => 'A rechargeable LED desk lamp with adjustable brightness.', 'detailed_description' => 'Perfect for bookworms and students. This USB-rechargeable LED lamp offers 3 brightness levels, a flexible gooseneck, and 20+ hours of battery life. Compact and portable.'],

            // Outdoor & Adventure
            ['name' => 'Hiking Day Pack', 'category' => 'outdoor-adventure', 'price' => 160.00, 'tags' => ['adventure', 'nature', 'fitness'], 'description' => 'A lightweight 20L hiking backpack with hydration support.', 'detailed_description' => 'A durable, lightweight backpack for day hikes and outdoor adventures. Features a hydration sleeve, breathable mesh back panel, multiple compartments, and rain cover. Perfect for exploring Ghana\'s waterfalls and trails.'],
            ['name' => 'Camping Hammock', 'category' => 'outdoor-adventure', 'price' => 120.00, 'tags' => ['adventure', 'relaxation', 'nature'], 'description' => 'A portable nylon hammock with tree straps for camping and relaxation.', 'detailed_description' => 'Relax anywhere with this lightweight parachute nylon hammock. Supports up to 200kg, packs into a small pouch, and includes adjustable tree straps. Great for camping, beach trips, or just the backyard.'],
            ['name' => 'Stainless Steel Water Bottle', 'category' => 'outdoor-adventure', 'price' => 55.00, 'tags' => ['fitness', 'nature', 'budget-friendly'], 'description' => 'A 750ml insulated water bottle that keeps drinks cold for 24 hours.', 'detailed_description' => 'Stay hydrated on every adventure. Double-wall vacuum insulation keeps drinks cold for 24 hours or hot for 12 hours. BPA-free, leak-proof, and comes in multiple colours. Perfect for hiking, gym, or everyday use.'],

            // Chocolates & Sweets
            ['name' => 'Ghanaian Single-Origin Chocolate Box', 'category' => 'chocolates-sweets', 'price' => 130.00, 'featured' => true, 'tags' => ['luxury', 'romantic'], 'description' => 'A box of 24 premium single-origin Ghanaian chocolate truffles.', 'detailed_description' => 'Handcrafted by artisan chocolatiers using cocoa from the Ashanti region. Includes dark, milk, and white chocolate truffles with fillings like caramel, ginger, and baobab. Elegantly packaged in a wooden box.'],
            ['name' => 'Cocoa Tea & Chocolate Pairing Set', 'category' => 'chocolates-sweets', 'price' => 100.00, 'tags' => ['relaxation', 'luxury'], 'description' => 'Premium cocoa tea paired with chocolate bars for the perfect evening treat.', 'detailed_description' => 'A unique pairing experience: 3 varieties of cocoa husk tea matched with 3 single-origin chocolate bars (70%, 55%, and 40% cocoa). Includes a tasting guide. Made with 100% Ghanaian cocoa.'],
            ['name' => 'Assorted Toffee & Nougat Gift Tin', 'category' => 'chocolates-sweets', 'price' => 70.00, 'tags' => ['birthday', 'budget-friendly'], 'description' => 'A beautiful tin of handmade toffees and nougats.', 'detailed_description' => 'A delightful assortment of butter toffees, coconut nougats, and peanut brittle. Handmade in small batches. Comes in a reusable decorative tin — perfect for birthdays and festive gifting.'],
        ];
    }

    private function generateEmbeddings(): void
    {
        $products = Product::whereDoesntHave('embedding')
            ->where('is_available', true)
            ->get();

        if ($products->isEmpty()) {
            $this->command->info('No products need embeddings.');

            return;
        }

        $this->command->info("Generating embeddings for {$products->count()} products...");

        // Process in batches to respect rate limits
        $products->chunk(5)->each(function ($batch) {
            $texts = $batch->map(function ($product) {
                return "{$product->name}. {$product->description} {$product->detailed_description}";
            })->toArray();

            try {
                $response = Embeddings::for($texts)
                    ->dimensions(768)
                    ->generate(provider: 'gemini');

                foreach ($batch->values() as $i => $product) {
                    DB::table('product_embeddings')->updateOrInsert(
                        ['product_id' => $product->id],
                        [
                            'embedding' => '['.implode(',', $response->embeddings[$i]).']',
                            'content_hash' => md5($texts[$i]),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }

                $this->command->info("  Embedded batch of {$batch->count()} products.");
            } catch (\Throwable $e) {
                Log::error('Embedding generation failed', ['error' => $e->getMessage()]);
                $this->command->error("  Failed: {$e->getMessage()}");
            }

            // Respect rate limits
            usleep(500000); // 0.5 second between batches
        });

        $count = DB::table('product_embeddings')->count();
        $this->command->info("Total embeddings: {$count}");
    }
}
