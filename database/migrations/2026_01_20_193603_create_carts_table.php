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
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->uuid('cart_token')->nullable()->unique();
            $table->string('currency', 8)->default('GHS');
            $table->bigInteger('subtotal_cents')->default(0);
            $table->bigInteger('shipping_cents')->default(0);
            $table->bigInteger('tax_cents')->default(0);
            $table->bigInteger('discount_cents')->default(0);
            $table->bigInteger('total_cents')->default(0);
            $table->json('metadata')->nullable();
            $table->integer('version')->default(1);
            $table->timestamps();

            $table->index('cart_token');
            $table->index(['user_id', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
