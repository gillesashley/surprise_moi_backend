<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_auto_generates_slug_on_creation(): void
    {
        $product = Product::factory()->create(['slug' => null]);

        $this->assertNotNull($product->slug);
        $this->assertSame(16, strlen($product->slug));
    }

    public function test_two_products_get_unique_slugs(): void
    {
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();

        $this->assertNotEquals($productA->slug, $productB->slug);
    }

    public function test_product_listing_includes_slug(): void
    {
        Product::factory()->create();

        $response = $this->getJson('/api/v1/products');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'products' => [
                        '*' => ['id', 'slug', 'name'],
                    ],
                ],
            ]);
    }

    public function test_by_slug_endpoint_returns_correct_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->getJson("/api/v1/products/by-slug/{$product->slug}");

        $response->assertOk()
            ->assertJsonPath('data.product.id', $product->id)
            ->assertJsonPath('data.product.slug', $product->slug);
    }

    public function test_by_slug_endpoint_returns_404_for_nonexistent_slug(): void
    {
        $response = $this->getJson('/api/v1/products/by-slug/nonexistent12345');

        $response->assertNotFound();
    }

    public function test_web_share_page_renders_with_slug(): void
    {
        $product = Product::factory()->create();

        $response = $this->get("/products/{$product->slug}");

        $response->assertOk()
            ->assertViewIs('products.share');
    }

    public function test_legacy_integer_id_redirects_to_slug_url(): void
    {
        $product = Product::factory()->create();

        $response = $this->get("/products/{$product->id}");

        $response->assertRedirect("/products/{$product->slug}");
    }

    public function test_well_known_assetlinks_returns_valid_json(): void
    {
        $response = $this->get('/.well-known/assetlinks.json');

        $response->assertOk()
            ->assertJsonStructure([
                '*' => [
                    'relation',
                    'target' => ['namespace', 'package_name', 'sha256_cert_fingerprints'],
                ],
            ]);
    }

    public function test_well_known_apple_app_site_association_returns_valid_json(): void
    {
        $response = $this->get('/.well-known/apple-app-site-association');

        $response->assertOk()
            ->assertJsonStructure([
                'applinks' => [
                    'apps',
                    'details',
                ],
            ]);
    }
}
