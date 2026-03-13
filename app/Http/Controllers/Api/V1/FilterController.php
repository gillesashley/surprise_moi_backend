<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Tag;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class FilterController extends Controller
{
    /**
     * Get all available filter options for products.
     *
     * Returns categories, price range, rating options, available colors, and occasions/tags.
     */
    public function index(): JsonResponse
    {
        $data = Cache::remember('filters:all', CacheService::TTL_FILTERS, function () {
            return [
                'categories' => $this->getCategories(),
                'price_range' => $this->getPriceRange(),
                'rating_options' => $this->getRatingOptions(),
                'colors' => $this->getAvailableColors(),
                'occasions' => $this->getOccasions(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get all active categories with product count.
     */
    public function categories(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $this->getCategories(),
            ],
        ]);
    }

    /**
     * Get price range from available products.
     */
    public function priceRange(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'price_range' => $this->getPriceRange(),
            ],
        ]);
    }

    /**
     * Get available colors from products.
     */
    public function colors(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'colors' => $this->getAvailableColors(),
            ],
        ]);
    }

    /**
     * Get all occasions/tags.
     */
    public function occasions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'occasions' => $this->getOccasions(),
            ],
        ]);
    }

    /**
     * Get unique vendor/shop locations.
     */
    public function locations(): JsonResponse
    {
        $locations = Cache::remember('filters:locations', CacheService::TTL_LOCATIONS, function () {
            return Shop::query()
                ->where('is_active', true)
                ->whereNotNull('location')
                ->where('location', '!=', '')
                ->distinct()
                ->orderBy('location')
                ->pluck('location')
                ->map(fn (string $name) => ['name' => $name])
                ->values()
                ->toArray();
        });

        return response()->json([
            'success' => true,
            'data' => [
                'locations' => $locations,
            ],
        ]);
    }

    /**
     * Get predefined rating filter options.
     */
    public function ratings(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'rating_options' => $this->getRatingOptions(),
            ],
        ]);
    }

    /**
     * Get categories with product count.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getCategories(): array
    {
        return Cache::remember('filters:categories', CacheService::TTL_CATEGORIES, function () {
            $categories = Category::query()
                ->where('is_active', true)
                ->withCount(['products' => function ($query) {
                    $query->where('is_available', true);
                }])
                ->orderBy('sort_order')
                ->get();

            return $categories->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'type' => $category->type,
                'icon' => $category->icon ? url($category->icon) : null,
                'image' => $category->image ? url($category->image) : null,
                'products_count' => $category->products_count,
            ])->toArray();
        });
    }

    /**
     * Get min and max price from available products.
     *
     * @return array{min: float, max: float, currency: string}
     */
    private function getPriceRange(): array
    {
        return Cache::remember('filters:price_range', CacheService::TTL_PRICE_RANGE, function () {
            $priceData = Product::query()
                ->where('is_available', true)
                ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
                ->first();

            return [
                'min' => (float) ($priceData->min_price ?? 0),
                'max' => (float) ($priceData->max_price ?? 0),
                'currency' => 'GHS',
            ];
        });
    }

    /**
     * Get predefined rating filter options matching the mobile app design.
     *
     * @return array<int, array{label: string, min: float, max: float|null}>
     */
    private function getRatingOptions(): array
    {
        return [
            [
                'label' => '4.5 and above',
                'min' => 4.5,
                'max' => null,
            ],
            [
                'label' => '4.0 - 4.5',
                'min' => 4.0,
                'max' => 4.5,
            ],
            [
                'label' => '3.5 - 4.0',
                'min' => 3.5,
                'max' => 4.0,
            ],
            [
                'label' => '3.0 - 3.5',
                'min' => 3.0,
                'max' => 3.5,
            ],
            [
                'label' => '2.5 - 3.0',
                'min' => 2.5,
                'max' => 3.0,
            ],
        ];
    }

    /**
     * Get all unique colors from available products.
     *
     * @return array<int, array{name: string, hex: string|null}>
     */
    private function getAvailableColors(): array
    {
        return Cache::remember('filters:available_colors', now()->addMinutes(10), function () {
            $products = Product::query()
                ->where('is_available', true)
                ->whereNotNull('colors')
                ->pluck('colors');

            $allColors = collect();

            foreach ($products as $colors) {
                if (is_array($colors)) {
                    $allColors = $allColors->merge($colors);
                }
            }

            $uniqueColors = $allColors->unique()->filter()->values();

            $colorHexMap = [
                'red' => '#FF0000',
                'pink' => '#FFC0CB',
                'purple' => '#800080',
                'white' => '#FFFFFF',
                'black' => '#000000',
                'blue' => '#0000FF',
                'green' => '#008000',
                'yellow' => '#FFFF00',
                'orange' => '#FFA500',
                'brown' => '#A52A2A',
                'gold' => '#FFD700',
                'silver' => '#C0C0C0',
                'rose' => '#FF007F',
                'lavender' => '#E6E6FA',
                'cream' => '#FFFDD0',
                'peach' => '#FFCBA4',
                'coral' => '#FF7F50',
                'teal' => '#008080',
                'navy' => '#000080',
                'maroon' => '#800000',
                'burgundy' => '#800020',
                'magenta' => '#FF00FF',
                'turquoise' => '#40E0D0',
                'beige' => '#F5F5DC',
            ];

            return $uniqueColors->map(function ($color) use ($colorHexMap) {
                $colorLower = strtolower(trim($color));

                return [
                    'name' => ucfirst($colorLower),
                    'hex' => $colorHexMap[$colorLower] ?? null,
                ];
            })->sortBy('name')->values()->toArray();
        });
    }

    /**
     * Get all tags/occasions with product count.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getOccasions(): array
    {
        return Cache::remember('filters:occasions', CacheService::TTL_FILTERS, function () {
            $tags = Tag::query()
                ->withCount(['products' => function ($query) {
                    $query->where('is_available', true);
                }])
                ->orderBy('name')
                ->get();

            return $tags->map(fn (Tag $tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'products_count' => $tag->products_count,
            ])->toArray();
        });
    }
}
