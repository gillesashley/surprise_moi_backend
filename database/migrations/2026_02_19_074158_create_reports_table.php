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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_number')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('category', ['order_issue', 'product_problem', 'vendor_dispute', 'payment_issue', 'other']);
            $table->text('description');
            $table->enum('status', ['pending', 'in_progress', 'resolved', 'cancelled'])->default('pending');
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('resolution_notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
