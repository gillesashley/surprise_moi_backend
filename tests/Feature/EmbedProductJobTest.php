<?php

namespace Tests\Feature;

use App\Jobs\EmbedAllProducts;
use App\Jobs\EmbedProduct;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Embeddings;
use Tests\TestCase;

class EmbedProductJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_embed_product_job_dispatched_on_product_creation(): void
    {
        Queue::fake([EmbedProduct::class]);

        $product = $this->createProduct();

        Queue::assertPushed(EmbedProduct::class, function ($job) use ($product) {
            return $job->product->id === $product->id;
        });
    }

    public function test_embed_product_job_dispatched_on_product_name_change(): void
    {
        Queue::fake([EmbedProduct::class]);

        $product = $this->createProduct();

        Queue::assertPushed(EmbedProduct::class);

        // Clear the queue
        Queue::fake([EmbedProduct::class]);

        $product->update(['name' => 'Updated Product Name']);

        Queue::assertPushed(EmbedProduct::class);
    }

    public function test_embed_product_job_not_dispatched_on_irrelevant_change(): void
    {
        Queue::fake([EmbedProduct::class]);

        $product = $this->createProduct();

        // Clear the queue from creation dispatch
        Queue::fake([EmbedProduct::class]);

        $product->update(['stock' => 50]);

        Queue::assertNotPushed(EmbedProduct::class);
    }

    public function test_embed_product_job_embeds_product(): void
    {
        Embeddings::fake();

        $product = $this->createProduct();

        $job = new EmbedProduct($product);
        $job->handle(app(\App\Services\ProductEmbeddingService::class));

        $this->assertDatabaseHas('product_embeddings', [
            'product_id' => $product->id,
        ]);
    }

    public function test_embed_all_products_dispatches_individual_jobs(): void
    {
        Queue::fake([EmbedProduct::class]);

        $this->createProduct(['is_available' => true]);
        $this->createProduct(['is_available' => true]);
        $this->createProduct(['is_available' => false]);

        // Reset queue from product creation observer dispatches
        Queue::fake([EmbedProduct::class]);

        $job = new EmbedAllProducts;
        $job->handle();

        // Should dispatch for available products only
        Queue::assertPushed(EmbedProduct::class, 2);
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
