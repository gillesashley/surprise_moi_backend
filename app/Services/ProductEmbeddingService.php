<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductEmbedding;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Embeddings;

class ProductEmbeddingService
{
    /**
     * Build the text representation of a product for embedding.
     */
    public function buildEmbeddingText(Product $product): string
    {
        $parts = [
            $product->name,
            $product->description,
        ];

        if ($product->relationLoaded('category') && $product->category) {
            $parts[] = 'Category: '.$product->category->name;
        } elseif ($product->category_id) {
            $product->load('category');
            if ($product->category) {
                $parts[] = 'Category: '.$product->category->name;
            }
        }

        if ($product->relationLoaded('tags') && $product->tags->isNotEmpty()) {
            $parts[] = 'Tags: '.$product->tags->pluck('name')->implode(', ');
        } elseif (! $product->relationLoaded('tags')) {
            $product->load('tags');
            if ($product->tags->isNotEmpty()) {
                $parts[] = 'Tags: '.$product->tags->pluck('name')->implode(', ');
            }
        }

        return implode('. ', array_filter($parts));
    }

    /**
     * Generate and store an embedding for a single product.
     * Skips re-embedding if the content hasn't changed.
     */
    public function embedProduct(Product $product, bool $force = false): void
    {
        $text = $this->buildEmbeddingText($product);
        $contentHash = hash('sha256', $text);

        if (! $force) {
            $existing = ProductEmbedding::where('product_id', $product->id)->first();
            if ($existing && $existing->content_hash === $contentHash) {
                return;
            }
        }

        try {
            $response = Embeddings::for([$text])
                ->dimensions(768)
                ->generate(provider: 'gemini');

            $embedding = $response->embeddings[0];

            ProductEmbedding::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'embedding' => $embedding,
                    'content_hash' => $contentHash,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Failed to embed product', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Embed all available products, skipping unchanged ones.
     *
     * @param  bool  $force  Re-embed all products regardless of changes
     * @return array{embedded: int, skipped: int, failed: int}
     */
    public function embedAllProducts(bool $force = false): array
    {
        $stats = ['embedded' => 0, 'skipped' => 0, 'failed' => 0];

        Product::query()
            ->where('is_available', true)
            ->with(['category', 'tags'])
            ->chunk(50, function ($products) use ($force, &$stats) {
                foreach ($products as $product) {
                    try {
                        $text = $this->buildEmbeddingText($product);
                        $contentHash = hash('sha256', $text);

                        if (! $force) {
                            $existing = ProductEmbedding::where('product_id', $product->id)->first();
                            if ($existing && $existing->content_hash === $contentHash) {
                                $stats['skipped']++;

                                continue;
                            }
                        }

                        $this->embedProduct($product, $force);
                        $stats['embedded']++;
                    } catch (\Throwable $e) {
                        $stats['failed']++;
                        Log::warning('Failed to embed product during bulk operation', [
                            'product_id' => $product->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $stats;
    }
}
