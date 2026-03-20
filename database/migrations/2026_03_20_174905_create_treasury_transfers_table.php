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
        Schema::create('treasury_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_bank_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->integer('amount_in_pesewas');
            $table->string('paystack_transfer_code')->nullable();
            $table->string('paystack_reference')->unique();
            $table->enum('status', ['pending', 'otp_required', 'processing', 'success', 'failed', 'reversed'])->default('pending');
            $table->json('paystack_response')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('paystack_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treasury_transfers');
    }
};
