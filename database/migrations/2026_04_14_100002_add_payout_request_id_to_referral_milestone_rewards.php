<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('referral_milestone_rewards', function (Blueprint $table) {
            $table->foreignId('payout_request_id')->nullable()->after('fulfilled_at')
                ->constrained('payout_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('referral_milestone_rewards', function (Blueprint $table) {
            $table->dropForeign(['payout_request_id']);
            $table->dropColumn('payout_request_id');
        });
    }
};
