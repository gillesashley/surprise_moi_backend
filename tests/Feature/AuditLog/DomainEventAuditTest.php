<?php

namespace Tests\Feature\AuditLog;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainEventAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_application_approve_logs_domain_event(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $app = VendorApplication::factory()->create([
            'status' => VendorApplication::STATUS_PENDING,
            'completed_step' => 4,
            'submitted_at' => now(),
            'payment_required' => true,
            'payment_completed' => true,
            'payment_completed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession(['user_management.verified_at' => time()])
            ->post(route('vendor-applications.approve', $app));

        $this->assertDatabaseHas('activity_log', [
            'event' => 'vendor_application.approved',
            'subject_type' => VendorApplication::class,
            'subject_id' => $app->id,
            'causer_id' => $admin->id,
        ]);

        $row = ActivityLog::where('event', 'vendor_application.approved')->first();
        $this->assertSame('critical', $row->properties['retention_class']);
    }

    public function test_field_agent_application_approve_logs_domain_event(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $app = \App\Models\FieldAgentApplication::factory()->pending()->create();

        $this->actingAs($admin)
            ->post("/dashboard/field-agent-applications/{$app->id}/approve");

        $this->assertDatabaseHas('activity_log', [
            'event' => 'field_agent_application.approved',
            'subject_type' => \App\Models\FieldAgentApplication::class,
            'subject_id' => $app->id,
            'causer_id' => $admin->id,
        ]);
    }

    public function test_field_agent_application_reject_logs_domain_event_with_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $app = \App\Models\FieldAgentApplication::factory()->pending()->create();

        $this->actingAs($admin)
            ->post("/dashboard/field-agent-applications/{$app->id}/reject", [
                'rejection_reason' => 'Docs unclear — please resubmit',
            ]);

        $row = ActivityLog::where('event', 'field_agent_application.rejected')->first();
        $this->assertNotNull($row);
        $this->assertSame('Docs unclear — please resubmit', $row->properties['extra']['reason']);
    }

    public function test_vendor_application_reject_logs_domain_event_with_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $app = VendorApplication::factory()->create([
            'status' => VendorApplication::STATUS_PENDING,
            'completed_step' => 4,
            'submitted_at' => now(),
            'payment_required' => true,
            'payment_completed' => true,
            'payment_completed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession(['user_management.verified_at' => time()])
            ->post(route('vendor-applications.reject', $app), [
                'rejection_reason' => 'Documents unclear, please resubmit',
            ]);

        $row = ActivityLog::where('event', 'vendor_application.rejected')->first();
        $this->assertNotNull($row);
        $this->assertSame('Documents unclear, please resubmit', $row->properties['extra']['reason']);
        $this->assertSame('critical', $row->properties['retention_class']);
    }

    public function test_admin_payout_approve_logs_domain_event(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $payout = \App\Models\PayoutRequest::factory()->pending()->create();

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/payouts/{$payout->id}/approve");

        $this->assertDatabaseHas('activity_log', [
            'event' => 'payout.approved',
            'subject_type' => \App\Models\PayoutRequest::class,
            'subject_id' => $payout->id,
            'causer_id' => $admin->id,
        ]);

        $row = ActivityLog::where('event', 'payout.approved')->first();
        $this->assertSame('critical', $row->properties['retention_class']);
    }

    public function test_user_role_change_logs_domain_event(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $target = User::factory()->create(['role' => 'customer']);

        $this->actingAs($superAdmin)
            ->withSession(['user_management.verified_at' => time()])
            ->put("/dashboard/users/{$target->id}", [
                'name' => $target->name,
                'email' => $target->email,
                'role' => 'admin',
            ]);

        $row = ActivityLog::where('event', 'user.role_changed')->first();
        $this->assertNotNull($row);
        $this->assertSame('customer', $row->properties['extra']['old_role']);
        $this->assertSame('admin', $row->properties['extra']['new_role']);
    }

    public function test_settings_update_logs_domain_event(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->post('/settings/vendor-onboarding', [
                'vendor_tier1_onboarding_fee' => 50.00,
                'vendor_tier2_onboarding_fee' => 30.00,
                'vendor_tier1_commission_rate' => 12.50,
                'vendor_tier2_commission_rate' => 8.00,
                'referral_bonus_customer_pct' => 5.00,
                'referral_bonus_vendor_pct' => 7.00,
                'referral_bonus_influencer_pct' => 10.00,
                'referral_bonus_field_agent_pct' => 6.00,
                'referral_bonus_employee_pct' => 4.00,
            ]);

        $this->assertDatabaseHas('activity_log', [
            'event' => 'settings.updated',
            'causer_id' => $admin->id,
        ]);
    }
}
