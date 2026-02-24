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
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE payout_requests DROP CONSTRAINT IF EXISTS payout_requests_user_role_check');
        DB::statement("ALTER TABLE payout_requests ADD CONSTRAINT payout_requests_user_role_check CHECK (user_role IN ('influencer', 'field_agent', 'marketer', 'vendor'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE payout_requests DROP CONSTRAINT IF EXISTS payout_requests_user_role_check');
        DB::statement("ALTER TABLE payout_requests ADD CONSTRAINT payout_requests_user_role_check CHECK (user_role IN ('influencer', 'field_agent', 'marketer'))");
    }
};
