<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->string('channel');
            $table->string('direction');
            $table->text('summary');
            $table->timestamp('occurred_at');
            $table->date('follow_up_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['ticket_id', 'occurred_at']);
            $table->index('follow_up_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_interactions');
    }
};
