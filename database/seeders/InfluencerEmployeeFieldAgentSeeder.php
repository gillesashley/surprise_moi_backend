<?php

namespace Database\Seeders;

use App\Models\ReferralCode;
use App\Models\Target;
use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Database\Seeder;

class InfluencerEmployeeFieldAgentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the super admin to assign targets
        $superAdmin = User::where('role', 'super_admin')->first();

        if (! $superAdmin) {
            $this->command->error('Super admin not found. Please run AdminUserSeeder first.');

            return;
        }

        // Create Influencers
        $influencers = User::factory()
            ->influencer()
            ->count(10)
            ->create();

        foreach ($influencers as $influencer) {
            // Each influencer gets a referral code
            ReferralCode::create([
                'influencer_id' => $influencer->id,
                'description' => "Referral code for {$influencer->name}",
                'is_active' => true,
                'registration_bonus' => 50.00,
            ]);
        }

        // Create a deterministic field agent for testing the onboarding flow.
        // This agent always has the same email so credentials survive re-seeding.
        // Configured as a team LEAD so the team-management feature is exercised.
        $primaryAgent = User::updateOrCreate(
            ['email' => 'fieldagent@surprisemoi.test'],
            [
                'name' => 'Test Field Agent',
                'phone' => '0240000010',
                'password' => 'password',
                'role' => 'field_agent',
                'is_team_field_agent' => true,
                'parent_user_id' => null,
                'is_active' => true,
                'must_change_password' => false,
                'location' => 'Accra',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]
        );

        // Seed two team members under the lead so the Team page is populated
        // immediately on first login. One member starts with a default password
        // (must_change_password=false → easy direct login). The second simulates
        // a freshly-provisioned member who must rotate their password on first login.
        $teamMembers = collect([
            [
                'email' => 'fieldagent.member1@surprisemoi.test',
                'name' => 'Member One',
                'phone' => '0240000011',
                'must_change_password' => false,
                'location' => 'Accra',
            ],
            [
                'email' => 'fieldagent.member2@surprisemoi.test',
                'name' => 'Member Two',
                'phone' => '0240000012',
                'must_change_password' => true,
                'location' => 'Kumasi',
            ],
        ])->map(function (array $row) use ($primaryAgent) {
            return User::updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'phone' => $row['phone'],
                    'password' => 'password',
                    'role' => 'field_agent',
                    'is_team_field_agent' => false,
                    'parent_user_id' => $primaryAgent->id,
                    'is_active' => true,
                    'must_change_password' => $row['must_change_password'],
                    'location' => $row['location'],
                    'email_verified_at' => now(),
                    'phone_verified_at' => now(),
                ]
            );
        });

        // Ensure the primary agent has a referral code
        $agentCode = ReferralCode::firstOrCreate(
            ['influencer_id' => $primaryAgent->id],
            [
                'description' => "Referral code for {$primaryAgent->name}",
                'is_active' => true,
                'registration_bonus' => 0,
            ]
        );

        // Seed 2 pending vendor applications tied to this agent's referral code.
        // These represent new vendors the field agent brought on board.
        for ($i = 1; $i <= 2; $i++) {
            $vendorEmail = "onboard.vendor{$i}@example.com";
            $vendorUser = User::updateOrCreate(
                ['email' => $vendorEmail],
                [
                    'name' => "Onboarding Vendor {$i}",
                    'phone' => '024100000'.$i,
                    'password' => 'password',
                    'role' => 'customer',
                    'email_verified_at' => now(),
                ]
            );

            // First vendor is attributed to Member One so the team breakdown
            // shows real data on the lead's Team page. Second vendor is unclaimed.
            $onboardedBy = $i === 1 ? $teamMembers->first()->id : null;

            $existingApplication = $vendorUser->vendorApplications()->first();
            if (! $existingApplication) {
                VendorApplication::factory()
                    ->for($vendorUser)
                    ->withGhanaCard()
                    ->pending()
                    ->create([
                        'completed_step' => 4,
                        'referral_code_id' => $agentCode->id,
                        'referral_code_used' => $agentCode->code,
                        'onboarded_by_user_id' => $onboardedBy,
                        'payment_required' => true,
                        'payment_completed' => true,
                    ]);
            } else {
                // Idempotent re-seed: keep attribution in sync with current
                // team-membership configuration even if the row pre-existed.
                $existingApplication->forceFill([
                    'onboarded_by_user_id' => $onboardedBy,
                ])->save();
            }
        }

        // Create remaining Field Agents (random)
        $fieldAgents = User::factory()
            ->fieldAgent()
            ->count(14)
            ->create();

        // Include the primary agent in the collection for target assignment
        $allAgents = collect([$primaryAgent])->merge($fieldAgents);

        foreach ($allAgents as $agent) {
            // Skip if this agent already has an active target this month
            if ($agent->targets()->where('status', Target::STATUS_ACTIVE)->where('start_date', now()->startOfMonth())->exists()) {
                continue;
            }

            Target::create([
                'user_id' => $agent->id,
                'assigned_by' => $superAdmin->id,
                'user_role' => 'field_agent',
                'target_type' => Target::TYPE_VENDOR_SIGNUPS,
                'target_value' => 10,
                'current_value' => 0,
                'bonus_amount' => 500.00,
                'overachievement_rate' => 10.00,
                'period_type' => 'monthly',
                'start_date' => now()->startOfMonth(),
                'end_date' => now()->endOfMonth(),
                'status' => Target::STATUS_ACTIVE,
                'notes' => 'Monthly vendor signup target',
            ]);
        }

        // Create Employees
        $employees = User::factory()
            ->employee()
            ->count(10)
            ->create();

        foreach ($employees as $employee) {
            // Assign targets to employees
            Target::create([
                'user_id' => $employee->id,
                'assigned_by' => $superAdmin->id,
                'user_role' => 'employee',
                'target_type' => Target::TYPE_REVENUE_GENERATED,
                'target_value' => 10000.00,
                'current_value' => 0,
                'bonus_amount' => 1000.00,
                'overachievement_rate' => 15.00,
                'period_type' => 'quarterly',
                'start_date' => now()->startOfQuarter(),
                'end_date' => now()->endOfQuarter(),
                'status' => Target::STATUS_ACTIVE,
                'notes' => 'Quarterly revenue generation target',
            ]);
        }

        $this->command->info('Created 5 Influencers with referral codes');
        $this->command->info('Created 3 Field Agents with vendor signup targets');
        $this->command->info('Created 3 Employees with revenue targets');

        $this->command->newLine();
        $this->command->info('==== LOGIN CREDENTIALS ====');
        $this->command->info('Default password for all users: password');
        $this->command->newLine();

        $this->command->info('--- INFLUENCERS ---');
        foreach ($influencers as $influencer) {
            $this->command->info("Email: {$influencer->email}");
        }
        $this->command->newLine();

        $this->command->info('--- FIELD AGENTS ---');
        $this->command->info("Email: {$primaryAgent->email} (LEAD — manages 2 team members; 2 pending vendor onboardings, 1 attributed to Member One)");
        foreach ($teamMembers as $member) {
            $must = $member->must_change_password ? ' [must change password on first login]' : '';
            $this->command->info("Email: {$member->email} (team member of {$primaryAgent->email}){$must}");
        }
        foreach ($fieldAgents as $agent) {
            $this->command->info("Email: {$agent->email}");
        }
        $this->command->newLine();

        $this->command->info('--- EMPLOYEES ---');
        foreach ($employees as $employee) {
            $this->command->info("Email: {$employee->email}");
        }
    }
}
