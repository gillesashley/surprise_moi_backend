<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

class BackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'backup:run {--type= : The type of backup to run (full, data, or both)}';

    /**
     * The console command description.
     */
    protected $description = 'Run database backup using existing bash script with GFS cleanup';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting database backup...');
        
        try {
            $backupType = $this->option('type') ?? 'both';
            
            $result = Process::timeout(300)->run(base_path('scripts/manage.sh') . ' backup');
            
            if ($result->successful()) {
                $this->info('Backup completed successfully');
                Log::info('Database backup completed successfully');
                
                // Display backup output
                $this->line($result->output());
                
                return Command::SUCCESS;
            } else {
                $this->error('Backup failed');
                $this->error($result->errorOutput());
                Log::error('Database backup failed: ' . $result->errorOutput());
                
                return Command::FAILURE;
            }
            
        } catch (\Exception $e) {
            $this->error('Backup failed: ' . $e->getMessage());
            Log::error('Database backup failed: ' . $e->getMessage());
            
            return Command::FAILURE;
        }
    }
}
