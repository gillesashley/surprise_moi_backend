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
        Schema::create('target_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('achieved_value', 15, 2);
            $table->decimal('bonus_earned', 10, 2);
            $table->decimal('overachievement_bonus', 10, 2)->default(0);
            $table->decimal('total_earned', 10, 2);
            $table->integer('completion_percentage');
            $table->timestamp('achieved_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('target_id');
            $table->index('user_id');
            $table->index('achieved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('target_achievements');
    }
};
