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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('shop_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description');
            $table->string('service_type');
            $table->decimal('charge_start', 10, 2);
            $table->decimal('charge_end', 10, 2)->nullable();
            $table->string('currency', 3)->default('GHS');
            $table->string('thumbnail')->nullable();
            $table->enum('availability', ['available', 'unavailable', 'booked'])->default('available');
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('reviews_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vendor_id']);
            $table->index(['shop_id']);
            $table->index(['service_type']);
            $table->index(['availability']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
