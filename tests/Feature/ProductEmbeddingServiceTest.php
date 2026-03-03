<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductEmbedding;
use App\Models\Shop;
use App\Models\User;
use App\Services\ProductEmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;
use Tests\TestCase;

class ProductEmbeddingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ProductEmbeddingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ProductEmbeddingService;
    }

    public function test_product_embedding_generated_and_stored(): void
    {
        Embeddings::fake();

        $product = $this->createProduct();

        $this->service->embedProduct($product, force: true);

        $this->assertDatabaseHas('product_embeddings', [
            'product_id' => $product->id,
        ]);

        Embeddings::assertGenerated(fn ($prompt) => $prompt->contains($product->name));
    }

    public function test_unchanged_product_not_re_embedded(): void
    {
        Embeddings::fake();

        $product = $this->createProduct();

        // First embed
        $this->service->embedProduct($product, force: true);

        // Get the content hash
        $embedding = ProductEmbedding::where('product_id', $product->id)->first();
        $originalHash = $embedding->content_hash;

        // Second embed without force — should skip
        $this->service->embedProduct($product, force: false);

        // Hash should be the same (not re-embedded)
        $embedding->refresh();
        $this->assertEquals($originalHash, $embedding->content_hash);
    }

    public function test_build_embedding_text_includes_product_details(): void
    {
        $category = Category::factory()->create(['name' => 'Electronics']);
        $product = $this->createProduct(['category_id' => $category->id]);

        $text = $this->service->buildEmbeddingText($product);

        $this->assertStringContainsString($product->name, $text);
        $this->assertStringContainsString('Category: Electronics', $text);
    }

    public function test_bulk_embedding_processes_available_products(): void
    {
        Embeddings::fake();

        // Create available products
        $this->createProduct(['is_available' => true]);
        $this->createProduct(['is_available' => true]);

        // Create unavailable product — should be skipped
        $this->createProduct(['is_available' => false]);

        $stats = $this->service->embedAllProducts(force: true);

        $this->assertEquals(2, $stats['embedded']);
        $this->assertEquals(0, $stats['skipped']);
    }

    private function createProduct(array $attributes = []): Product
    {
        $vendor = User::factory()->vendor()->create();
        $shop = Shop::factory()->create(['vendor_id' => $vendor->id]);

        return Product::factory()->create(array_merge([
            'vendor_id' => $vendor->id,
            'shop_id' => $shop->id,
            'is_available' => true,
        ], $attributes));
    }
}
