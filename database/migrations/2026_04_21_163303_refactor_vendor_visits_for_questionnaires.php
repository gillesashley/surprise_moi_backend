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
        Schema::dropIfExists('vendor_visit_items');

        Schema::table('vendor_visits', function (Blueprint $table) {
            $table->foreignId('vendor_application_id')->nullable()->constrained('vendor_applications')->cascadeOnDelete();

            // New Questionnaire Fields
            $table->string('ghana_card_number')->nullable();
            $table->string('tin_number')->nullable();
            $table->boolean('has_shop')->default(false);
            $table->string('shop_location')->nullable();
            $table->string('primary_business_address')->nullable();

            // Drop Legacy Fields
            $table->dropColumn([
                'visit_latitude',
                'visit_longitude',
                'notes',
                'escalated',
                'badge_issued_at',
                'badge_expires_at',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_visits', function (Blueprint $table) {
            $table->dropForeign(['vendor_application_id']);
            $table->dropColumn([
                'vendor_application_id',
                'ghana_card_number',
                'tin_number',
                'has_shop',
                'shop_location',
                'primary_business_address',
            ]);

            $table->decimal('visit_latitude', 10, 7)->nullable();
            $table->decimal('visit_longitude', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('escalated')->default(false);
            $table->timestamp('badge_issued_at')->nullable();
            $table->timestamp('badge_expires_at')->nullable();
        });

        Schema::create('vendor_visit_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('vendor_visit_id');
            $table->string('item_key');
            $table->string('category');
            $table->string('criticality');
            $table->boolean('passed')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('vendor_visit_id')
                ->references('id')->on('vendor_visits')
                ->cascadeOnDelete();
            $table->unique(['vendor_visit_id', 'item_key']);
            $table->index('vendor_visit_id');
        });
    }
};
