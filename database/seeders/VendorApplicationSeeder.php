<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Database\Seeder;

class VendorApplicationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create vendor applicants with different statuses
        $maxCount = 30;

        // 1. Pending applications (ready for review) - 3 registered vendors
        for ($i = 1; $i <= $maxCount; $i++) {
            $user = User::factory()->create([
                'name' => "Pending Vendor {$i}",
                'email' => "pending.vendor{$i}@example.com",
                'role' => 'customer',
                'email_verified_at' => now(),
            ]);

            VendorApplication::factory()
                ->for($user)
                ->withGhanaCard()
                ->registeredVendor()
                ->withRegisteredDocuments()
                ->pending()
                ->create(['completed_step' => 4]);
            $this->command->info("{$i} Pending (registered): {$i}/{$maxCount} created");
        }

        // 2. Pending applications - 2 unregistered vendors
        for ($i = 1; $i <= $maxCount; $i++) {
            $user = User::factory()->create([
                'name' => "Pending Unregistered Vendor {$i}",
                'email' => "pending.unreg{$i}@example.com",
                'role' => 'customer',
                'email_verified_at' => now(),
            ]);

            VendorApplication::factory()
                ->for($user)
                ->withGhanaCard()
                ->unregisteredVendor()
                ->withUnregisteredDocuments()
                ->pending()
                ->create(['completed_step' => 4]);
            $this->command->info("{$i} Pending (unregistered): {$i}/{$maxCount} created");
        }

        // 3. Under review applications - 2 applications
        for ($i = 1; $i <= $maxCount; $i++) {
            $user = User::factory()->create([
                'name' => "Under Review Vendor {$i}",
                'email' => "review.vendor{$i}@example.com",
                'role' => 'customer',
                'email_verified_at' => now(),
            ]);

            VendorApplication::factory()
                ->for($user)
                ->withGhanaCard()
                ->registeredVendor()
                ->withRegisteredDocuments()
                ->underReview()
                ->create(['completed_step' => 4]);
            $this->command->info("{$i} Under Review: {$i}/{$maxCount} created");
        }

        // 4. Approved vendors - 3 vendors (these should have shops, products, services)
        $vendorAvatars = [
            'https://images.unsplash.com/photo-143876168$maxCount33-6461ffad8d80?w=400&h=400&fit=crop',
            'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&h=400&fit=crop',
            'https://images.unsplash.com/photo-1494790$maxCount8377-be9c29b29330?w=400&h=400&fit=crop',
        ];
        for ($i = 1; $i <= $maxCount; $i++) {
            $user = User::factory()->create([
                'name' => "Approved Vendor {$i}",
                'email' => "approved.vendor{$i}@example.com",
                'role' => 'vendor', // Already vendor
                'email_verified_at' => now(),
                // 'avatar' => $vendorAvatars[$i - 1],
            ]);

            VendorApplication::factory()
                ->for($user)
                ->withGhanaCard()
                ->registeredVendor()
                ->withRegisteredDocuments()
                ->approved()
                ->create(['completed_step' => 4]);
            $this->command->info("{$i} Approved: {$i}/{$maxCount} created");
        }

        // 5. Rejected appl$maxCountcations - 2 applications
        for ($i = 1; $i <= $maxCount; $i++) {
            $user = User::factory()->create([
                'name' => "Rejected Vendor {$i}",
                'email' => "rejected.vendor{$i}@example.com",
                'role' => 'customer',
                'email_verified_at' => now(),
            ]);

            VendorApplication::factory()
                ->for($user)
                ->withGhanaCard()
                ->registeredVendor()
                ->withRegisteredDocuments()
                ->rejected()
                ->create([
                    'completed_step' => 4,
                    'rejection_reason' => 'Incomplete business documentation. Please provide a valid business registration certificate.',
                ]);
            $this->command->info("{$i} Rejected: {$i}/{$maxCount} created");
        }

        // 6. In-progress applications (not yet submitted) - 2 applications
        for ($i = 1; $i <= $maxCount; $i++) {
            $user = User::factory()->create([
                'name' => "In Progress Vendor {$i}",
                'email' => "inprogress.vendor{$i}@example.com",
                'role' => 'customer',
                'email_verified_at' => now(),
            ]);

            VendorApplication::factory()
                ->for($user)
                ->withGhanaCard()
                ->create([
                    'status' => VendorApplication::STATUS_PENDING,
                    'current_step' => 2,
                    'completed_step' => 1,
                    'submitted_at' => null, // Not submitted yet
                ]);
            $this->command->info("{$i} In-progress: {$i}/{$maxCount} created");
        }

        $this->command->info("Created vendor applications: $maxCount pending, $maxCount under review, $maxCount approved, $maxCount rejected, $maxCount in-progress");
    }
}
