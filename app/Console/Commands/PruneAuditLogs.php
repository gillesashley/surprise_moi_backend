<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune {--days=90}';

    protected $description = 'Delete standard-retention audit log rows older than the retention window.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = Carbon::now()->subDays($days);

        if (! config('database.connections.audit_pruner.username')) {
            $msg = 'AUDIT_PRUNER_DB_USERNAME not set — skipping prune.';
            $this->warn($msg);
            Log::warning($msg);

            return self::SUCCESS;
        }

        $deleted = DB::connection('audit_pruner')
            ->table('activity_log')
            ->where('created_at', '<', $cutoff)
            ->whereRaw("properties ->> 'retention_class' = 'standard'")
            ->delete();

        $this->info("Pruned {$deleted} standard audit rows older than {$days} days.");

        return self::SUCCESS;
    }
}
