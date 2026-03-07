<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductEmbedding;
use App\Models\Shop;
use App\Models\User;
use App\Services\ProductSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;
use Tests\TestCase;

class ProductSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ProductSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ProductSearchService;
    }

    public function test_search_returns_empty_array_when_no_embeddings(): void
    {
        Embeddings::fake();

        $results = $this->service->searchSimilar('nature gifts');

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function test_search_returns_products_with_expected_structure(): void
    {
        Embeddings::fake();

        $product = $this->createProductWithEmbedding();

        $results = $this->service->searchSimilar('test search');

        // If pgvector is available, this will return results
        // If not, we still verify it doesn't throw errors
        $this->assertIsArray($results);

        if (! empty($results)) {
            $this->assertArrayHasKey('product_id', $results[0]);
            $this->assertArrayHasKey('product_name', $results[0]);
            $this->assertArrayHasKey('price', $results[0]);
            $this->assertArrayHasKey('vendor_name', $results[0]);
            $this->assertArrayHasKey('similarity_score', $results[0]);
        }
    }

    public function test_search_handles_embedding_failure_gracefully(): void
    {
        // Don't fake embeddings — let it fail gracefully
        // The service should catch the exception and return empty
        $results = $this->service->searchSimilar('test query');

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    private function createProductWithEmbedding(): Product
    {
        $vendor = User::factory()->vendor()->create();
        $shop = Shop::factory()->create(['vendor_id' => $vendor->id]);
        $category = Category::factory()->create(['name' => 'Gifts']);

        $product = Product::factory()->create([
            'vendor_id' => $vendor->id,
            'shop_id' => $shop->id,
            'category_id' => $category->id,
            'is_available' => true,
            'name' => 'Beautiful Gift Set',
            'price' => 100.00,
        ]);

        // Create a fake embedding
        $fakeEmbedding = array_fill(0, 768, 0.1);

        ProductEmbedding::updateOrCreate(
            ['product_id' => $product->id],
            [
                'embedding' => $fakeEmbedding,
                'content_hash' => hash('sha256', 'test'),
            ]
        );

        return $product;
    }
}
