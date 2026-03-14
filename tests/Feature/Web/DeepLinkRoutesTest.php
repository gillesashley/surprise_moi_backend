<?php

namespace Tests\Feature\Web;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeepLinkRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_assetlinks_endpoint_returns_expected_json_contract(): void
    {
        config()->set('deep_links.android.package_name', 'com.surprisemoi.app');
        config()->set('deep_links.android.sha256_cert_fingerprints', [
            'AA:BB:CC:DD:EE:FF',
        ]);

        $response = $this->get('/.well-known/assetlinks.json');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/json');
        $response->assertExactJson([
            [
                'relation' => ['delegate_permission/common.handle_all_urls'],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => 'com.surprisemoi.app',
                    'sha256_cert_fingerprints' => ['AA:BB:CC:DD:EE:FF'],
                ],
            ],
        ]);
    }

    public function test_apple_app_site_association_endpoint_returns_expected_json_contract(): void
    {
        config()->set('deep_links.ios.team_id', 'TEAMID');
        config()->set('deep_links.ios.bundle_id', 'com.surprisemoi.app');

        $response = $this->get('/.well-known/apple-app-site-association');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/json');
        $response->assertExactJson([
            'applinks' => [
                'apps' => [],
                'details' => [
                    [
                        'appIDs' => ['TEAMID.com.surprisemoi.app'],
                        'paths' => ['/products/*'],
                        'components' => [
                            ['/' => '/products/*'],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function test_product_share_page_renders_public_fallback_with_og_tags(): void
    {
        config()->set('deep_links.share_base_url', 'https://surprisemoi.com');
        config()->set('deep_links.android.store_url', 'https://play.google.com/store/apps/details?id=com.surprisemoi.app');
        config()->set('deep_links.ios.store_url', 'https://apps.apple.com/app/id1234567890');

        $product = Product::factory()->create([
            'name' => 'Sharing Test Product',
            'description' => 'A product description used for deep-link fallback rendering.',
            'price' => 120,
            'currency' => 'GHS',
        ]);

        $product->images()->create([
            'image_path' => 'products/images/share-test.jpg',
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        $response = $this->followingRedirects()->get("/products/{$product->id}");

        $response->assertStatus(200);
        $response->assertSee('Sharing Test Product');
        $response->assertSee('A product description used for deep-link fallback rendering.');
        $response->assertSee('Download the App');
        $response->assertSee('property="og:title"', false);
        $response->assertSee('property="og:description"', false);
        $response->assertSee('property="og:image"', false);
        $response->assertSee(
            'property="og:url" content="https://surprisemoi.com/products/'.$product->slug.'"',
            false
        );
    }

    public function test_product_share_page_returns_404_for_unknown_product(): void
    {
        $this->get('/products/999999')->assertNotFound();
    }
}
