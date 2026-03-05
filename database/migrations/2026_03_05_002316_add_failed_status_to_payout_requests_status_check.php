<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE payout_requests DROP CONSTRAINT IF EXISTS payout_requests_status_check');
        DB::statement("ALTER TABLE payout_requests ADD CONSTRAINT payout_requests_status_check CHECK ((status)::text = ANY ((ARRAY['pending'::character varying, 'processing'::character varying, 'approved'::character varying, 'rejected'::character varying, 'paid'::character varying, 'cancelled'::character varying, 'failed'::character varying])::text[]))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE payout_requests DROP CONSTRAINT IF EXISTS payout_requests_status_check');
        DB::statement("ALTER TABLE payout_requests ADD CONSTRAINT payout_requests_status_check CHECK ((status)::text = ANY ((ARRAY['pending'::character varying, 'processing'::character varying, 'approved'::character varying, 'rejected'::character varying, 'paid'::character varying, 'cancelled'::character varying])::text[]))");
    }
};
