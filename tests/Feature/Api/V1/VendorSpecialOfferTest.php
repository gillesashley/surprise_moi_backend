<?php

namespace Tests\Feature\Api\V1;

use App\Models\Product;
use App\Models\Shop;
use App\Models\SpecialOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorSpecialOfferTest extends TestCase
{
    use RefreshDatabase;

    protected User $vendor;
    protected Shop $shop;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vendor = User::factory()->vendor()->create();
        $this->shop = Shop::factory()->create(['vendor_id' => $this->vendor->id]);
        $this->product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'vendor_id' => $this->vendor->id,
            'price' => 200.00,
        ]);
    }

    public function test_vendor_can_list_own_offers(): void
    {
        SpecialOffer::factory()->create(['product_id' => $this->product->id]);
        $expiredProduct = Product::factory()->create(['shop_id' => $this->shop->id, 'vendor_id' => $this->vendor->id]);
        SpecialOffer::factory()->expired()->create(['product_id' => $expiredProduct->id]);

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/special-offers');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_vendor_cannot_see_other_vendors_offers(): void
    {
        $otherVendor = User::factory()->vendor()->create();
        $otherShop = Shop::factory()->create(['vendor_id' => $otherVendor->id]);
        $otherProduct = Product::factory()->create(['shop_id' => $otherShop->id, 'vendor_id' => $otherVendor->id]);
        SpecialOffer::factory()->create(['product_id' => $otherProduct->id]);

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/special-offers');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_vendor_can_create_offer(): void
    {
        $response = $this->actingAs($this->vendor)
            ->postJson('/api/v1/vendor/special-offers', [
                'product_id' => $this->product->id,
                'discount_percentage' => 25,
                'tag' => SpecialOffer::TAG_FLASH_SALE,
                'starts_at' => now()->toDateTimeString(),
                'ends_at' => now()->addDays(7)->toDateTimeString(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.discount_percentage', 25)
            ->assertJsonPath('data.tag', SpecialOffer::TAG_FLASH_SALE);

        $this->assertDatabaseHas('special_offers', [
            'product_id' => $this->product->id,
            'discount_percentage' => 25,
        ]);
    }

    public function test_rejects_offer_on_another_vendors_product(): void
    {
        $otherVendor = User::factory()->vendor()->create();
        $otherShop = Shop::factory()->create(['vendor_id' => $otherVendor->id]);
        $otherProduct = Product::factory()->create(['shop_id' => $otherShop->id, 'vendor_id' => $otherVendor->id]);

        $response = $this->actingAs($this->vendor)
            ->postJson('/api/v1/vendor/special-offers', [
                'product_id' => $otherProduct->id,
                'discount_percentage' => 10,
                'tag' => SpecialOffer::TAG_SPECIAL_OFFERS,
                'starts_at' => now()->toDateTimeString(),
                'ends_at' => now()->addDays(7)->toDateTimeString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);
    }

    public function test_rejects_offer_when_product_has_active_offer(): void
    {
        SpecialOffer::factory()->create(['product_id' => $this->product->id]);

        $response = $this->actingAs($this->vendor)
            ->postJson('/api/v1/vendor/special-offers', [
                'product_id' => $this->product->id,
                'discount_percentage' => 15,
                'tag' => SpecialOffer::TAG_LIMITED_TIME,
                'starts_at' => now()->toDateTimeString(),
                'ends_at' => now()->addDays(3)->toDateTimeString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);
    }

    public function test_validates_discount_percentage_bounds(): void
    {
        $response = $this->actingAs($this->vendor)
            ->postJson('/api/v1/vendor/special-offers', [
                'product_id' => $this->product->id,
                'discount_percentage' => 0,
                'tag' => SpecialOffer::TAG_FLASH_SALE,
                'starts_at' => now()->toDateTimeString(),
                'ends_at' => now()->addDays(7)->toDateTimeString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['discount_percentage']);

        $response = $this->actingAs($this->vendor)
            ->postJson('/api/v1/vendor/special-offers', [
                'product_id' => $this->product->id,
                'discount_percentage' => 100,
                'tag' => SpecialOffer::TAG_FLASH_SALE,
                'starts_at' => now()->toDateTimeString(),
                'ends_at' => now()->addDays(7)->toDateTimeString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['discount_percentage']);
    }

    public function test_validates_tag_values(): void
    {
        $response = $this->actingAs($this->vendor)
            ->postJson('/api/v1/vendor/special-offers', [
                'product_id' => $this->product->id,
                'discount_percentage' => 10,
                'tag' => 'Invalid Tag',
                'starts_at' => now()->toDateTimeString(),
                'ends_at' => now()->addDays(7)->toDateTimeString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tag']);
    }

    public function test_validates_ends_at_after_starts_at(): void
    {
        $response = $this->actingAs($this->vendor)
            ->postJson('/api/v1/vendor/special-offers', [
                'product_id' => $this->product->id,
                'discount_percentage' => 10,
                'tag' => SpecialOffer::TAG_FLASH_SALE,
                'starts_at' => now()->addDays(5)->toDateTimeString(),
                'ends_at' => now()->addDays(2)->toDateTimeString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ends_at']);
    }

    public function test_vendor_can_update_own_offer(): void
    {
        $offer = SpecialOffer::factory()->create(['product_id' => $this->product->id]);

        $response = $this->actingAs($this->vendor)
            ->putJson("/api/v1/vendor/special-offers/{$offer->id}", [
                'discount_percentage' => 30,
                'tag' => SpecialOffer::TAG_FESTIVAL_OFFERS,
                'ends_at' => now()->addDays(14)->toDateTimeString(),
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.discount_percentage', 30)
            ->assertJsonPath('data.tag', SpecialOffer::TAG_FESTIVAL_OFFERS);
    }

    public function test_vendor_cannot_update_other_vendors_offer(): void
    {
        $otherVendor = User::factory()->vendor()->create();
        $otherShop = Shop::factory()->create(['vendor_id' => $otherVendor->id]);
        $otherProduct = Product::factory()->create(['shop_id' => $otherShop->id, 'vendor_id' => $otherVendor->id]);
        $otherOffer = SpecialOffer::factory()->create(['product_id' => $otherProduct->id]);

        $response = $this->actingAs($this->vendor)
            ->putJson("/api/v1/vendor/special-offers/{$otherOffer->id}", [
                'discount_percentage' => 50,
            ]);

        $response->assertStatus(403);
    }

    public function test_vendor_can_delete_own_offer(): void
    {
        $offer = SpecialOffer::factory()->create(['product_id' => $this->product->id]);

        $response = $this->actingAs($this->vendor)
            ->deleteJson("/api/v1/vendor/special-offers/{$offer->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('special_offers', ['id' => $offer->id]);
    }

    public function test_vendor_cannot_delete_other_vendors_offer(): void
    {
        $otherVendor = User::factory()->vendor()->create();
        $otherShop = Shop::factory()->create(['vendor_id' => $otherVendor->id]);
        $otherProduct = Product::factory()->create(['shop_id' => $otherShop->id, 'vendor_id' => $otherVendor->id]);
        $otherOffer = SpecialOffer::factory()->create(['product_id' => $otherProduct->id]);

        $response = $this->actingAs($this->vendor)
            ->deleteJson("/api/v1/vendor/special-offers/{$otherOffer->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('special_offers', ['id' => $otherOffer->id]);
    }

    public function test_unauthenticated_user_cannot_access_vendor_endpoints(): void
    {
        $this->getJson('/api/v1/vendor/special-offers')->assertStatus(401);
        $this->postJson('/api/v1/vendor/special-offers')->assertStatus(401);
    }
}
