<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\ServiceResource;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServiceResourceOctaneTest extends TestCase
{
    #[Test]
    public function it_can_flush_the_wishlist_cache(): void
    {
        $reflection = new \ReflectionClass(ServiceResource::class);
        $property = $reflection->getProperty('wishlistServiceIds');
        $property->setAccessible(true);

        $property->setValue(null, [1 => [10, 20, 30]]);
        $this->assertNotEmpty($property->getValue(null));

        ServiceResource::flushWishlistCache();

        $this->assertEmpty($property->getValue(null));
    }
}
