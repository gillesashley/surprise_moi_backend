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
        Schema::create('targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete();
            $table->enum('user_role', ['field_agent', 'marketer']);
            $table->enum('target_type', ['vendor_signups', 'revenue_generated']);
            $table->decimal('target_value', 15, 2);
            $table->decimal('current_value', 15, 2)->default(0);
            $table->decimal('bonus_amount', 10, 2);
            $table->decimal('overachievement_rate', 5, 2)->default(0);
            $table->enum('period_type', ['monthly', 'quarterly', 'annual', 'custom']);
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['active', 'completed', 'expired', 'cancelled'])->default('active');
            $table->timestamp('achieved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['user_role', 'status']);
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('targets');
    }
};
