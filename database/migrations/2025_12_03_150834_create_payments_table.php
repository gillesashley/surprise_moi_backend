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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // Paystack-specific fields
            $table->string('reference')->unique(); // Unique transaction reference
            $table->string('paystack_reference')->nullable(); // Paystack's reference
            $table->string('authorization_url')->nullable(); // Payment page URL
            $table->string('access_code')->nullable(); // Paystack access code

            // Payment details
            $table->decimal('amount', 12, 2); // Amount in major currency unit (GHS)
            $table->integer('amount_in_kobo'); // Amount in minor unit (pesewas)
            $table->string('currency', 3)->default('GHS');
            $table->string('channel')->nullable(); // card, bank, mobile_money, etc.
            $table->string('payment_method_type')->nullable(); // visa, mastercard, mtn, vodafone, etc.

            // Status tracking
            $table->enum('status', [
                'pending',      // Payment initiated but not completed
                'processing',   // Payment is being processed
                'success',      // Payment completed successfully
                'failed',       // Payment failed
                'abandoned',    // User abandoned the payment
                'reversed',     // Payment was reversed/refunded
                'cancelled',    // Payment was cancelled
            ])->default('pending');

            // Card details (masked for security)
            $table->string('card_last4')->nullable();
            $table->string('card_type')->nullable(); // visa, mastercard, verve
            $table->string('card_exp_month')->nullable();
            $table->string('card_exp_year')->nullable();
            $table->string('card_bank')->nullable();

            // Mobile money details
            $table->string('mobile_money_number')->nullable();
            $table->string('mobile_money_provider')->nullable();

            // Additional metadata
            $table->json('metadata')->nullable(); // Store additional Paystack response data
            $table->json('log')->nullable(); // Transaction log from Paystack
            $table->string('gateway_response')->nullable(); // Gateway response message
            $table->string('ip_address')->nullable();
            $table->text('failure_reason')->nullable();

            // Timestamps
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'status']);
            $table->index(['order_id', 'status']);
            $table->index('paid_at');
        });

        // Add payment_status column to orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('payment_status', [
                'unpaid',
                'pending',
                'paid',
                'failed',
                'refunded',
            ])->default('unpaid')->after('status');
            $table->index('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_status']);
            $table->dropColumn('payment_status');
        });

        Schema::dropIfExists('payments');
    }
};
