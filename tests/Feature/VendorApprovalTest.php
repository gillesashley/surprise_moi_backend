<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_vendor_application(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = User::factory()->create(['role' => 'customer']);

        $application = VendorApplication::factory()
            ->for($applicant)
            ->create(['status' => VendorApplication::STATUS_PENDING]);

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
            ->create(['status' => VendorApplication::STATUS_PENDING]);

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
}
