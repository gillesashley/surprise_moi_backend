<?php

namespace Tests\Feature\Services;

use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorOnboardingPayment;
use App\Services\AdminNotificationFeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNotificationFeedServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_application_submitted_then_approved_yields_two_rows(): void
    {
        $vendor = User::factory()->create(['name' => 'Akwasi Mensah']);
        $application = VendorApplication::factory()
            ->for($vendor)
            ->create([
                'submitted_at' => now()->subHours(2),
                'status' => VendorApplication::STATUS_APPROVED,
                'updated_at' => now()->subMinutes(10),
            ]);

        $service = app(AdminNotificationFeedService::class);
        $page = $service->feed(categories: [], perPage: 50, page: 1);

        $rows = $page->items();

        $this->assertCount(2, $rows);

        $approved = $rows[0];
        $submitted = $rows[1];

        $this->assertSame('vendor_onboarding', $approved['category']);
        $this->assertSame('approved', $approved['type']);
        $this->assertSame("vendor_application:{$application->id}:approved", $approved['id']);
        $this->assertSame($vendor->id, $approved['actor']['id']);
        $this->assertSame('Akwasi Mensah', $approved['actor']['name']);
        $this->assertSame($application->id, $approved['subject']['id']);
        $this->assertSame('vendor_application', $approved['subject']['type']);
        $this->assertStringContainsString('Akwasi Mensah', $approved['subject']['label']);
        $this->assertSame("/dashboard/vendor-applications/{$application->id}", $approved['action_url']);

        $this->assertSame('submitted', $submitted['type']);
        $this->assertSame("vendor_application:{$application->id}:submitted", $submitted['id']);
    }

    public function test_paid_event_uses_successful_payment_paid_at(): void
    {
        $vendor = User::factory()->create();
        $application = VendorApplication::factory()->for($vendor)->create([
            'submitted_at' => null,
            'status' => VendorApplication::STATUS_PENDING,
        ]);

        VendorOnboardingPayment::factory()->create([
            'vendor_application_id' => $application->id,
            'status' => VendorOnboardingPayment::STATUS_SUCCESS,
            'paid_at' => now()->subHour(),
        ]);

        VendorOnboardingPayment::factory()->create([
            'vendor_application_id' => $application->id,
            'status' => VendorOnboardingPayment::STATUS_FAILED,
            'paid_at' => null,
        ]);

        $page = app(AdminNotificationFeedService::class)->feed([], 50, 1);
        $types = collect($page->items())->pluck('type')->all();

        $this->assertContains('paid', $types);
        $this->assertCount(1, array_filter($types, fn ($t) => $t === 'paid'));
    }

    public function test_flag_lifecycle_yields_three_rows(): void
    {
        $application = VendorApplication::factory()->create([
            'submitted_at' => null,
            'status' => VendorApplication::STATUS_FLAGGED,
            'flagged_at' => now()->subDays(3),
            'flag_reminder_sent_at' => now()->subDays(2),
            'flag_expired_alert_sent_at' => now()->subDay(),
        ]);

        $rows = app(AdminNotificationFeedService::class)->feed([], 50, 1)->items();
        $types = collect($rows)->pluck('type')->all();

        $this->assertContains('flagged', $types);
        $this->assertContains('flag_reminded', $types);
        $this->assertContains('flag_expired', $types);
    }

    public function test_empty_table_returns_empty_paginator(): void
    {
        $page = app(AdminNotificationFeedService::class)->feed([], 50, 1);

        $this->assertCount(0, $page->items());
        $this->assertSame(0, $page->total());
    }

    public function test_tier_upgrade_submitted_paid_rejected_yields_three_rows(): void
    {
        $vendor = User::factory()->create(['name' => 'Ama Boateng']);
        $request = \App\Models\TierUpgradeRequest::factory()->create([
            'vendor_id' => $vendor->id,
            'status' => \App\Models\TierUpgradeRequest::STATUS_REJECTED,
            'created_at' => now()->subDays(5),
            'payment_verified_at' => now()->subDays(4),
            'reviewed_at' => now()->subDays(2),
        ]);

        $rows = app(AdminNotificationFeedService::class)->feed([], 50, 1)->items();
        $types = collect($rows)
            ->where('category', 'tier_upgrade')
            ->pluck('type')
            ->all();

        $this->assertEqualsCanonicalizing(['submitted', 'paid', 'rejected'], $types);
        $first = collect($rows)->firstWhere('category', 'tier_upgrade');
        $this->assertSame($vendor->id, $first['actor']['id']);
        $this->assertSame('tier_upgrade_request', $first['subject']['type']);
        $this->assertSame($request->id, $first['subject']['id']);
        $this->assertSame("/dashboard/tier-upgrades/{$request->id}", $first['action_url']);
    }

    public function test_tier_upgrade_pending_review_does_not_emit_terminal_rows(): void
    {
        \App\Models\TierUpgradeRequest::factory()->create([
            'status' => \App\Models\TierUpgradeRequest::STATUS_PENDING_REVIEW,
            'created_at' => now(),
            'payment_verified_at' => now(),
            'reviewed_at' => null,
        ]);

        $types = collect(app(AdminNotificationFeedService::class)->feed([], 50, 1)->items())
            ->where('category', 'tier_upgrade')
            ->pluck('type')
            ->all();

        $this->assertNotContains('approved', $types);
        $this->assertNotContains('rejected', $types);
    }
}
