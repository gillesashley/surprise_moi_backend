<?php

namespace App\Ai\Tools;

use App\Services\ProductSearchService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchProducts implements Tool
{
    public function description(): Stringable|string
    {
        return 'Search the Surprise Moi product catalog for gifts matching keywords. '
             .'Supports filtering by price range and excluding categories based on dislikes.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'keywords' => $schema->string()
                ->required()
                ->description('Optimized search keywords for gift matching, e.g. "nature inspired calm relaxation gifts"'),
            'max_price' => $schema->number()
                ->description('Maximum price in GHS'),
            'min_price' => $schema->number()
                ->description('Minimum price in GHS'),
            'exclude_categories' => $schema->array()
                ->items($schema->string())
                ->description('Category names to exclude based on dislikes'),
            'limit' => $schema->integer()
                ->description('Number of results to return, defaults to 10'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        /** @var ProductSearchService $service */
        $service = app(ProductSearchService::class);

        $results = $service->searchSimilar(
            query: $request->string('keywords')->toString(),
            minPrice: $request['min_price'] ?? null,
            maxPrice: $request['max_price'] ?? null,
            excludeCategories: $request['exclude_categories'] ?? [],
            limit: $request->integer('limit', 10),
        );

        if (empty($results)) {
            return json_encode([
                'results' => [],
                'message' => 'No products found matching the search criteria.',
            ]);
        }

        return json_encode([
            'results' => $results,
            'count' => count($results),
        ]);
    }
}
