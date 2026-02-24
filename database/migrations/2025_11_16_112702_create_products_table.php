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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->foreignId('vendor_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('shop_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description');
            $table->text('detailed_description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('discount_price', 10, 2)->nullable();
            $table->integer('discount_percentage')->nullable();
            $table->string('currency', 3)->default('GHS');
            $table->string('thumbnail')->nullable();
            $table->integer('stock')->default(0);
            $table->boolean('is_available')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('reviews_count')->default(0);
            $table->json('sizes')->nullable();
            $table->json('colors')->nullable();
            $table->boolean('free_delivery')->default(false);
            $table->decimal('delivery_fee', 10, 2)->nullable();
            $table->string('estimated_delivery_days')->nullable();
            $table->text('return_policy')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'is_available', 'created_at']);
            $table->index(['vendor_id']);
            $table->index(['shop_id']);
            $table->index(['price']);
            $table->index(['is_featured']);
            $table->index(['rating']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
