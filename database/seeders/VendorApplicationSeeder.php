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

        $buckets = [
            [
                'label' => 'Pending (registered)',
                'namePrefix' => 'Pending Vendor',
                'emailPrefix' => 'pending.vendor',
                'role' => 'customer',
                'apply' => fn ($factory) => $factory->registeredVendor()->withRegisteredDocuments()->pending(),
                'extra' => ['completed_step' => 4],
            ],
            [
                'label' => 'Pending (unregistered)',
                'namePrefix' => 'Pending Unregistered Vendor',
                'emailPrefix' => 'pending.unreg',
                'role' => 'customer',
                'apply' => fn ($factory) => $factory->unregisteredVendor()->withUnregisteredDocuments()->pending(),
                'extra' => ['completed_step' => 4],
            ],
            [
                'label' => 'Under Review',
                'namePrefix' => 'Under Review Vendor',
                'emailPrefix' => 'review.vendor',
                'role' => 'customer',
                'apply' => fn ($factory) => $factory->registeredVendor()->withRegisteredDocuments()->underReview(),
                'extra' => ['completed_step' => 4],
            ],
            [
                'label' => 'Approved',
                'namePrefix' => 'Approved Vendor',
                'emailPrefix' => 'approved.vendor',
                'role' => 'vendor',
                'apply' => fn ($factory) => $factory->registeredVendor()->withRegisteredDocuments()->approved(),
                'extra' => ['completed_step' => 4],
            ],
            [
                'label' => 'Rejected',
                'namePrefix' => 'Rejected Vendor',
                'emailPrefix' => 'rejected.vendor',
                'role' => 'customer',
                'apply' => fn ($factory) => $factory->registeredVendor()->withRegisteredDocuments()->rejected(),
                'extra' => [
                    'completed_step' => 4,
                    'rejection_reason' => 'Incomplete business documentation. Please provide a valid business registration certificate.',
                ],
            ],
            [
                'label' => 'In-progress',
                'namePrefix' => 'In Progress Vendor',
                'emailPrefix' => 'inprogress.vendor',
                'role' => 'customer',
                'apply' => fn ($factory) => $factory,
                'extra' => [
                    'status' => VendorApplication::STATUS_PENDING,
                    'current_step' => 2,
                    'completed_step' => 1,
                    'submitted_at' => null,
                ],
            ],
        ];

        foreach ($buckets as $bucket) {
            for ($i = 1; $i <= $maxCount; $i++) {
                $email = "{$bucket['emailPrefix']}{$i}@example.com";

                if (User::where('email', $email)->exists()) {
                    $this->command->info("{$bucket['label']}: {$i}/{$maxCount} skipped (exists)");

                    continue;
                }

                $user = User::factory()->create([
                    'name' => "{$bucket['namePrefix']} {$i}",
                    'email' => $email,
                    'role' => $bucket['role'],
                    'email_verified_at' => now(),
                ]);

                $factory = VendorApplication::factory()->for($user)->withGhanaCard();
                $bucket['apply']($factory)->create($bucket['extra']);

                $this->command->info("{$bucket['label']}: {$i}/{$maxCount} created");
            }
        }

        $this->command->info("Created vendor applications: $maxCount pending, $maxCount under review, $maxCount approved, $maxCount rejected, $maxCount in-progress");
    }
}
