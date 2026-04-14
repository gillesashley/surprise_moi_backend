<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payout_requests', function (Blueprint $table) {
            $table->enum('source', ['vendor_earnings', 'referral_milestone'])
                ->default('vendor_earnings')
                ->after('user_role');
            $table->unsignedInteger('referral_milestone_threshold')->nullable()->after('source');
            $table->unsignedInteger('points_deducted')->nullable()->after('referral_milestone_threshold');
            $table->foreignId('user_payout_detail_id')->nullable()->after('points_deducted')
                ->constrained('user_payout_details')->nullOnDelete();

            $table->index(['source', 'status']);
        });

        // Extend user_role check constraint to include 'customer' (PostgreSQL-safe pattern).
        DB::statement('ALTER TABLE payout_requests DROP CONSTRAINT IF EXISTS payout_requests_user_role_check');
        DB::statement("ALTER TABLE payout_requests ADD CONSTRAINT payout_requests_user_role_check CHECK (user_role::text = ANY (ARRAY['influencer'::character varying, 'field_agent'::character varying, 'marketer'::character varying, 'vendor'::character varying, 'customer'::character varying]::text[]))");
    }

    public function down(): void
    {
        // NOTE: If any payout_requests rows have user_role='customer' (created after
        // this feature shipped), the ADD CONSTRAINT below will fail because Postgres
        // validates existing rows against the new stricter constraint. Before rolling
        // back in an environment that may contain such rows, delete or re-role those
        // rows first. Safe to run as-is in envs that never created customer payouts.
        Schema::table('payout_requests', function (Blueprint $table) {
            $table->dropForeign(['user_payout_detail_id']);
            $table->dropIndex(['source', 'status']);
            $table->dropColumn(['source', 'referral_milestone_threshold', 'points_deducted', 'user_payout_detail_id']);
        });

        DB::statement('ALTER TABLE payout_requests DROP CONSTRAINT IF EXISTS payout_requests_user_role_check');
        DB::statement("ALTER TABLE payout_requests ADD CONSTRAINT payout_requests_user_role_check CHECK (user_role::text = ANY (ARRAY['influencer'::character varying, 'field_agent'::character varying, 'marketer'::character varying, 'vendor'::character varying]::text[]))");
    }
};
