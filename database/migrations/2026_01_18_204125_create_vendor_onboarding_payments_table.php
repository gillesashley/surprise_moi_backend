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
        Schema::create('vendor_onboarding_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();

            // Paystack-specific fields
            $table->string('reference')->unique();
            $table->string('paystack_reference')->nullable();
            $table->string('authorization_url')->nullable();
            $table->string('access_code')->nullable();

            // Payment details
            $table->decimal('amount', 10, 2);
            $table->integer('amount_in_kobo');
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('GHS');
            $table->string('channel')->nullable();
            $table->string('payment_method_type')->nullable();

            // Status tracking
            $table->enum('status', [
                'pending',
                'processing',
                'success',
                'failed',
                'abandoned',
                'reversed',
                'cancelled',
            ])->default('pending');

            // Card details (masked for security)
            $table->string('card_last4')->nullable();
            $table->string('card_type')->nullable();
            $table->string('card_exp_month')->nullable();
            $table->string('card_exp_year')->nullable();
            $table->string('card_bank')->nullable();

            // Mobile money details
            $table->string('mobile_money_number')->nullable();
            $table->string('mobile_money_provider')->nullable();

            // Additional metadata
            $table->json('metadata')->nullable();
            $table->json('log')->nullable();
            $table->string('gateway_response')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('failure_reason')->nullable();

            // Timestamps
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'status']);
            $table->index(['vendor_application_id', 'status']);
            $table->index('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_onboarding_payments');
    }
};
