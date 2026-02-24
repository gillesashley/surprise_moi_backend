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
        if (! Schema::hasTable('table')) {

            Schema::create('payment_methods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->enum('type', ['mobile_money', 'visa', 'mastercard']);
                $table->string('label')->nullable();
                $table->text('details');
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->index(['user_id', 'is_default']);
                $table->index(['user_id', 'type']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::dropIfExists('orders');
    }
};
