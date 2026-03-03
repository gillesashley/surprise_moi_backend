<?php

namespace App\Jobs;

use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EmbedAllProducts implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(
        public bool $force = false
    ) {}

    public function handle(): void
    {
        Product::query()
            ->where('is_available', true)
            ->chunk(50, function ($products) {
                foreach ($products as $product) {
                    EmbedProduct::dispatch($product);
                }
            });
    }
}
