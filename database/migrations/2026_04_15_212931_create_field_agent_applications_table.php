<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_agent_applications', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('contact_number');

            $table->foreignId('region_id')->constrained()->restrictOnDelete();
            $table->foreignId('city_id')->constrained()->restrictOnDelete();
            $table->string('location');

            $table->string('ghana_card_number');
            $table->string('ghana_card_image_path');
            $table->string('selfie_path');

            $table->string('password')->nullable();

            $table->string('status')->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('approved_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('email');
            $table->index('ghana_card_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_agent_applications');
    }
};
