<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_authenticated_admin_users_can_visit_the_dashboard()
    {
        $this->actingAs($user = User::factory()->create(['role' => 'admin']));

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_authenticated_super_admin_users_can_visit_the_dashboard()
    {
        $this->actingAs($user = User::factory()->create(['role' => 'super_admin']));

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_regular_users_cannot_access_the_dashboard()
    {
        $this->actingAs($user = User::factory()->create(['role' => 'customer']));

        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }
}
