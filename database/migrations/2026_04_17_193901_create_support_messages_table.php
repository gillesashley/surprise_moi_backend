<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->nullable()->constrained('support_tickets')->nullOnDelete();
            $table->foreignId('interaction_id')->nullable()->constrained('support_interactions')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('to_phone');
            $table->text('body');
            $table->string('template_key')->nullable();
            $table->string('status')->default('queued');
            $table->string('failed_reason', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('sent_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['sent_by', 'created_at']);
            $table->index('to_user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
    }
};
