<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('settings')
            ->where('key', 'referral_bonus_field_agent_team_pct')
            ->exists();

        if ($existing) {
            return;
        }

        DB::table('settings')->insert([
            'key' => 'referral_bonus_field_agent_team_pct',
            'value' => '35.00',
            'type' => 'number',
            'description' => 'Field Agent (Team) referral bonus percentage (applied to referred vendor\'s tier onboarding fee)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('key', 'referral_bonus_field_agent_team_pct')
            ->delete();
    }
};
