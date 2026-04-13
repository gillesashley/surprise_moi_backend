<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            [
                'key' => 'referral_bonus_customer_pct',
                'value' => '15.00',
                'type' => 'number',
                'description' => 'Customer referral bonus percentage (applied to referred person\'s tier onboarding fee)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'referral_bonus_vendor_pct',
                'value' => '20.00',
                'type' => 'number',
                'description' => 'Vendor referral bonus percentage (applied to referred person\'s tier onboarding fee)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'referral_bonus_influencer_pct',
                'value' => '25.00',
                'type' => 'number',
                'description' => 'Influencer referral bonus percentage (applied to referred person\'s tier onboarding fee)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'referral_bonus_field_agent_pct',
                'value' => '30.00',
                'type' => 'number',
                'description' => 'Field Agent referral bonus percentage (applied to referred person\'s tier onboarding fee)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'referral_bonus_marketer_pct',
                'value' => '20.00',
                'type' => 'number',
                'description' => 'Marketer referral bonus percentage (applied to referred person\'s tier onboarding fee)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('settings')->insert($settings);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'referral_bonus_customer_pct',
            'referral_bonus_vendor_pct',
            'referral_bonus_influencer_pct',
            'referral_bonus_field_agent_pct',
            'referral_bonus_marketer_pct',
        ])->delete();
    }
};
