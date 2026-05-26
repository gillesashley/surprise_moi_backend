<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            [
                'key' => 'payout_min_amount',
                'value' => '50',
                'type' => 'number',
                'description' => 'Minimum amount (GHS) a vendor can request for payout',
            ],
            [
                'key' => 'payout_max_amount',
                'value' => '10000',
                'type' => 'number',
                'description' => 'Maximum amount (GHS) a vendor can request for payout',
            ],
        ];

        foreach ($settings as $setting) {
            $exists = DB::table('settings')
                ->where('key', $setting['key'])
                ->exists();

            if (! $exists) {
                DB::table('settings')->insert(array_merge($setting, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        DB::table('settings')
            ->whereIn('key', ['payout_min_amount', 'payout_max_amount'])
            ->delete();
    }
};
