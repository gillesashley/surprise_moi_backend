<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove per-order referral commission.
 *
 * The referral program is strictly about subsidizing vendor onboarding
 * fees (the one-time registration bonus). The per-order commission
 * pipeline was a misfeature — no rate was ever exposed in admin settings
 * and a flat 5% was always used. Historical Earning rows with
 * earning_type = 'commission' are preserved for audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_codes', function (Blueprint $table) {
            $table->dropColumn(['commission_rate', 'commission_duration_months']);
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->dropColumn('commission_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('referral_codes', function (Blueprint $table) {
            $table->decimal('commission_rate', 5, 2)->default(0)->after('registration_bonus');
            $table->integer('commission_duration_months')->default(3)->after('commission_rate');
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->timestamp('commission_expires_at')->nullable()->after('activated_at');
        });
    }
};
