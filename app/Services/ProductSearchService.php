<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Embeddings;

class ProductSearchService
{
    /**
     * Search for products similar to the given query using vector similarity.
     *
     * @param  array<string>  $excludeCategories
     * @return array<int, array<string, mixed>>
     */
    public function searchSimilar(
        string $query,
        ?float $minPrice = null,
        ?float $maxPrice = null,
        array $excludeCategories = [],
        int $limit = 10
    ): array {
        try {
            $response = Embeddings::for([$query])
                ->dimensions(768)
                ->generate(provider: 'gemini');

            $queryEmbedding = $response->embeddings[0];
        } catch (\Throwable $e) {
            Log::error('Failed to generate query embedding', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $embeddingStr = '['.implode(',', $queryEmbedding).']';

        $sql = '
            SELECT
                p.id as product_id,
                p.name as product_name,
                p.description,
                p.price,
                p.discount_price,
                p.thumbnail,
                p.currency,
                c.name as category_name,
                s.name as vendor_name,
                (pe.embedding <=> :embedding) as distance
            FROM product_embeddings pe
            JOIN products p ON p.id = pe.product_id
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN shops s ON s.id = p.shop_id
            WHERE p.is_available = true
            AND p.deleted_at IS NULL
        ';

        $bindings = ['embedding' => $embeddingStr];

        if ($minPrice !== null) {
            $sql .= ' AND p.price >= :min_price';
            $bindings['min_price'] = $minPrice;
        }

        if ($maxPrice !== null) {
            $sql .= ' AND p.price <= :max_price';
            $bindings['max_price'] = $maxPrice;
        }

        if (! empty($excludeCategories)) {
            $placeholders = [];
            foreach ($excludeCategories as $i => $category) {
                $key = "exclude_cat_{$i}";
                $placeholders[] = ":{$key}";
                $bindings[$key] = $category;
            }
            $sql .= ' AND (c.name IS NULL OR c.name NOT IN ('.implode(',', $placeholders).'))';
        }

        $sql .= ' ORDER BY distance ASC LIMIT :limit';
        $bindings['limit'] = $limit;

        $results = DB::select($sql, $bindings);

        return array_map(function ($row) {
            return [
                'product_id' => $row->product_id,
                'product_name' => $row->product_name,
                'description' => $row->description,
                'price' => (float) ($row->discount_price ?? $row->price),
                'original_price' => (float) $row->price,
                'thumbnail' => $row->thumbnail,
                'currency' => $row->currency ?? 'GHS',
                'category' => $row->category_name,
                'vendor_name' => $row->vendor_name,
                'similarity_score' => round(1 - $row->distance, 4),
            ];
        }, $results);
    }
}
