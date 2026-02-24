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
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('vendor_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('sku', 128)->nullable();
            $table->string('name', 512);
            $table->bigInteger('unit_price_cents');
            $table->integer('quantity');
            $table->bigInteger('line_total_cents');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['cart_id', 'product_id', 'sku']);
            $table->index('cart_id');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
