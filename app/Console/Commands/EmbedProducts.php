<?php

namespace App\Console\Commands;

use App\Jobs\EmbedAllProducts;
use App\Services\ProductEmbeddingService;
use Illuminate\Console\Command;

class EmbedProducts extends Command
{
    protected $signature = 'products:embed
        {--fresh : Re-embed all products regardless of changes}
        {--sync : Process synchronously instead of dispatching to queue}';

    protected $description = 'Generate embeddings for all available products';

    public function handle(ProductEmbeddingService $service): int
    {
        $fresh = $this->option('fresh');

        if ($this->option('sync')) {
            $this->info('Embedding products synchronously...');

            $stats = $service->embedAllProducts($fresh);

            $this->info("Done! Embedded: {$stats['embedded']}, Skipped: {$stats['skipped']}, Failed: {$stats['failed']}");

            return self::SUCCESS;
        }

        EmbedAllProducts::dispatch($fresh);
        $this->info('Product embedding job dispatched to queue.');

        return self::SUCCESS;
    }
}
