<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add trigram indexes for services and users tables to support ILIKE search.
     *
     * pg_trgm extension is already enabled by the previous performance migration.
     */
    public function up(): void
    {
        // Services: trigram indexes for ILIKE text search on name and description
        DB::statement('CREATE INDEX IF NOT EXISTS idx_services_name_trgm ON services USING gin (name gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_services_description_trgm ON services USING gin (description gin_trgm_ops)');

        // Users: trigram indexes for vendor name and bio search
        DB::statement('CREATE INDEX IF NOT EXISTS idx_users_name_trgm ON users USING gin (name gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_users_bio_trgm ON users USING gin (bio gin_trgm_ops) WHERE bio IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_services_name_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_services_description_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_users_name_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_users_bio_trgm');
    }
};
