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
        Schema::create('referral_milestone_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('threshold');            // 1000, 5000, 10000, ...
            $table->unsignedInteger('points_at_milestone');  // actual points when threshold crossed
            $table->enum('status', ['pending', 'fulfilled', 'cancelled'])->default('pending');
            $table->string('reward_type')->nullable();       // 'cash' | 'gift' | 'power_bank' | 'other'
            $table->text('reward_description')->nullable();
            $table->decimal('reward_value', 10, 2)->nullable();
            $table->foreignId('fulfilled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fulfilled_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'threshold']);        // idempotency — same threshold can't be crossed twice
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_milestone_rewards');
    }
};
