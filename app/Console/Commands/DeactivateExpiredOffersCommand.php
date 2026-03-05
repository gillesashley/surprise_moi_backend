<?php

namespace App\Console\Commands;

use App\Models\SpecialOffer;
use Illuminate\Console\Command;

class DeactivateExpiredOffersCommand extends Command
{
    protected $signature = 'special-offers:deactivate-expired';

    protected $description = 'Deactivate special offers that have passed their end date';

    public function handle(): int
    {
        $count = SpecialOffer::where('is_active', true)
            ->where('ends_at', '<', now())
            ->update(['is_active' => false]);

        $this->info("Deactivated {$count} expired special offer(s).");

        return self::SUCCESS;
    }
}
