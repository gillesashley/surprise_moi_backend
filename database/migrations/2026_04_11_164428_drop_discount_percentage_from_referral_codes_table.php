<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the long-orphaned discount_percentage column from referral_codes.
     *
     * The field was added by 2026_01_14_205946_add_discount_to_referral_codes_table
     * but was never read by any production code path — verified by grep over the
     * full app/ tree on 2026-04-11. The admin form stopped collecting it with the
     * Phase 1 form simplification, and the column has no foreign key, no index,
     * and no consumer (services, jobs, resources, reports, or external APIs).
     *
     * Commission columns (commission_rate, commission_duration_months) are
     * deliberately left in place because PaystackService and Referral::activate
     * still read them for legacy referral codes that earn commission on vendor
     * orders.
     */
    public function up(): void
    {
        Schema::table('referral_codes', function (Blueprint $table) {
            $table->dropColumn('discount_percentage');
        });
    }

    /**
     * Recreate the column with the original definition so a rollback restores
     * the schema exactly as it was before this migration ran. Existing row
     * data is not recoverable — the down() only restores structure.
     */
    public function down(): void
    {
        Schema::table('referral_codes', function (Blueprint $table) {
            $table->decimal('discount_percentage', 5, 2)->default(0);
        });
    }
};
