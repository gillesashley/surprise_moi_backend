<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\ProductEmbeddingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EmbedProduct implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public Product $product
    ) {}

    public function handle(ProductEmbeddingService $service): void
    {
        $service->embedProduct($this->product);
    }
}
