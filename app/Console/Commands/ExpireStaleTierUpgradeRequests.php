<?php

namespace App\Console\Commands;

use App\Models\TierUpgradeRequest;
use Illuminate\Console\Command;

class ExpireStaleTierUpgradeRequests extends Command
{
    protected $signature = 'tier-upgrade:expire-stale {--hours=24 : Hours after which pending_payment requests are deleted}';

    protected $description = 'Delete tier upgrade requests stuck in pending_payment status for too long';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');

        $count = TierUpgradeRequest::stalePendingPayment($hours)->delete();

        $this->info("Expired {$count} stale tier upgrade request(s).");

        return Command::SUCCESS;
    }
}
