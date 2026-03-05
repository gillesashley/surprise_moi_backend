<?php

namespace App\Listeners;

use App\Http\Resources\ProductResource;
use App\Http\Resources\ServiceResource;
use Laravel\Octane\Events\RequestReceived;

class FlushWishlistCaches
{
    /**
     * Flush static wishlist caches to prevent memory leaks and stale data
     * across Octane requests.
     */
    public function handle(RequestReceived $event): void
    {
        ProductResource::flushWishlistCache();
        ServiceResource::flushWishlistCache();
    }
}
