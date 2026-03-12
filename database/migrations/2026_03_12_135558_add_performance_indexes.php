<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add performance indexes for high-traffic API endpoints.
     *
     * Uses raw SQL for PostgreSQL-specific index types (GIN trigram)
     * that Laravel's schema builder doesn't support natively.
     */
    public function up(): void
    {
        // Enable pg_trgm extension for trigram-based text search indexes
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        // Products: trigram indexes for ILIKE text search on name and description
        DB::statement('CREATE INDEX IF NOT EXISTS idx_products_name_trgm ON products USING gin (name gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_products_description_trgm ON products USING gin (description gin_trgm_ops)');

        // Products: partial index for available products filtered by price
        DB::statement('CREATE INDEX IF NOT EXISTS idx_products_available_price ON products (price) WHERE is_available = true');

        // Products: partial index for available products with discount
        DB::statement('CREATE INDEX IF NOT EXISTS idx_products_available_discount ON products (discount_price) WHERE is_available = true AND discount_price IS NOT NULL AND discount_price > 0');

        // Products: index for free delivery filter
        DB::statement('CREATE INDEX IF NOT EXISTS idx_products_free_delivery ON products (free_delivery) WHERE is_available = true AND free_delivery = true');

        // Shops: trigram indexes for ILIKE text search
        DB::statement('CREATE INDEX IF NOT EXISTS idx_shops_name_trgm ON shops USING gin (name gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_shops_location_trgm ON shops USING gin (location gin_trgm_ops)');

        // Shops: partial index for active shops
        DB::statement('CREATE INDEX IF NOT EXISTS idx_shops_active ON shops (is_active) WHERE is_active = true');

        // Orders: composite index for user listing with status and date ordering
        DB::statement('CREATE INDEX IF NOT EXISTS idx_orders_user_status_created ON orders (user_id, status, created_at DESC)');

        // Orders: vendor listing index
        DB::statement('CREATE INDEX IF NOT EXISTS idx_orders_vendor_status_created ON orders (vendor_id, status, created_at DESC)');

        // Advertisements: composite partial index for active ad lookups
        DB::statement('CREATE INDEX IF NOT EXISTS idx_advertisements_active_placement ON advertisements (placement, display_order) WHERE status = \'active\'');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_products_name_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_products_description_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_products_available_price');
        DB::statement('DROP INDEX IF EXISTS idx_products_available_discount');
        DB::statement('DROP INDEX IF EXISTS idx_products_free_delivery');
        DB::statement('DROP INDEX IF EXISTS idx_shops_name_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_shops_location_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_shops_active');
        DB::statement('DROP INDEX IF EXISTS idx_orders_user_status_created');
        DB::statement('DROP INDEX IF EXISTS idx_orders_vendor_status_created');
        DB::statement('DROP INDEX IF EXISTS idx_advertisements_active_placement');
    }
};
