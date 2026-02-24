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
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('influencer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vendor_application_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'active', 'inactive', 'expired'])->default('pending');
            $table->decimal('earned_amount', 10, 2)->default(0);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('commission_expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['influencer_id', 'status']);
            $table->index('vendor_id');
            $table->index('referral_code_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
