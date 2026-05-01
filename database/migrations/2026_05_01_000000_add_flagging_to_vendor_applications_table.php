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
            $table->timestamp('flagged_at')->nullable()->after('reviewed_at');
            $table->text('flag_reason')->nullable()->after('flagged_at');
            $table->foreignId('flagged_by')->nullable()->after('flag_reason')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('grace_period_ends_at')->nullable()->after('flagged_by');
            $table->timestamp('flag_reminder_sent_at')->nullable()->after('grace_period_ends_at');
            $table->timestamp('flag_expired_alert_sent_at')->nullable()->after('flag_reminder_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('flagged_by');
            $table->dropColumn([
                'flagged_at',
                'flag_reason',
                'grace_period_ends_at',
                'flag_reminder_sent_at',
                'flag_expired_alert_sent_at',
            ]);
        });
    }
};
