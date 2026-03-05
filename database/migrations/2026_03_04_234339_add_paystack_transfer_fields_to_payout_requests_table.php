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
        Schema::table('payout_requests', function (Blueprint $table) {
            $table->string('paystack_transfer_code')->nullable()->after('notes');
            $table->string('paystack_transfer_reference')->nullable()->after('paystack_transfer_code');
            $table->unsignedBigInteger('paystack_transfer_id')->nullable()->after('paystack_transfer_reference');
            $table->foreignId('payout_detail_id')->nullable()->after('paystack_transfer_id')
                ->constrained('vendor_payout_details')->onDelete('set null');
            $table->timestamp('rejected_at')->nullable()->after('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payout_requests', function (Blueprint $table) {
            $table->dropForeign(['payout_detail_id']);
            $table->dropColumn([
                'paystack_transfer_code',
                'paystack_transfer_reference',
                'paystack_transfer_id',
                'payout_detail_id',
                'rejected_at',
            ]);
        });
    }
};
