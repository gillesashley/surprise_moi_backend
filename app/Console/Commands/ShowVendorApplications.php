<?php

namespace App\Console\Commands;

use App\Models\VendorApplication;
use Illuminate\Console\Command;

class ShowVendorApplications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vendor:list-applications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display all vendor applications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $applications = VendorApplication::with('user:id,name,email')->get();

        if ($applications->isEmpty()) {
            $this->info('No vendor applications found.');

            return;
        }

        $this->info("Total Applications: {$applications->count()}");
        $this->newLine();

        $headers = ['ID', 'Status', 'User Name', 'Email', 'Submitted At'];
        $rows = $applications->map(function ($app) {
            return [
                $app->id,
                $app->status,
                $app->user->name,
                $app->user->email,
                $app->submitted_at ? $app->submitted_at->format('Y-m-d H:i') : 'Not submitted',
            ];
        });

        $this->table($headers, $rows);

        // Summary by status
        $this->newLine();
        $this->info('Summary by Status:');
        $summary = $applications->groupBy('status')->map->count();
        foreach ($summary as $status => $count) {
            $this->line("  {$status}: {$count}");
        }
    }
}
