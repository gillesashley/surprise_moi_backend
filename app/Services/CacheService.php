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

    public const TTL_TREASURY = 600;         // 10 min — Paystack data refresh interval

    /** @var array<string> Registry of active treasury cache keys */
    private static array $treasuryCacheKeys = [];

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

    /**
     * Register a treasury cache key so it can be flushed later.
     */
    public static function registerTreasuryCacheKey(string $key): void
    {
        static::$treasuryCacheKeys[] = $key;

        $registered = Cache::get('treasury:registered_keys', []);
        $registered[] = $key;
        Cache::put('treasury:registered_keys', array_unique($registered), self::TTL_TREASURY * 2);
    }

    /**
     * Flush all caches related to treasury Paystack data.
     */
    public static function flushTreasuryCaches(): void
    {
        $keys = array_unique(array_merge(
            static::$treasuryCacheKeys,
            Cache::get('treasury:registered_keys', [])
        ));

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        Cache::forget('treasury:balance');
        Cache::forget('treasury:balance_ledger');
        Cache::forget('treasury:transaction_totals');
        Cache::forget('treasury:registered_keys');

        static::$treasuryCacheKeys = [];
    }

    /**
     * Flush treasury balance and transfer caches (after a transfer).
     */
    public static function flushTreasuryTransferCaches(): void
    {
        $keys = Cache::get('treasury:registered_keys', []);

        Cache::forget('treasury:balance');
        Cache::forget('treasury:balance_ledger');
        Cache::forget('treasury:transaction_totals');

        foreach ($keys as $key) {
            if (str_starts_with($key, 'treasury:transfers:')) {
                Cache::forget($key);
            }
        }
    }
}
