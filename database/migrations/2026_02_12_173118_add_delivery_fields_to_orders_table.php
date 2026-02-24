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
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('rider_id')->nullable()->after('vendor_id')->constrained('riders')->nullOnDelete();
            $table->string('receiver_name')->nullable()->after('special_instructions');
            $table->string('receiver_phone')->nullable()->after('receiver_name');
            $table->enum('delivery_method', ['vendor_self', 'platform_rider', 'third_party_courier'])->nullable()->after('receiver_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['rider_id']);
            $table->dropColumn(['rider_id', 'receiver_name', 'receiver_phone', 'delivery_method']);
        });
    }
};
