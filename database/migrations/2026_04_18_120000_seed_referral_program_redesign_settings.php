<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            [
                'key' => 'vendor_onboarding_subsidy_pct',
                'value' => '25.00',
                'type' => 'string',
                'description' => "The discount applied to a vendor's onboarding fee when they onboard using a valid referral code. Applies identically to Tier 1 and Tier 2. Example: at 25%, a Tier 1 vendor with a 200 GHS fee pays 150 GHS.",
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'referral_points_per_ghs',
                'value' => '10',
                'type' => 'string',
                'description' => 'How many points a referrer sees in their wallet for every 1 GHS earned. A higher number makes the reward feel larger. Example: at 10, a 15 GHS reward displays as 150 points.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'referral_cashout_min_points',
                'value' => '1000',
                'type' => 'string',
                'description' => 'The lowest points balance at which a referrer can request a cashout. Example: at 1000 points (= 100 GHS at the current conversion rate), a referrer must reach this balance before they can withdraw.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('settings')->insert($settings);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'vendor_onboarding_subsidy_pct',
            'referral_points_per_ghs',
            'referral_cashout_min_points',
        ])->delete();
    }
};
