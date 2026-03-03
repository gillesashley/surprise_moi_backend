<?php

namespace App\Observers;

use App\Jobs\EmbedProduct;
use App\Models\Product;

class ProductObserver
{
    public function created(Product $product): void
    {
        if ($product->is_available) {
            EmbedProduct::dispatch($product);
        }
    }

    public function updated(Product $product): void
    {
        if ($product->wasChanged(['name', 'description', 'detailed_description', 'category_id'])) {
            EmbedProduct::dispatch($product);
        }
    }
}
