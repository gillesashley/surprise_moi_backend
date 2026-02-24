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
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('platform_commission_rate', 5, 2)->nullable()->after('total')->comment('Platform commission rate percentage');
            $table->decimal('platform_commission_amount', 10, 2)->nullable()->after('platform_commission_rate')->comment('Platform commission amount deducted');
            $table->decimal('vendor_payout_amount', 10, 2)->nullable()->after('platform_commission_amount')->comment('Amount payable to vendor after commission');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['platform_commission_rate', 'platform_commission_amount', 'vendor_payout_amount']);
        });
    }
};
