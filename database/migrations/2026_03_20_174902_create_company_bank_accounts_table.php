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
        Schema::create('company_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_name');
            $table->string('account_number');
            $table->string('bank_code');
            $table->string('bank_name');
            $table->string('paystack_recipient_code')->nullable();
            $table->boolean('is_active')->default(false);
            $table->foreignId('added_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_bank_accounts');
    }
};
