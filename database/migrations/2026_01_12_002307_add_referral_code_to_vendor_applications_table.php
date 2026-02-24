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
        Schema::table('vendor_applications', function (Blueprint $table) {
            $table->foreignId('referral_code_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('referral_code_used')->nullable()->after('referral_code_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_applications', function (Blueprint $table) {
            $table->dropForeign(['referral_code_id']);
            $table->dropColumn(['referral_code_id', 'referral_code_used']);
        });
    }
};
