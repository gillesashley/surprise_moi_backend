<?php

namespace App\Console\Commands;

use App\Events\VendorApprovalSubmitted;
use App\Models\VendorApplication;
use Illuminate\Console\Command;

class TestVendorApprovalEvent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:vendor-approval-event';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Fire a test VendorApprovalSubmitted event to admin channel';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Firing test VendorApprovalSubmitted event...');

        // Get or create a test vendor application
        $application = VendorApplication::firstOrCreate(
            ['user_id' => 1],
            [
                'status' => 'pending',
                'current_step' => 1,
                'completed_step' => 1,
                'has_business_certificate' => false,
                'payment_required' => false,
                'payment_completed' => true,
            ],
        );

        $this->info("Using application ID: {$application->id}");
        $this->info("User: {$application->user->name} ({$application->user->email})");

        // Fire the event
        event(new VendorApprovalSubmitted($application));

        $this->info('✅ Test event fired!');
        $this->info('');
        $this->info('Event data sent to admin channel:');
        $this->table(
            ['Key', 'Value'],
            [
                ['Vendor Application ID', $application->id],
                ['User ID', $application->user_id],
                ['User Name', $application->user->name],
                ['User Email', $application->user->email],
                ['Event Name', 'vendor.approval.submitted'],
                ['Channel', 'private-admin'],
            ],
        );
    }
}
