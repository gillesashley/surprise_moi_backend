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
        Schema::create('rider_withdrawal_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('rider_id')->constrained('riders')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'processing', 'completed', 'rejected', 'failed'])->default('pending');
            $table->enum('mobile_money_provider', ['mtn', 'vodafone', 'airteltigo']);
            $table->string('mobile_money_number');
            $table->timestamp('processed_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['rider_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rider_withdrawal_requests');
    }
};
