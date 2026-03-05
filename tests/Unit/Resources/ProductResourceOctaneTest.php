<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\ProductResource;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductResourceOctaneTest extends TestCase
{
    #[Test]
    public function it_can_flush_the_wishlist_cache(): void
    {
        $reflection = new \ReflectionClass(ProductResource::class);
        $property = $reflection->getProperty('wishlistProductIds');
        $property->setAccessible(true);

        // Seed with fake data (simulating a previous request)
        $property->setValue(null, [1 => [10, 20, 30]]);
        $this->assertNotEmpty($property->getValue(null));

        // Flush
        ProductResource::flushWishlistCache();

        // Verify it's empty
        $this->assertEmpty($property->getValue(null));
    }
}
