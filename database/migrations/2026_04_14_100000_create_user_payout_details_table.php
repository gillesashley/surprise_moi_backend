<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_payout_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('payout_method', ['mobile_money'])->default('mobile_money');
            $table->string('mobile_money_number', 32);
            $table->enum('mobile_money_provider', ['mtn', 'vodafone', 'airteltigo']);
            $table->string('account_name')->nullable();
            $table->string('paystack_recipient_code')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_default')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['user_id', 'mobile_money_number', 'mobile_money_provider'],
                'upd_user_number_provider_unique'
            );
            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_payout_details');
    }
};
