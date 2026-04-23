<?php

namespace App\Console\Commands;

use App\Models\Earning;
use App\Models\Referral;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillReferralEarningsCommand extends Command
{
    protected $signature = 'earnings:backfill-referrals
        {--dry-run : Report what would be created without writing}';

    protected $description = 'Create missing Earning ledger rows for historical Referrals activated before the Earning-creation step existed in activateReferral()';

    private int $created = 0;

    private int $skippedHasEarning = 0;

    private int $skippedNotEarningCapable = 0;

    private int $failed = 0;

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $this->info('DRY RUN — no records will be created.');
        }

        $referrals = Referral::query()
            ->where('earned_amount', '>', 0)
            ->where('status', Referral::STATUS_ACTIVE)
            ->with(['influencer', 'vendorApplication.user'])
            ->get();

        $this->info("Found {$referrals->count()} activated referrals with earnings.");

        $bar = $this->output->createProgressBar($referrals->count());
        $bar->start();

        foreach ($referrals as $referral) {
            try {
                $influencer = $referral->influencer;

                if (! $influencer || ! $influencer->isEarningCapable()) {
                    $this->skippedNotEarningCapable++;
                    $bar->advance();

                    continue;
                }

                $existing = Earning::query()
                    ->where('earnable_type', Referral::class)
                    ->where('earnable_id', $referral->id)
                    ->exists();

                if ($existing) {
                    $this->skippedHasEarning++;
                    $bar->advance();

                    continue;
                }

                if (! $isDryRun) {
                    DB::transaction(function () use ($referral, $influencer): void {
                        $vendorName = $referral->vendorApplication?->user?->name ?? 'vendor';

                        Earning::create([
                            'user_id' => $influencer->id,
                            'user_role' => $influencer->role,
                            'earning_type' => Earning::TYPE_REFERRAL_BONUS,
                            'earnable_id' => $referral->id,
                            'earnable_type' => Referral::class,
                            'amount' => $referral->earned_amount,
                            'currency' => 'GHS',
                            'status' => Earning::STATUS_PENDING,
                            'description' => "Referral bonus for vendor onboarding: {$vendorName}",
                            'earned_at' => $referral->activated_at ?? $referral->created_at,
                        ]);
                    });
                }

                $this->created++;
            } catch (\Throwable $e) {
                $this->failed++;
                $this->newLine();
                $this->error("  Failed on referral #{$referral->id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Created', $this->created],
                ['Skipped (earning already exists)', $this->skippedHasEarning],
                ['Skipped (user not earning-capable)', $this->skippedNotEarningCapable],
                ['Failed', $this->failed],
            ]
        );

        if ($this->failed > 0) {
            $this->error('Some rows failed — review the output above.');

            return self::FAILURE;
        }

        $this->info($isDryRun ? 'Dry run complete.' : 'Backfill complete.');

        return self::SUCCESS;
    }
}
