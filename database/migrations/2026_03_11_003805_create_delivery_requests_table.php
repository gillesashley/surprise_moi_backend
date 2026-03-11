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
        Schema::create('delivery_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('rider_id')->nullable()->constrained('riders')->nullOnDelete();
            $table->foreignId('vendor_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('assigned_rider_id')->nullable()->constrained('riders')->nullOnDelete();
            $table->enum('status', [
                'broadcasting', 'assigned', 'accepted', 'picked_up',
                'in_transit', 'delivered', 'cancelled', 'expired',
            ])->default('broadcasting');
            $table->string('pickup_address');
            $table->decimal('pickup_latitude', 10, 7);
            $table->decimal('pickup_longitude', 10, 7);
            $table->string('dropoff_address');
            $table->decimal('dropoff_latitude', 10, 7);
            $table->decimal('dropoff_longitude', 10, 7);
            $table->decimal('delivery_fee', 10, 2);
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->decimal('broadcast_radius_km', 5, 2)->default(5.00);
            $table->unsignedTinyInteger('broadcast_attempts')->default(0);
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index(['rider_id', 'status']);
            $table->index(['vendor_id', 'status']);
            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_requests');
    }
};
