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
            $table->string('delivery_pin', 4)->nullable()->after('tracking_number');
            $table->timestamp('delivery_confirmed_at')->nullable()->after('delivered_at');
            $table->string('delivery_confirmed_by')->nullable()->after('delivery_confirmed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_pin', 'delivery_confirmed_at', 'delivery_confirmed_by']);
        });
    }
};
