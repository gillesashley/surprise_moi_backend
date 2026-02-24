<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Remove old vendor_onboarding_fee setting
        DB::table('settings')->where('key', 'vendor_onboarding_fee')->delete();

        // Insert tier-based settings
        $settings = [
            [
                'key' => 'vendor_tier1_onboarding_fee',
                'value' => '150.00',
                'type' => 'number',
                'description' => 'Tier 1 vendor onboarding fee (with business certificates)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'vendor_tier2_onboarding_fee',
                'value' => '100.00',
                'type' => 'number',
                'description' => 'Tier 2 vendor onboarding fee (without business certificates)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'vendor_tier1_commission_rate',
                'value' => '12.00',
                'type' => 'number',
                'description' => 'Tier 1 vendor commission rate percentage',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'vendor_tier2_commission_rate',
                'value' => '8.00',
                'type' => 'number',
                'description' => 'Tier 2 vendor commission rate percentage',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('settings')->insert($settings);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove tier-based settings
        DB::table('settings')->whereIn('key', [
            'vendor_tier1_onboarding_fee',
            'vendor_tier2_onboarding_fee',
            'vendor_tier1_commission_rate',
            'vendor_tier2_commission_rate',
        ])->delete();

        // Restore old vendor_onboarding_fee setting
        DB::table('settings')->insert([
            'key' => 'vendor_onboarding_fee',
            'value' => '100.00',
            'type' => 'number',
            'description' => 'Vendor onboarding fee amount in GHS',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
