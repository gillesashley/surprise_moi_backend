<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminNotificationFeedControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_notifications_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        VendorApplication::factory()->create(['submitted_at' => now()]);

        $this->actingAs($admin)
            ->get('/dashboard/notifications')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('notifications/index')
                ->has('feed.data', 1)
                ->where('filters.categories', [])
            );
    }

    public function test_super_admin_can_view_notifications_page(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->get('/dashboard/notifications')
            ->assertOk();
    }

    public function test_non_admin_is_blocked(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);

        $response = $this->actingAs($vendor)->get('/dashboard/notifications');

        $this->assertNotEquals(200, $response->status());
    }

    public function test_unauthenticated_is_redirected(): void
    {
        $this->get('/dashboard/notifications')->assertRedirect();
    }

    public function test_category_filter_is_passed_through(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        VendorApplication::factory()->create(['submitted_at' => now()]);
        \App\Models\TierUpgradeRequest::factory()->create(['created_at' => now()]);

        $this->actingAs($admin)
            ->get('/dashboard/notifications?categories[]=vendor_onboarding')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.categories', ['vendor_onboarding'])
                ->where('feed.data.0.category', 'vendor_onboarding')
                ->has('feed.data', 1)
            );
    }

    public function test_invalid_category_returns_validation_error(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get('/dashboard/notifications?categories[]=bogus')
            ->assertSessionHasErrors('categories.0');
    }
}
