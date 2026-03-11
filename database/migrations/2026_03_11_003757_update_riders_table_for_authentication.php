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
        Schema::table('riders', function (Blueprint $table) {
            $table->string('password')->after('email');
            $table->timestamp('email_verified_at')->nullable()->after('password');
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
            $table->string('ghana_card_front')->nullable()->after('phone_verified_at');
            $table->string('ghana_card_back')->nullable()->after('ghana_card_front');
            $table->string('drivers_license')->nullable()->after('ghana_card_back');
            $table->string('vehicle_photo')->nullable()->after('drivers_license');
            $table->enum('vehicle_category', ['motorbike', 'car'])->default('motorbike')->after('vehicle_photo');
            $table->enum('status', ['pending', 'under_review', 'approved', 'rejected', 'suspended'])->default('pending')->after('vehicle_category');
            $table->boolean('is_online')->default(false)->after('status');
            $table->decimal('current_latitude', 10, 7)->nullable()->after('is_online');
            $table->decimal('current_longitude', 10, 7)->nullable()->after('current_latitude');
            $table->timestamp('location_updated_at')->nullable()->after('current_longitude');
            $table->string('device_token')->nullable()->after('location_updated_at');
            $table->decimal('average_rating', 3, 2)->default(0)->after('device_token');
            $table->unsignedInteger('total_deliveries')->default(0)->after('average_rating');
            $table->rememberToken()->after('total_deliveries');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->dropColumn([
                'password', 'email_verified_at', 'phone_verified_at',
                'ghana_card_front', 'ghana_card_back', 'drivers_license',
                'vehicle_photo', 'vehicle_category', 'status', 'is_online',
                'current_latitude', 'current_longitude', 'location_updated_at',
                'device_token', 'average_rating', 'total_deliveries', 'remember_token',
            ]);
        });
    }
};
