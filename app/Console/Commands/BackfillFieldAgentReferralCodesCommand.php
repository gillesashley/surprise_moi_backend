<?php

namespace App\Console\Commands;

use App\Models\ReferralCode;
use App\Models\User;
use Illuminate\Console\Command;

class BackfillFieldAgentReferralCodesCommand extends Command
{
    protected $signature = 'field-agents:backfill-referral-codes';

    protected $description = 'Create a referral code for every field agent that does not already have one.';

    public function handle(): int
    {
        $agentsWithoutCode = User::query()
            ->where('role', 'field_agent')
            ->whereDoesntHave('referralCodes')
            ->get();

        if ($agentsWithoutCode->isEmpty()) {
            $this->info('All field agents already have referral codes.');

            return self::SUCCESS;
        }

        $this->info("Creating referral codes for {$agentsWithoutCode->count()} agent(s)...");

        foreach ($agentsWithoutCode as $agent) {
            $code = new ReferralCode([
                'influencer_id' => $agent->id,
                'is_active' => true,
            ]);
            $code->prefix = ReferralCode::getPrefixForRole('field_agent');
            $code->save();
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
