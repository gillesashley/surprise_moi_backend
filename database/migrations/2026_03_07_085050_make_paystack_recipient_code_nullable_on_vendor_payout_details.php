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
        Schema::table('vendor_payout_details', function (Blueprint $table) {
            $table->string('paystack_recipient_code')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_payout_details', function (Blueprint $table) {
            $table->string('paystack_recipient_code')->nullable(false)->change();
        });
    }
};
