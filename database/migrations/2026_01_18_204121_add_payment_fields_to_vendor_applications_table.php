<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vendor_applications', function (Blueprint $table) {
            $table->boolean('payment_required')->default(true)->after('submitted_at');
            $table->boolean('payment_completed')->default(false)->after('payment_required');
            $table->timestamp('payment_completed_at')->nullable()->after('payment_completed');
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete()->after('payment_completed_at');
            $table->decimal('onboarding_fee', 10, 2)->nullable()->after('coupon_id');
            $table->decimal('discount_amount', 10, 2)->nullable()->after('onboarding_fee');
            $table->decimal('final_amount', 10, 2)->nullable()->after('discount_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_applications', function (Blueprint $table) {
            $table->dropForeign(['coupon_id']);
            $table->dropColumn([
                'payment_required',
                'payment_completed',
                'payment_completed_at',
                'coupon_id',
                'onboarding_fee',
                'discount_amount',
                'final_amount',
            ]);
        });
    }
};
