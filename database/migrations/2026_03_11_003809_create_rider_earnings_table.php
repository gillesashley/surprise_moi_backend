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
        Schema::create('rider_earnings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('rider_id')->constrained('riders')->onDelete('cascade');
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->uuid('delivery_request_id');
            $table->foreign('delivery_request_id')->references('id')->on('delivery_requests')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->enum('type', ['delivery_fee', 'bonus', 'adjustment'])->default('delivery_fee');
            $table->enum('status', ['pending', 'available', 'withdrawn'])->default('pending');
            $table->timestamp('available_at')->nullable();
            $table->timestamps();

            $table->index(['rider_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rider_earnings');
    }
};
