<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\TierUpgradeRequest;
use App\Models\User;
use App\Notifications\TierUpgradeApprovedNotification;
use App\Notifications\TierUpgradeRejectedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminTierUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->vendor = User::factory()->vendor()->create(['vendor_tier' => 2]);
    }

    public function test_admin_can_list_upgrade_requests(): void
    {
        TierUpgradeRequest::factory()->pendingReview()->count(3)->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/vendor/upgrade-tier');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 3);
    }

    public function test_admin_can_filter_by_status(): void
    {
        TierUpgradeRequest::factory()->pendingReview()->count(2)->create();
        TierUpgradeRequest::factory()->approved()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/vendor/upgrade-tier?status=pending_review');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_admin_can_show_single_request(): void
    {
        $request = TierUpgradeRequest::factory()->pendingReview()->create([
            'vendor_id' => $this->vendor->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/vendor/upgrade-tier/{$request->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $request->id)
            ->assertJsonPath('data.status', 'pending_review');
    }

    public function test_admin_can_approve_upgrade_request(): void
    {
        Notification::fake();

        $request = TierUpgradeRequest::factory()->pendingReview()->create([
            'vendor_id' => $this->vendor->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/vendor/upgrade-tier/{$request->id}/approve");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        $this->vendor->refresh();
        $this->assertEquals(1, $this->vendor->vendor_tier);

        $this->assertDatabaseHas('tier_upgrade_requests', [
            'id' => $request->id,
            'status' => TierUpgradeRequest::STATUS_APPROVED,
            'admin_id' => $this->admin->id,
        ]);

        Notification::assertSentTo($this->vendor, TierUpgradeApprovedNotification::class);
    }

    public function test_approve_updates_vendor_tier_atomically(): void
    {
        Notification::fake();

        $request = TierUpgradeRequest::factory()->pendingReview()->create([
            'vendor_id' => $this->vendor->id,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/vendor/upgrade-tier/{$request->id}/approve");

        $this->assertDatabaseHas('users', [
            'id' => $this->vendor->id,
            'vendor_tier' => 1,
        ]);

        $this->assertDatabaseHas('tier_upgrade_requests', [
            'id' => $request->id,
            'status' => TierUpgradeRequest::STATUS_APPROVED,
        ]);
    }

    public function test_admin_can_reject_upgrade_request(): void
    {
        Notification::fake();

        $request = TierUpgradeRequest::factory()->pendingReview()->create([
            'vendor_id' => $this->vendor->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/vendor/upgrade-tier/{$request->id}/reject", [
                'admin_notes' => 'Document is illegible',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('tier_upgrade_requests', [
            'id' => $request->id,
            'status' => TierUpgradeRequest::STATUS_REJECTED,
            'admin_notes' => 'Document is illegible',
            'admin_id' => $this->admin->id,
        ]);

        // Vendor tier should NOT change
        $this->vendor->refresh();
        $this->assertEquals(2, $this->vendor->vendor_tier);

        Notification::assertSentTo($this->vendor, TierUpgradeRejectedNotification::class);
    }

    public function test_reject_requires_admin_notes(): void
    {
        $request = TierUpgradeRequest::factory()->pendingReview()->create([
            'vendor_id' => $this->vendor->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/vendor/upgrade-tier/{$request->id}/reject", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('admin_notes');
    }

    public function test_cannot_approve_non_pending_review_request(): void
    {
        $request = TierUpgradeRequest::factory()->pendingDocument()->create([
            'vendor_id' => $this->vendor->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/vendor/upgrade-tier/{$request->id}/approve");

        $response->assertStatus(422);
    }

    public function test_cannot_reject_non_pending_review_request(): void
    {
        $request = TierUpgradeRequest::factory()->approved()->create([
            'vendor_id' => $this->vendor->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/vendor/upgrade-tier/{$request->id}/reject", [
                'admin_notes' => 'Some reason',
            ]);

        $response->assertStatus(422);
    }

    public function test_non_admin_cannot_access_admin_endpoints(): void
    {
        $request = TierUpgradeRequest::factory()->pendingReview()->create([
            'vendor_id' => $this->vendor->id,
        ]);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->getJson('/api/v1/admin/vendor/upgrade-tier');

        $response->assertStatus(403);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson("/api/v1/admin/vendor/upgrade-tier/{$request->id}/approve");

        $response->assertStatus(403);
    }
}
