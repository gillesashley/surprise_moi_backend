<?php

namespace App\Observers;

use App\Jobs\EmbedProduct;
use App\Models\Product;
use App\Services\CacheService;

class ProductObserver
{
    public function created(Product $product): void
    {
        if ($product->is_available) {
            EmbedProduct::dispatch($product);
        }

        CacheService::flushProductCaches();
    }

    public function updated(Product $product): void
    {
        if ($product->wasChanged(['name', 'description', 'detailed_description', 'category_id'])) {
            EmbedProduct::dispatch($product);
        }

        CacheService::flushProductCaches();
    }

    public function deleted(Product $product): void
    {
        CacheService::flushProductCaches();
    }
}
