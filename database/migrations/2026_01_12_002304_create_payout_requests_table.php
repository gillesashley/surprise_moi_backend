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
        Schema::create('payout_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('request_number')->unique();
            $table->enum('user_role', ['influencer', 'field_agent', 'marketer']);
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('GHS');
            $table->enum('payout_method', ['mobile_money', 'bank_transfer', 'quarterly_salary']);
            $table->string('mobile_money_number')->nullable();
            $table->string('mobile_money_provider')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
            $table->enum('status', ['pending', 'processing', 'approved', 'rejected', 'paid', 'cancelled'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index('request_number');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payout_requests');
    }
};
