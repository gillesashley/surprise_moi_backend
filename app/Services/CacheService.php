<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Centralized cache key management and invalidation for API endpoints.
 *
 * TTLs are tuned per-entity based on how frequently data changes.
 */
class CacheService
{
    /** @var int Cache TTL in seconds */
    public const TTL_CATEGORIES = 1800;      // 30 min — rarely change

    public const TTL_FILTERS = 1800;          // 30 min — derived from products/categories

    public const TTL_ADVERTISEMENTS = 300;    // 5 min — active set changes infrequently

    public const TTL_SPECIAL_OFFERS = 300;    // 5 min

    public const TTL_LOCATIONS = 1800;        // 30 min — shops don't move often

    public const TTL_PRICE_RANGE = 600;       // 10 min

    /**
     * Flush all caches related to products (filters, categories, price range, colors, occasions).
     */
    public static function flushProductCaches(): void
    {
        Cache::forget('filters:available_colors');
        Cache::forget('filters:categories');
        Cache::forget('filters:occasions');
        Cache::forget('filters:price_range');
        Cache::forget('filters:all');
    }

    /**
     * Flush all caches related to categories.
     */
    public static function flushCategoryCaches(): void
    {
        Cache::forget('filters:categories');
        Cache::forget('filters:all');
    }

    /**
     * Flush all caches related to advertisements.
     */
    public static function flushAdvertisementCaches(): void
    {
        $placements = ['', 'home', 'banner', 'sidebar', 'popup', 'featured'];
        foreach ($placements as $placement) {
            Cache::forget("advertisements:list:{$placement}");
        }
    }

    /**
     * Flush all caches related to special offers.
     */
    public static function flushSpecialOfferCaches(): void
    {
        // Page-based keys — flush first 10 pages (covers realistic usage)
        for ($page = 1; $page <= 10; $page++) {
            Cache::forget("special_offers:page:{$page}");
        }
    }

    /**
     * Flush all caches related to shops/locations.
     */
    public static function flushShopCaches(): void
    {
        Cache::forget('filters:locations');
    }
}
