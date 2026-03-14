<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeepLinkingTest extends TestCase
{
    use RefreshDatabase;

    public function test_slug_is_generated_from_product_name(): void
    {
        $product = Product::factory()->create(['name' => 'Chocolate Gift Box']);

        $this->assertSame('chocolate-gift-box', $product->slug);
    }

    public function test_duplicate_names_get_unique_slugs(): void
    {
        $first = Product::factory()->create(['name' => 'Birthday Cake']);
        $second = Product::factory()->create(['name' => 'Birthday Cake']);

        $this->assertSame('birthday-cake', $first->slug);
        $this->assertSame('birthday-cake-2', $second->slug);
    }

    public function test_slug_is_immutable_on_update(): void
    {
        $product = Product::factory()->create(['name' => 'Original Name']);
        $originalSlug = $product->slug;

        $product->slug = 'changed-slug';
        $product->save();
        $product->refresh();

        $this->assertSame($originalSlug, $product->slug);
    }

    public function test_slug_is_not_mass_assignable(): void
    {
        $product = Product::factory()->create(['name' => 'Test Product']);
        $originalSlug = $product->slug;

        $product->update(['slug' => 'hacked-slug', 'name' => 'Updated Name']);
        $product->refresh();

        $this->assertSame($originalSlug, $product->slug);
    }

    public function test_by_slug_api_is_case_insensitive(): void
    {
        $product = Product::factory()->create(['name' => 'Premium Watch']);

        $response = $this->getJson('/api/v1/products/by-slug/PREMIUM-WATCH');

        $response->assertOk()
            ->assertJsonPath('data.product.id', $product->id);
    }

    public function test_by_slug_api_returns_404_for_nonexistent_slug(): void
    {
        $response = $this->getJson('/api/v1/products/by-slug/does-not-exist');

        $response->assertNotFound();
    }

    public function test_share_page_renders_with_name_based_slug(): void
    {
        $product = Product::factory()->create(['name' => 'Flower Bouquet']);

        $response = $this->get('/products/flower-bouquet');

        $response->assertOk()
            ->assertViewIs('products.share');
    }

    public function test_share_page_contains_redirect_script(): void
    {
        $product = Product::factory()->create(['name' => 'Gift Hamper']);

        $response = $this->get('/products/gift-hamper');

        $response->assertOk()
            ->assertSee('window.location.replace', false);
    }

    public function test_share_page_contains_og_meta_tags(): void
    {
        $product = Product::factory()->create(['name' => 'Luxury Perfume']);

        $response = $this->get('/products/luxury-perfume');

        $response->assertOk()
            ->assertSee('og:title', false)
            ->assertSee('og:description', false)
            ->assertSee('og:image', false)
            ->assertSee('og:url', false);
    }

    public function test_asset_links_returns_correct_structure(): void
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

    public function test_apple_app_site_association_returns_correct_structure(): void
    {
        $response = $this->get('/.well-known/apple-app-site-association');

        $response->assertOk()
            ->assertJsonStructure([
                'applinks' => [
                    'apps',
                    'details' => [
                        '*' => ['appIDs', 'paths', 'components'],
                    ],
                ],
            ]);
    }

    public function test_legacy_id_redirects_to_slug_url(): void
    {
        $product = Product::factory()->create(['name' => 'Rose Gold Ring']);

        $response = $this->get("/products/{$product->id}");

        $response->assertRedirect("/products/{$product->slug}");
    }
}
