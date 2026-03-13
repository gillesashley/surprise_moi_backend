<?php

namespace App\Observers;

use App\Models\Shop;
use App\Services\CacheService;

class ShopObserver
{
    public function created(Shop $shop): void
    {
        CacheService::flushShopCaches();
    }

    public function updated(Shop $shop): void
    {
        if ($shop->wasChanged(['location', 'is_active'])) {
            CacheService::flushShopCaches();
        }
    }

    public function deleted(Shop $shop): void
    {
        CacheService::flushShopCaches();
    }
}
