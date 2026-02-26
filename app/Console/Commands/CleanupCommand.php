<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

class CleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'backup:cleanup {--days=7 : Number of days to keep daily backups} 
                                         {--weeks=4 : Number of weeks to keep weekly backups} 
                                         {--months=12 : Number of months to keep monthly backups}';

    /**
     * The console command description.
     */
    protected $description = 'Cleanup old database backups using GFS retention policy';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting backup cleanup...');
        
        try {
            $days = $this->option('days');
            $weeks = $this->option('weeks');
            $months = $this->option('months');
            
            $result = Process::timeout(60)->run(base_path('scripts/manage.sh') . " cleanup --days={$days} --weeks={$weeks} --months={$months}");
            
            if ($result->successful()) {
                $this->info('Backup cleanup completed successfully');
                Log::info('Database backup cleanup completed successfully');
                
                // Display cleanup output
                $this->line($result->output());
                
                return Command::SUCCESS;
            } else {
                $this->error('Backup cleanup failed');
                $this->error($result->errorOutput());
                Log::error('Database backup cleanup failed: ' . $result->errorOutput());
                
                return Command::FAILURE;
            }
            
        } catch (\Exception $e) {
            $this->error('Backup cleanup failed: ' . $e->getMessage());
            Log::error('Database backup cleanup failed: ' . $e->getMessage());
            
            return Command::FAILURE;
        }
    }
}
