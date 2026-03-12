<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_can_approve_vendor_application(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = User::factory()->create(['role' => 'customer']);

        $application = VendorApplication::factory()
            ->for($applicant)
            ->readyToSubmit()
            ->create([
                'status' => VendorApplication::STATUS_PENDING,
                'submitted_at' => now(),
                'payment_required' => true,
                'payment_completed' => true,
            ]);

        $response = $this->actingAs($admin)
            ->post("/dashboard/vendor-applications/{$application->id}/approve");

        $response->assertRedirect();

        $application->refresh();
        $this->assertEquals(VendorApplication::STATUS_APPROVED, $application->status);
        $this->assertEquals($admin->id, $application->reviewed_by);
        $this->assertNotNull($application->reviewed_at);

        // User should now be a vendor
        $applicant->refresh();
        $this->assertEquals('vendor', $applicant->role);
    }

    public function test_admin_can_reject_vendor_application(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = User::factory()->create(['role' => 'customer']);

        $application = VendorApplication::factory()
            ->for($applicant)
            ->readyToSubmit()
            ->create([
                'status' => VendorApplication::STATUS_PENDING,
                'submitted_at' => now(),
                'payment_required' => true,
                'payment_completed' => true,
            ]);

        $rejectionReason = 'Incomplete documentation provided.';

        $response = $this->actingAs($admin)
            ->post("/dashboard/vendor-applications/{$application->id}/reject", [
                'rejection_reason' => $rejectionReason,
            ]);

        $response->assertRedirect();

        $application->refresh();
        $this->assertEquals(VendorApplication::STATUS_REJECTED, $application->status);
        $this->assertEquals($rejectionReason, $application->rejection_reason);
        $this->assertEquals($admin->id, $application->reviewed_by);
        $this->assertNotNull($application->reviewed_at);

        // User should still be customer
        $applicant->refresh();
        $this->assertEquals('customer', $applicant->role);
    }

    public function test_rejection_requires_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = User::factory()->create(['role' => 'customer']);

        $application = VendorApplication::factory()
            ->for($applicant)
            ->create(['status' => VendorApplication::STATUS_PENDING]);

        $response = $this->actingAs($admin)
            ->post("/dashboard/vendor-applications/{$application->id}/reject", [
                'rejection_reason' => '',
            ]);

        $response->assertSessionHasErrors('rejection_reason');
    }

    public function test_rejection_reason_must_be_at_least_10_characters(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = User::factory()->create(['role' => 'customer']);

        $application = VendorApplication::factory()
            ->for($applicant)
            ->create(['status' => VendorApplication::STATUS_PENDING]);

        $response = $this->actingAs($admin)
            ->post("/dashboard/vendor-applications/{$application->id}/reject", [
                'rejection_reason' => 'Too short',
            ]);

        $response->assertSessionHasErrors('rejection_reason');
    }

    public function test_cannot_approve_already_approved_application(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = User::factory()->create(['role' => 'customer']);

        $application = VendorApplication::factory()
            ->for($applicant)
            ->create(['status' => VendorApplication::STATUS_APPROVED]);

        $response = $this->actingAs($admin)
            ->post("/dashboard/vendor-applications/{$application->id}/approve");

        $response->assertSessionHas('error');
    }

    public function test_cannot_approve_already_rejected_application(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = User::factory()->create(['role' => 'customer']);

        $application = VendorApplication::factory()
            ->for($applicant)
            ->create(['status' => VendorApplication::STATUS_REJECTED]);

        $response = $this->actingAs($admin)
            ->post("/dashboard/vendor-applications/{$application->id}/approve");

        $response->assertSessionHas('error');
    }

    public function test_non_approved_vendor_cannot_create_shop(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        // User has no vendor application
        $response = $this->actingAs($user)
            ->postJson('/api/v1/shops', [
                'name' => 'Test Shop',
                'description' => 'Test Description',
            ]);

        $response->assertStatus(403);
    }

    public function test_non_approved_vendor_cannot_create_product(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/products', [
                'name' => 'Test Product',
                'price' => 100,
            ]);

        $response->assertStatus(403);
    }

    public function test_non_approved_vendor_cannot_create_service(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/services', [
                'name' => 'Test Service',
                'charge_start' => 50,
                'charge_end' => 150,
            ]);

        $response->assertStatus(403);
    }

    public function test_approved_vendor_can_create_shop(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $category = \App\Models\Category::factory()->create();

        VendorApplication::factory()
            ->for($user)
            ->create(['status' => VendorApplication::STATUS_APPROVED]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/shops', [
                'name' => 'Test Shop',
                'category_id' => $category->id,
                'owner_name' => $user->name,
                'description' => 'Test Description',
                'location' => 'Test Location',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_admin_can_view_vendor_applications_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/dashboard/vendor-applications');

        $response->assertStatus(200);
    }

    public function test_admin_can_view_specific_vendor_application(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = User::factory()->create(['role' => 'customer']);

        $application = VendorApplication::factory()
            ->for($applicant)
            ->create(['status' => VendorApplication::STATUS_PENDING]);

        $response = $this->actingAs($admin)
            ->get("/dashboard/vendor-applications/{$application->id}");

        $response->assertStatus(200);
    }

    public function test_customer_cannot_access_vendor_applications_page(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)->get('/dashboard/vendor-applications');

        $response->assertStatus(302); // Redirected due to middleware
    }

    public function test_can_be_reviewed_returns_true_for_complete_application(): void
    {
        $application = VendorApplication::factory()
            ->withGhanaCard()
            ->registeredVendor()
            ->withRegisteredDocuments()
            ->readyToSubmit()
            ->create([
                'status' => VendorApplication::STATUS_PENDING,
                'submitted_at' => now(),
                'payment_required' => true,
                'payment_completed' => true,
            ]);

        $this->assertTrue($application->canBeReviewed());
    }

    public function test_can_be_reviewed_returns_false_when_steps_incomplete(): void
    {
        $application = VendorApplication::factory()
            ->withGhanaCard()
            ->registeredVendor()
            ->create([
                'status' => VendorApplication::STATUS_PENDING,
                'submitted_at' => now(),
                'completed_step' => 2,
                'payment_completed' => true,
            ]);

        $this->assertFalse($application->canBeReviewed());
    }

    public function test_can_be_reviewed_returns_false_when_payment_not_completed(): void
    {
        $application = VendorApplication::factory()
            ->readyToSubmit()
            ->create([
                'status' => VendorApplication::STATUS_PENDING,
                'submitted_at' => now(),
                'payment_required' => true,
                'payment_completed' => false,
            ]);

        $this->assertFalse($application->canBeReviewed());
    }

    public function test_can_be_reviewed_returns_true_when_payment_not_required(): void
    {
        $application = VendorApplication::factory()
            ->readyToSubmit()
            ->create([
                'status' => VendorApplication::STATUS_PENDING,
                'submitted_at' => now(),
                'payment_required' => false,
                'payment_completed' => false,
            ]);

        $this->assertTrue($application->canBeReviewed());
    }

    public function test_can_be_reviewed_returns_false_when_not_submitted(): void
    {
        $application = VendorApplication::factory()
            ->readyToSubmit()
            ->create([
                'status' => VendorApplication::STATUS_PENDING,
                'submitted_at' => null,
                'payment_completed' => true,
            ]);

        $this->assertFalse($application->canBeReviewed());
    }

    public function test_cannot_approve_application_with_incomplete_steps(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = User::factory()->create(['role' => 'customer']);

        $application = VendorApplication::factory()
            ->for($applicant)
            ->create([
                'status' => VendorApplication::STATUS_PENDING,
                'submitted_at' => now(),
                'completed_step' => 2,
                'payment_completed' => true,
            ]);

        $response = $this->actingAs($admin)
            ->post("/dashboard/vendor-applications/{$application->id}/approve");

        $response->assertSessionHas('error');
        $application->refresh();
        $this->assertEquals(VendorApplication::STATUS_PENDING, $application->status);
    }

    public function test_cannot_approve_application_without_payment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = User::factory()->create(['role' => 'customer']);

        $application = VendorApplication::factory()
            ->for($applicant)
            ->readyToSubmit()
            ->create([
                'status' => VendorApplication::STATUS_PENDING,
                'submitted_at' => now(),
                'payment_required' => true,
                'payment_completed' => false,
            ]);

        $response = $this->actingAs($admin)
            ->post("/dashboard/vendor-applications/{$application->id}/approve");

        $response->assertSessionHas('error');
        $application->refresh();
        $this->assertEquals(VendorApplication::STATUS_PENDING, $application->status);
    }

    public function test_cannot_approve_unsubmitted_application(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = User::factory()->create(['role' => 'customer']);

        $application = VendorApplication::factory()
            ->for($applicant)
            ->readyToSubmit()
            ->create([
                'status' => VendorApplication::STATUS_PENDING,
                'submitted_at' => null,
                'payment_completed' => true,
            ]);

        $response = $this->actingAs($admin)
            ->post("/dashboard/vendor-applications/{$application->id}/approve");

        $response->assertSessionHas('error');
        $application->refresh();
        $this->assertEquals(VendorApplication::STATUS_PENDING, $application->status);
    }

    public function test_cannot_reject_application_with_incomplete_steps(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = User::factory()->create(['role' => 'customer']);

        $application = VendorApplication::factory()
            ->for($applicant)
            ->create([
                'status' => VendorApplication::STATUS_PENDING,
                'submitted_at' => now(),
                'completed_step' => 2,
                'payment_completed' => true,
            ]);

        $response = $this->actingAs($admin)
            ->post("/dashboard/vendor-applications/{$application->id}/reject", [
                'rejection_reason' => 'This is a detailed rejection reason.',
            ]);

        $response->assertSessionHas('error');
        $application->refresh();
        $this->assertEquals(VendorApplication::STATUS_PENDING, $application->status);
    }

    public function test_mark_application_as_under_review(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = User::factory()->create(['role' => 'customer']);

        $application = VendorApplication::factory()
            ->for($applicant)
            ->create(['status' => VendorApplication::STATUS_PENDING]);

        $response = $this->actingAs($admin)
            ->post("/dashboard/vendor-applications/{$application->id}/under-review");

        $response->assertRedirect();

        $application->refresh();
        $this->assertEquals(VendorApplication::STATUS_UNDER_REVIEW, $application->status);
    }

    public function test_show_page_includes_payment_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = User::factory()->create(['role' => 'customer']);

        $application = VendorApplication::factory()
            ->for($applicant)
            ->readyToSubmit()
            ->withPaymentCompleted()
            ->pending()
            ->create();

        // Create an onboarding payment record
        \App\Models\VendorOnboardingPayment::factory()
            ->successful()
            ->create([
                'user_id' => $applicant->id,
                'vendor_application_id' => $application->id,
                'amount' => 100.00,
                'currency' => 'GHS',
                'channel' => 'card',
                'card_last4' => '4321',
                'card_bank' => 'Test Bank',
            ]);

        $response = $this->actingAs($admin)
            ->get("/dashboard/vendor-applications/{$application->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('vendor-applications/show')
            ->has('application.payment')
            ->where('application.payment.status', 'success')
            ->where('application.payment.amount', 100)
            ->where('application.payment.currency', 'GHS')
            ->where('application.can_be_reviewed', true)
            ->where('application.payment_completed', true)
        );
    }

    public function test_index_page_includes_payment_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = User::factory()->create(['role' => 'customer']);

        $application = VendorApplication::factory()
            ->for($applicant)
            ->readyToSubmit()
            ->withPaymentCompleted()
            ->pending()
            ->create();

        \App\Models\VendorOnboardingPayment::factory()
            ->successful()
            ->create([
                'user_id' => $applicant->id,
                'vendor_application_id' => $application->id,
                'amount' => 100.00,
            ]);

        $response = $this->actingAs($admin)
            ->get('/dashboard/vendor-applications');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('vendor-applications/index')
            ->has('applications.data', 1)
            ->where('applications.data.0.payment_status', 'success')
            ->where('applications.data.0.payment_completed', true)
        );
    }

    public function test_admin_can_filter_vendor_applications_by_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user1 = User::factory()->create(['role' => 'customer']);
        $user2 = User::factory()->create(['role' => 'customer']);
        $user3 = User::factory()->create(['role' => 'customer']);

        VendorApplication::factory()->for($user1)->create(['status' => VendorApplication::STATUS_PENDING]);
        VendorApplication::factory()->for($user2)->create(['status' => VendorApplication::STATUS_APPROVED]);
        VendorApplication::factory()->for($user3)->create(['status' => VendorApplication::STATUS_REJECTED]);

        $response = $this->actingAs($admin)->get('/dashboard/vendor-applications?status=pending');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('vendor-applications/index')
                ->has('applications.data', 1)
                ->where('filters.status', 'pending')
        );
    }

    public function test_no_status_filter_returns_all_vendor_applications(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user1 = User::factory()->create(['role' => 'customer']);
        $user2 = User::factory()->create(['role' => 'customer']);

        VendorApplication::factory()->for($user1)->create(['status' => VendorApplication::STATUS_PENDING]);
        VendorApplication::factory()->for($user2)->create(['status' => VendorApplication::STATUS_APPROVED]);

        $response = $this->actingAs($admin)->get('/dashboard/vendor-applications');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('vendor-applications/index')
                ->has('applications.data', 2)
                ->where('filters.status', null)
        );
    }

    public function test_show_page_includes_can_be_reviewed_flag(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = User::factory()->create(['role' => 'customer']);

        $application = VendorApplication::factory()
            ->for($applicant)
            ->create([
                'status' => VendorApplication::STATUS_PENDING,
                'completed_step' => 2,
                'submitted_at' => null,
            ]);

        $response = $this->actingAs($admin)
            ->get("/dashboard/vendor-applications/{$application->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('application.can_be_reviewed', false)
        );
    }
}
