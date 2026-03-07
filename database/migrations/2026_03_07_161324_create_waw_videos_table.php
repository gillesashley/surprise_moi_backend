<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waw_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('users')->onDelete('cascade');
            $table->string('video_url', 500);
            $table->string('thumbnail_url', 500)->nullable();
            $table->string('caption', 200);
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('service_id')->nullable()->constrained()->onDelete('set null');
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamps();

            $table->index(['created_at']);
            $table->index(['vendor_id']);
        });

        Schema::create('waw_video_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('waw_video_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamp('created_at')->nullable();

            $table->unique(['waw_video_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waw_video_likes');
        Schema::dropIfExists('waw_videos');
    }
};
