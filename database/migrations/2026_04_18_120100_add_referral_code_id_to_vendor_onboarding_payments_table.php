<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_onboarding_payments', function (Blueprint $table) {
            $table->foreignId('referral_code_id')
                ->nullable()
                ->after('vendor_application_id')
                ->constrained('referral_codes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_onboarding_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referral_code_id');
        });
    }
};
