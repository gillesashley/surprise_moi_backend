<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add missing indexes for high-traffic query patterns.
     */
    public function up(): void
    {
        // special_offers: composite index for activeOffer() relationship
        // Query: WHERE product_id = ? AND is_active = true AND starts_at <= now() AND ends_at >= now()
        DB::statement('CREATE INDEX IF NOT EXISTS idx_special_offers_product_active ON special_offers (product_id, is_active, ends_at, starts_at) WHERE is_active = true');

        // targets: partial index for bulk expiry query
        // Query: WHERE status = 'active' AND end_date < now()
        DB::statement("CREATE INDEX IF NOT EXISTS idx_targets_active_end_date ON targets (end_date) WHERE status = 'active'");

        // delivery_requests: composite index for expiry/timeout queries
        DB::statement("CREATE INDEX IF NOT EXISTS idx_delivery_requests_broadcasting_expires ON delivery_requests (expires_at) WHERE status = 'broadcasting'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_special_offers_product_active');
        DB::statement('DROP INDEX IF EXISTS idx_targets_active_end_date');
        DB::statement('DROP INDEX IF EXISTS idx_delivery_requests_broadcasting_expires');
    }
};
