<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorApplicationFlagTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_status_flagged_constant_is_defined(): void
    {
        $this->assertSame('flagged', VendorApplication::STATUS_FLAGGED);
    }

    public function test_get_statuses_returns_lifecycle_ordered_array(): void
    {
        // Order matters: the admin status filter dropdown iterates over this array.
        $this->assertSame(
            ['pending', 'under_review', 'flagged', 'approved', 'rejected'],
            VendorApplication::getStatuses(),
        );
    }

    public function test_flagged_scope_returns_only_flagged_applications(): void
    {
        $flagged = VendorApplication::factory()->create(['status' => VendorApplication::STATUS_FLAGGED]);
        VendorApplication::factory()->create(['status' => VendorApplication::STATUS_PENDING]);

        $results = VendorApplication::query()->flagged()->get();

        $this->assertCount(1, $results);
        $this->assertSame($flagged->id, $results->first()->id);
    }

    public function test_flagger_relationship_resolves_to_flagged_by_user_not_application_owner(): void
    {
        $owner = User::factory()->create();
        $flagger = User::factory()->create();
        $app = VendorApplication::factory()->create([
            'user_id' => $owner->id,
            'flagged_by' => $flagger->id,
        ]);

        $this->assertSame($flagger->id, $app->flagger->id);
        $this->assertNotSame($owner->id, $app->flagger->id);
    }

    public function test_factory_flagged_state_creates_flagged_application(): void
    {
        $app = VendorApplication::factory()->flagged()->create();

        $this->assertSame(VendorApplication::STATUS_FLAGGED, $app->status);
        $this->assertNotNull($app->flagged_at);
        $this->assertNotNull($app->flag_reason);
        $this->assertInstanceOf(User::class, $app->flagger);
        $this->assertNotNull($app->grace_period_ends_at);
        $this->assertTrue($app->grace_period_ends_at->isFuture());
    }

    public function test_flagged_application_is_editable(): void
    {
        $app = VendorApplication::factory()->flagged()->create();

        $this->assertTrue($app->isEditable());
    }

    public function test_rejected_application_is_editable(): void
    {
        // Pin existing behavior so a future narrowing of the in_array check
        // can't silently break the rejected resubmit path.
        $app = VendorApplication::factory()->rejected()->create();

        $this->assertTrue($app->isEditable());
    }

    public function test_pending_unsubmitted_application_is_still_editable(): void
    {
        $app = VendorApplication::factory()->create([
            'status' => VendorApplication::STATUS_PENDING,
            'submitted_at' => null,
        ]);

        $this->assertTrue($app->isEditable());
    }

    public function test_pending_submitted_application_is_not_editable(): void
    {
        $app = VendorApplication::factory()->create([
            'status' => VendorApplication::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        $this->assertFalse($app->isEditable());
    }

    public function test_flagged_application_can_be_resubmitted(): void
    {
        \Notification::fake();
        \Event::fake();

        $user = User::factory()->create();
        $app = VendorApplication::factory()
            ->for($user)
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->readyToSubmit()
            ->withPaymentCompleted()
            ->flagged()
            ->create();

        $originalSubmittedAt = $app->submitted_at;

        $result = $app->submit();

        $this->assertTrue($result);
        $fresh = $app->fresh();
        $this->assertSame(VendorApplication::STATUS_UNDER_REVIEW, $fresh->status);
        $this->assertTrue(
            $fresh->submitted_at->isAfter($originalSubmittedAt),
            'submitted_at should be refreshed on resubmit, not retained from the first submission.',
        );
    }

    public function test_pending_first_submission_does_not_change_status(): void
    {
        \Notification::fake();
        \Event::fake();

        $user = User::factory()->create();
        $app = VendorApplication::factory()
            ->for($user)
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->readyToSubmit()
            ->withPaymentCompleted()
            ->create([
                'status' => VendorApplication::STATUS_PENDING,
                'submitted_at' => null,
            ]);

        $result = $app->submit();

        $this->assertTrue($result);
        $this->assertSame(VendorApplication::STATUS_PENDING, $app->fresh()->status);
        $this->assertNotNull($app->fresh()->submitted_at);
    }

    public function test_flag_method_sets_status_and_columns(): void
    {
        \Notification::fake();
        \Event::fake();

        \App\Models\Setting::set('vendor_application_grace_period_days', '7', 'number');

        $reviewer = User::factory()->create();
        $app = VendorApplication::factory()->underReview()->create();

        $result = $app->flag($reviewer->id, 'Ghana card back is unreadable');

        $this->assertTrue($result);
        $fresh = $app->fresh();
        $this->assertSame(VendorApplication::STATUS_FLAGGED, $fresh->status);
        $this->assertSame('Ghana card back is unreadable', $fresh->flag_reason);
        $this->assertSame($reviewer->id, $fresh->flagged_by);
        $this->assertNotNull($fresh->flagged_at);
        $this->assertTrue($fresh->grace_period_ends_at->isAfter(now()->addDays(6)));
        $this->assertNull($fresh->flag_reminder_sent_at);
        $this->assertNull($fresh->flag_expired_alert_sent_at);
    }

    public function test_flag_method_dispatches_event_and_notification(): void
    {
        \Notification::fake();
        \Event::fake();

        $reviewer = User::factory()->create();
        $app = VendorApplication::factory()->underReview()->create();

        $app->flag($reviewer->id, 'Ghana card back is unreadable');

        \Event::assertDispatched(\App\Events\VendorFlagged::class);
        \Notification::assertSentTo(
            $app->user,
            \App\Notifications\VendorFlaggedNotification::class
        );
    }

    public function test_flag_method_clears_previous_reminder_stamps_on_re_flag(): void
    {
        // The model method has no status guard — it can be invoked on an
        // already-flagged application. This test proves the stamps reset and
        // the deadline is recalculated so the scheduled command treats the row
        // as a fresh flag against the new deadline.
        \Notification::fake();
        \Event::fake();

        $reviewer = User::factory()->create();
        $app = VendorApplication::factory()->flagged()->create([
            'flag_reminder_sent_at' => now()->subDay(),
            'flag_expired_alert_sent_at' => now()->subDay(),
        ]);

        $app->flag($reviewer->id, 'Still missing TIN document');

        $fresh = $app->fresh();
        $this->assertNull($fresh->flag_reminder_sent_at);
        $this->assertNull($fresh->flag_expired_alert_sent_at);
        $this->assertTrue(
            $fresh->grace_period_ends_at->isAfter(now()->addDays(6)),
            'grace_period_ends_at should be recalculated from current time on re-flag.',
        );
    }

    public function test_user_admins_scope_returns_admin_and_super_admin(): void
    {
        User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'super_admin']);
        User::factory()->create(['role' => 'vendor']);
        User::factory()->create(['role' => 'customer']);

        $admins = User::admins()->get();

        $this->assertCount(2, $admins);
        $this->assertContains('admin', $admins->pluck('role')->all());
        $this->assertContains('super_admin', $admins->pluck('role')->all());
    }

    public function test_admin_can_flag_a_pending_application(): void
    {
        \Notification::fake();
        \Event::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $app = VendorApplication::factory()
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->readyToSubmit()
            ->withPaymentCompleted()
            ->pending()
            ->create();

        $response = $this->actingAs($admin)
            ->withSession(['user_management.verified_at' => time()])
            ->post("/dashboard/vendor-applications/{$app->id}/flag", [
                'flag_reason' => 'Ghana card back image is unreadable, please re-upload.',
            ]);

        $response->assertRedirect();
        $this->assertSame(VendorApplication::STATUS_FLAGGED, $app->fresh()->status);
        $this->assertSame($admin->id, $app->fresh()->flagged_by);
    }

    public function test_admin_can_flag_an_under_review_application(): void
    {
        \Notification::fake();
        \Event::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $app = VendorApplication::factory()
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->readyToSubmit()
            ->withPaymentCompleted()
            ->underReview()
            ->create();

        $response = $this->actingAs($admin)
            ->withSession(['user_management.verified_at' => time()])
            ->post("/dashboard/vendor-applications/{$app->id}/flag", [
                'flag_reason' => 'Need a clearer selfie photo, current one is blurry.',
            ]);

        $response->assertRedirect();
        $this->assertSame(VendorApplication::STATUS_FLAGGED, $app->fresh()->status);
    }

    public function test_admin_cannot_flag_already_approved_application(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $app = VendorApplication::factory()
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->readyToSubmit()
            ->withPaymentCompleted()
            ->approved()
            ->create();

        $response = $this->actingAs($admin)
            ->withSession(['user_management.verified_at' => time()])
            ->from("/dashboard/vendor-applications/{$app->id}")
            ->post("/dashboard/vendor-applications/{$app->id}/flag", [
                'flag_reason' => 'Ghana card back is unreadable, please re-upload it.',
            ]);

        $response->assertRedirect("/dashboard/vendor-applications/{$app->id}");
        $response->assertSessionHas('error');
        $this->assertSame(VendorApplication::STATUS_APPROVED, $app->fresh()->status);
    }

    public function test_flag_reason_is_required(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $app = VendorApplication::factory()
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->readyToSubmit()
            ->withPaymentCompleted()
            ->pending()
            ->create();

        $response = $this->actingAs($admin)
            ->withSession(['user_management.verified_at' => time()])
            ->post("/dashboard/vendor-applications/{$app->id}/flag", []);

        $response->assertSessionHasErrors('flag_reason');
    }

    public function test_flag_reason_must_be_at_least_10_characters(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $app = VendorApplication::factory()
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->readyToSubmit()
            ->withPaymentCompleted()
            ->pending()
            ->create();

        $response = $this->actingAs($admin)
            ->withSession(['user_management.verified_at' => time()])
            ->post("/dashboard/vendor-applications/{$app->id}/flag", [
                'flag_reason' => 'too short',
            ]);

        $response->assertSessionHasErrors('flag_reason');
    }

    public function test_non_admin_cannot_flag(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $app = VendorApplication::factory()
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->readyToSubmit()
            ->withPaymentCompleted()
            ->pending()
            ->create();

        $response = $this->actingAs($vendor)
            ->withSession(['user_management.verified_at' => time()])
            ->post("/dashboard/vendor-applications/{$app->id}/flag", [
                'flag_reason' => 'attempting to flag from a vendor account',
            ]);

        // Vendor users have no dashboard access; the EnsureDashboardAccess
        // middleware redirects them to login before reaching the controller.
        $response->assertRedirect();
        $this->assertSame(VendorApplication::STATUS_PENDING, $app->fresh()->status);
    }

    public function test_flagged_application_can_be_approved_by_admin(): void
    {
        \Notification::fake();
        \Event::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $app = VendorApplication::factory()
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->readyToSubmit()
            ->withPaymentCompleted()
            ->flagged()
            ->create();

        $response = $this->actingAs($admin)
            ->withSession(['user_management.verified_at' => time()])
            ->post("/dashboard/vendor-applications/{$app->id}/approve");

        $response->assertRedirect();
        $this->assertSame(VendorApplication::STATUS_APPROVED, $app->fresh()->status);
    }

    public function test_flagged_application_can_be_rejected_by_admin(): void
    {
        \Notification::fake();
        \Event::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $app = VendorApplication::factory()
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->readyToSubmit()
            ->withPaymentCompleted()
            ->flagged()
            ->create();

        $response = $this->actingAs($admin)
            ->withSession(['user_management.verified_at' => time()])
            ->post("/dashboard/vendor-applications/{$app->id}/reject", [
                'rejection_reason' => 'No response within grace period and details still missing.',
            ]);

        $response->assertRedirect();
        $fresh = $app->fresh();
        $this->assertSame(VendorApplication::STATUS_REJECTED, $fresh->status);
        $this->assertNotNull($fresh->flag_reason); // preserved
        $this->assertNotNull($fresh->rejection_reason);
    }

    public function test_admin_can_re_flag_an_already_flagged_application(): void
    {
        \Notification::fake();
        \Event::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $app = VendorApplication::factory()
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->readyToSubmit()
            ->withPaymentCompleted()
            ->flagged()
            ->create([
                'flag_reminder_sent_at' => now()->subDay(),
            ]);

        $response = $this->actingAs($admin)
            ->withSession(['user_management.verified_at' => time()])
            ->post("/dashboard/vendor-applications/{$app->id}/flag", [
                'flag_reason' => 'Updated reason — still need a clearer TIN document.',
            ]);

        $response->assertRedirect();
        $fresh = $app->fresh();
        $this->assertSame(VendorApplication::STATUS_FLAGGED, $fresh->status);
        $this->assertSame('Updated reason — still need a clearer TIN document.', $fresh->flag_reason);
        $this->assertNull($fresh->flag_reminder_sent_at); // stamp cleared so reminder fires again
    }
}
