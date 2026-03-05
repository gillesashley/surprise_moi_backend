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
        Schema::create('vendor_payout_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('users')->onDelete('cascade');
            $table->string('payout_method'); // mobile_money, bank_transfer
            $table->string('account_name');
            $table->string('account_number');
            $table->string('bank_code');
            $table->string('bank_name');
            $table->string('provider')->nullable(); // mtn, vodafone, airteltigo
            $table->string('paystack_recipient_code');
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_default')->default(true);
            $table->timestamps();

            $table->index('vendor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_payout_details');
    }
};
