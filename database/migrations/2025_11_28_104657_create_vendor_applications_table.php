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
        Schema::create('vendor_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Application status tracking
            $table->string('status')->default('pending'); // pending, under_review, approved, rejected
            $table->integer('current_step')->default(1); // 1-5 wizard steps
            $table->integer('completed_step')->default(0); // Highest completed step

            // Step 1: Ghana Card Documents (stored as file paths)
            $table->string('ghana_card_front')->nullable();
            $table->string('ghana_card_back')->nullable();

            // Step 2: Business Registration Flags
            $table->boolean('has_business_certificate')->default(false);
            $table->boolean('has_tin')->default(false);

            // Step 3A: Registered Vendor Documents (if has business documents)
            $table->string('business_certificate_document')->nullable();
            $table->string('tin_document')->nullable();

            // Step 3B: Unregistered Vendor Verification (if no business documents)
            $table->string('selfie_image')->nullable();
            $table->string('mobile_money_number')->nullable();
            $table->string('mobile_money_provider')->nullable(); // mtn, vodafone, airteltigo
            $table->string('proof_of_business')->nullable(); // receipts, invoices, etc.

            // Step 3 (Both): Social Media Handles (optional)
            $table->string('facebook_handle')->nullable();
            $table->string('instagram_handle')->nullable();
            $table->string('twitter_handle')->nullable();

            // Admin review fields
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['user_id', 'status']);
            $table->index('status');
            $table->index('submitted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_applications');
    }
};
