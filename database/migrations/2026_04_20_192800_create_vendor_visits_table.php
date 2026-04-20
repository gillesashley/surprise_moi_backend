<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_visits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('vendor_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('field_agent_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('visit_latitude', 10, 7);
            $table->decimal('visit_longitude', 10, 7);
            $table->string('storefront_photo_path')->nullable();
            $table->string('owner_photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('escalated')->default(false);
            $table->string('computed_result')->nullable();
            $table->string('admin_override_result')->nullable();
            $table->text('admin_override_reason')->nullable();
            $table->foreignId('admin_override_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('admin_override_at')->nullable();
            $table->timestamp('badge_issued_at')->nullable();
            $table->timestamp('badge_expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vendor_user_id', 'status']);
            $table->index(['field_agent_user_id', 'status']);
            $table->index('badge_expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_visits');
    }
};
