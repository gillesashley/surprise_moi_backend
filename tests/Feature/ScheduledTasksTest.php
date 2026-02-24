<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledTasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_scheduled_tasks_page()
    {
        $this->get(route('scheduled-tasks'))->assertRedirect(route('login'));
    }

    public function test_authenticated_admin_users_can_access_scheduled_tasks()
    {
        $this->actingAs($user = User::factory()->create(['role' => 'admin']));

        $response = $this->get(route('scheduled-tasks'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page->has('props.scheduleData')
        );
    }

    public function test_authenticated_super_admin_users_can_access_scheduled_tasks()
    {
        $this->actingAs($user = User::factory()->create(['role' => 'super_admin']));

        $response = $this->get(route('scheduled-tasks'));

        $response->assertOk();
    }

    public function test_regular_users_cannot_access_scheduled_tasks()
    {
        $this->actingAs($user = User::factory()->create(['role' => 'customer']));

        $this->get(route('scheduled-tasks'))->assertRedirect(route('login'));
    }

    public function test_scheduled_tasks_returns_schedule_data()
    {
        $this->actingAs($user = User::factory()->create(['role' => 'admin']));

        $response = $this->get(route('scheduled-tasks'));

        $response->assertOk()
            ->assertInertia(
                fn ($page) => $page->has('props.scheduleData')
            );
    }
}
