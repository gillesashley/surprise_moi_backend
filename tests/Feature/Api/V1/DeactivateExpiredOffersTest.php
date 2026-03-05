<?php

namespace Tests\Feature\Api\V1;

use App\Models\Product;
use App\Models\Shop;
use App\Models\SpecialOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeactivateExpiredOffersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $vendor = User::factory()->vendor()->create();
        Shop::factory()->create(['vendor_id' => $vendor->id]);
    }

    public function test_deactivates_expired_offers(): void
    {
        $product = Product::factory()->create();
        $offer = SpecialOffer::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'starts_at' => now()->subDays(7),
            'ends_at' => now()->subHour(),
        ]);

        $this->artisan('special-offers:deactivate-expired')
            ->assertSuccessful();

        $offer->refresh();
        $this->assertFalse($offer->is_active);
    }

    public function test_does_not_deactivate_non_expired_offers(): void
    {
        $product = Product::factory()->create();
        $offer = SpecialOffer::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'ends_at' => now()->addDays(3),
        ]);

        $this->artisan('special-offers:deactivate-expired')
            ->assertSuccessful();

        $offer->refresh();
        $this->assertTrue($offer->is_active);
    }

    public function test_does_not_touch_already_inactive_offers(): void
    {
        $product = Product::factory()->create();
        $offer = SpecialOffer::factory()->create([
            'product_id' => $product->id,
            'is_active' => false,
            'starts_at' => now()->subDays(14),
            'ends_at' => now()->subDays(7),
        ]);

        $this->artisan('special-offers:deactivate-expired')
            ->assertSuccessful();

        $offer->refresh();
        $this->assertFalse($offer->is_active);
    }
}
