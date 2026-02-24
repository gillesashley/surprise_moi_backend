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
        // Personality traits lookup table
        Schema::create('personality_traits', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('icon')->nullable();
            $table->timestamps();
        });

        // Pivot table for user personality traits (many-to-many)
        Schema::create('personality_trait_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('personality_trait_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'personality_trait_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personality_trait_user');
        Schema::dropIfExists('personality_traits');
    }
};
