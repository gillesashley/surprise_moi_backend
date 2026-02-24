<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_can_view_users_index(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->get('/dashboard/users');

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

        $response = $this->actingAs($authUser)->get("/dashboard/users/{$viewUser->id}");

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

        $response = $this->actingAs($authUser)->get("/dashboard/users/{$editUser->id}/edit");

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

        $response = $this->actingAs($authUser)->put("/dashboard/users/{$editUser->id}", [
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

        $response = $this->actingAs($authUser)->delete("/dashboard/users/{$deleteUser->id}");

        $response->assertRedirect('/dashboard/users');
        $this->assertDatabaseMissing('users', ['id' => $deleteUser->id]);
    }

    public function test_admin_cannot_delete_user(): void
    {
        $authUser = User::factory()->create(['role' => 'admin']);
        $deleteUser = User::factory()->create();

        $response = $this->actingAs($authUser)->delete("/dashboard/users/{$deleteUser->id}");

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $deleteUser->id]);
    }

    public function test_user_cannot_delete_themselves(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($user)->delete("/dashboard/users/{$user->id}");

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $user->id]);
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
}
