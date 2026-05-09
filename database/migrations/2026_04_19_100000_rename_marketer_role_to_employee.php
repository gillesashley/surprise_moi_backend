<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rename the `marketer` user role to `employee` throughout the database.
 *
 * Touches:
 *  - users.role
 *  - earnings.user_role (+ check constraint)
 *  - targets.user_role  (+ check constraint)
 *  - payout_requests.user_role (+ check constraint)
 *  - settings key `referral_bonus_marketer_pct` → `referral_bonus_employee_pct`
 *
 * Strategy: drop existing CHECK constraints first (they'd otherwise block the
 * data update), rewrite rows, then recreate the constraints with 'employee' in
 * place of 'marketer'. Run inside a transaction so a partial migration is
 * rolled back cleanly.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $isPgsql = DB::getDriverName() === 'pgsql';

            // 1. Drop existing check constraints so we can rewrite values.
            if ($isPgsql) {
                DB::statement('ALTER TABLE earnings DROP CONSTRAINT IF EXISTS earnings_user_role_check');
                DB::statement('ALTER TABLE targets DROP CONSTRAINT IF EXISTS targets_user_role_check');
                DB::statement('ALTER TABLE payout_requests DROP CONSTRAINT IF EXISTS payout_requests_user_role_check');
            }

            // 2. Rewrite data.
            DB::table('users')->where('role', 'marketer')->update(['role' => 'employee']);
            DB::table('earnings')->where('user_role', 'marketer')->update(['user_role' => 'employee']);
            DB::table('targets')->where('user_role', 'marketer')->update(['user_role' => 'employee']);
            DB::table('payout_requests')->where('user_role', 'marketer')->update(['user_role' => 'employee']);

            // 3. Rename the settings key.
            DB::table('settings')
                ->where('key', 'referral_bonus_marketer_pct')
                ->update(['key' => 'referral_bonus_employee_pct']);

            // 4. Recreate check constraints with 'employee' in place of 'marketer'.
            if ($isPgsql) {
                DB::statement("ALTER TABLE earnings ADD CONSTRAINT earnings_user_role_check CHECK (user_role::text = ANY (ARRAY['influencer'::character varying, 'field_agent'::character varying, 'employee'::character varying]::text[]))");
                DB::statement("ALTER TABLE targets ADD CONSTRAINT targets_user_role_check CHECK (user_role::text = ANY (ARRAY['field_agent'::character varying, 'employee'::character varying]::text[]))");
                DB::statement("ALTER TABLE payout_requests ADD CONSTRAINT payout_requests_user_role_check CHECK (user_role::text = ANY (ARRAY['influencer'::character varying, 'field_agent'::character varying, 'employee'::character varying, 'vendor'::character varying, 'customer'::character varying]::text[]))");
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $isPgsql = DB::getDriverName() === 'pgsql';

            if ($isPgsql) {
                DB::statement('ALTER TABLE earnings DROP CONSTRAINT IF EXISTS earnings_user_role_check');
                DB::statement('ALTER TABLE targets DROP CONSTRAINT IF EXISTS targets_user_role_check');
                DB::statement('ALTER TABLE payout_requests DROP CONSTRAINT IF EXISTS payout_requests_user_role_check');
            }

            DB::table('users')->where('role', 'employee')->update(['role' => 'marketer']);
            DB::table('earnings')->where('user_role', 'employee')->update(['user_role' => 'marketer']);
            DB::table('targets')->where('user_role', 'employee')->update(['user_role' => 'marketer']);
            DB::table('payout_requests')->where('user_role', 'employee')->update(['user_role' => 'marketer']);

            DB::table('settings')
                ->where('key', 'referral_bonus_employee_pct')
                ->update(['key' => 'referral_bonus_marketer_pct']);

            if ($isPgsql) {
                DB::statement("ALTER TABLE earnings ADD CONSTRAINT earnings_user_role_check CHECK (user_role::text = ANY (ARRAY['influencer'::character varying, 'field_agent'::character varying, 'marketer'::character varying]::text[]))");
                DB::statement("ALTER TABLE targets ADD CONSTRAINT targets_user_role_check CHECK (user_role::text = ANY (ARRAY['field_agent'::character varying, 'marketer'::character varying]::text[]))");
                DB::statement("ALTER TABLE payout_requests ADD CONSTRAINT payout_requests_user_role_check CHECK (user_role::text = ANY (ARRAY['influencer'::character varying, 'field_agent'::character varying, 'marketer'::character varying, 'vendor'::character varying, 'customer'::character varying]::text[]))");
            }
        });
    }
};
