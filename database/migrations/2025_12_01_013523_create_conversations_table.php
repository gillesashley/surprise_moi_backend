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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('users')->cascadeOnDelete();
            $table->text('last_message')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->foreignId('last_message_sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('customer_unread_count')->default(0);
            $table->integer('vendor_unread_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            // Composite index for efficient lookups
            $table->unique(['customer_id', 'vendor_id']);
            $table->index(['customer_id', 'last_message_at']);
            $table->index(['vendor_id', 'last_message_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
