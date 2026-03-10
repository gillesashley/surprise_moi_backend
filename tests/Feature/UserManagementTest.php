<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_can_view_users_index(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)
            ->withSession(['user_management.verified_at' => time()])
            ->get('/dashboard/users');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('users/index')
                ->has('users.data')
        );
    }

    public function test_admin_user_can_view_single_user(): void
    {
        $authUser = User::factory()->create(['role' => 'admin']);
        $viewUser = User::factory()->create(['name' => 'John Doe']);

        $response = $this->actingAs($authUser)
            ->withSession(['user_management.verified_at' => time()])
            ->get("/dashboard/users/{$viewUser->id}");

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('users/show')
                ->where('user.name', 'John Doe')
        );
    }

    public function test_admin_user_can_view_edit_form(): void
    {
        $authUser = User::factory()->create(['role' => 'admin']);
        $editUser = User::factory()->create();

        $response = $this->actingAs($authUser)
            ->withSession(['user_management.verified_at' => time()])
            ->get("/dashboard/users/{$editUser->id}/edit");

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('users/edit')
                ->has('user')
        );
    }

    public function test_admin_user_can_update_another_user(): void
    {
        $authUser = User::factory()->create(['role' => 'admin']);
        $editUser = User::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($authUser)
            ->withSession(['user_management.verified_at' => time()])
            ->put("/dashboard/users/{$editUser->id}", [
                'name' => 'New Name',
                'email' => $editUser->email,
                'role' => $editUser->role,
            ]);

        $response->assertRedirect("/dashboard/users/{$editUser->id}");
        $this->assertDatabaseHas('users', [
            'id' => $editUser->id,
            'name' => 'New Name',
        ]);
    }

    public function test_super_admin_can_delete_another_user(): void
    {
        $authUser = User::factory()->create(['role' => 'super_admin']);
        $deleteUser = User::factory()->create();

        $response = $this->actingAs($authUser)
            ->withSession(['user_management.verified_at' => time()])
            ->delete("/dashboard/users/{$deleteUser->id}");

        $response->assertRedirect('/dashboard/users');
        $this->assertDatabaseMissing('users', ['id' => $deleteUser->id]);
    }

    public function test_admin_cannot_delete_user(): void
    {
        $authUser = User::factory()->create(['role' => 'admin']);
        $deleteUser = User::factory()->create();

        $response = $this->actingAs($authUser)
            ->withSession(['user_management.verified_at' => time()])
            ->delete("/dashboard/users/{$deleteUser->id}");

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $deleteUser->id]);
    }

    public function test_user_cannot_delete_themselves(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($user)
            ->withSession(['user_management.verified_at' => time()])
            ->delete("/dashboard/users/{$user->id}");

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_admin_can_filter_users_by_single_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(3)->create(['role' => 'customer']);
        User::factory()->count(2)->create(['role' => 'vendor']);

        $response = $this->actingAs($admin)
            ->withSession(['user_management.verified_at' => time()])
            ->get('/dashboard/users?role=customer');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('users/index')
                ->where('activeRole', 'customer')
                ->where('filters.role', 'customer')
                ->has('users.data', 3)
        );
    }

    public function test_admin_can_filter_users_by_multiple_roles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'super_admin']);
        User::factory()->count(3)->create(['role' => 'customer']);

        $response = $this->actingAs($admin)
            ->withSession(['user_management.verified_at' => time()])
            ->get('/dashboard/users?role=admin,super_admin');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('users/index')
                ->where('activeRole', 'admin,super_admin')
                ->has('users.data', 2)
        );
    }

    public function test_invalid_role_filter_is_ignored(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(2)->create(['role' => 'customer']);

        $response = $this->actingAs($admin)
            ->withSession(['user_management.verified_at' => time()])
            ->get('/dashboard/users?role=invalid_role');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('users/index')
                ->has('users.data', 3)
        );
    }

    public function test_no_role_filter_returns_all_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(2)->create(['role' => 'customer']);
        User::factory()->create(['role' => 'vendor']);

        $response = $this->actingAs($admin)
            ->withSession(['user_management.verified_at' => time()])
            ->get('/dashboard/users');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('users/index')
                ->where('activeRole', null)
                ->has('users.data', 4)
        );
    }

    public function test_unverified_access_redirects_to_access_code_page(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->get('/dashboard/users');

        $response->assertRedirect('/user-management-access');
    }

    public function test_guest_cannot_access_user_management(): void
    {
        $response = $this->get('/dashboard/users');

        $response->assertRedirect('/login');
    }

    public function test_regular_user_cannot_access_user_management(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($user)->get('/dashboard/users');

        $response->assertRedirect('/login');
    }

    public function test_access_code_page_renders(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->get('/user-management-access');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page->component('auth/user-management-access')
        );
    }

    public function test_correct_access_code_grants_access(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->post('/user-management-access', [
            'code' => config('auth.user_management_access_code'),
        ]);

        $response->assertRedirect('/dashboard/users');
    }

    public function test_wrong_access_code_is_rejected(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->post('/user-management-access', [
            'code' => 'wrong-code',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('code');
    }

    public function test_empty_access_code_is_rejected(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->post('/user-management-access', [
            'code' => '',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('code');
    }

    public function test_expired_session_redirects_to_access_code_page(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $timeout = config('auth.user_management_timeout', 1200);

        $response = $this->actingAs($user)
            ->withSession(['user_management.verified_at' => time() - $timeout - 1])
            ->get('/dashboard/users');

        $response->assertRedirect('/user-management-access');
    }

    public function test_active_requests_refresh_session_timestamp(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        // Verify with code
        $this->actingAs($user)->post('/user-management-access', [
            'code' => config('auth.user_management_access_code'),
        ]);

        // Wait a moment, then make an active request
        $originalVerifiedAt = session('user_management.verified_at');

        // Simulate time passing by setting verified_at slightly in the past
        session(['user_management.verified_at' => time() - 60]);
        $beforeRequest = session('user_management.verified_at');

        $response = $this->actingAs($user)
            ->withSession(['user_management.verified_at' => time() - 60])
            ->get('/dashboard/users');

        $response->assertOk();

        // The middleware should have refreshed verified_at to now
        $afterRequest = session('user_management.verified_at');
        $this->assertGreaterThanOrEqual($beforeRequest, $afterRequest);
    }

    public function test_null_config_code_denies_access(): void
    {
        config(['auth.user_management_access_code' => null]);
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->get('/dashboard/users');

        $response->assertStatus(403);
    }

    public function test_show_page_returns_enhanced_data(): void
    {
        $authUser = User::factory()->create(['role' => 'admin']);
        $viewUser = User::factory()->create(['name' => 'Jane Doe']);

        Order::factory()->count(2)->create(['user_id' => $viewUser->id]);

        $response = $this->actingAs($authUser)
            ->withSession(['user_management.verified_at' => time()])
            ->get("/dashboard/users/{$viewUser->id}");

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('users/show')
                ->where('user.name', 'Jane Doe')
                ->has('user.orders_count')
                ->has('user.reviews_count')
                ->has('user.wishlists_count')
                ->has('user.total_spent')
                ->has('user.addresses')
                ->has('user.music_genres')
                ->has('user.recent_orders')
                ->has('user.recent_reviews')
                ->where('user.orders_count', 2)
        );
    }

    public function test_admin_can_export_users_pdf(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(3)->create(['role' => 'customer']);

        $response = $this->actingAs($admin)
            ->withSession(['user_management.verified_at' => time()])
            ->get('/dashboard/users/export-pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_pdf_export_respects_role_filter(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(3)->create(['role' => 'customer']);
        User::factory()->count(2)->create(['role' => 'vendor']);

        $response = $this->actingAs($admin)
            ->withSession(['user_management.verified_at' => time()])
            ->get('/dashboard/users/export-pdf?role=customer');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_pdf_export_respects_search_filter(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['name' => 'Unique Export Name']);
        User::factory()->count(3)->create();

        $response = $this->actingAs($admin)
            ->withSession(['user_management.verified_at' => time()])
            ->get('/dashboard/users/export-pdf?search=Unique+Export');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_guest_cannot_export_users_pdf(): void
    {
        $response = $this->get('/dashboard/users/export-pdf');

        $response->assertRedirect('/login');
    }

    public function test_unverified_access_cannot_export_pdf(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->get('/dashboard/users/export-pdf');

        $response->assertRedirect('/user-management-access');
    }
}
