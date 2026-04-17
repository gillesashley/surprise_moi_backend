<?php

namespace Tests\Feature\AuditLog;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogViewerTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_index(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($user)->get('/dashboard/audit-log')->assertOk();
    }

    public function test_admin_cannot_view_index(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->get('/dashboard/audit-log')->assertForbidden();
    }

    public function test_customer_cannot_view_index(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAs($user)->get('/dashboard/audit-log')->assertRedirect();
    }

    public function test_index_filters_by_event(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        $u = User::factory()->create();
        (new AuditService)->record('login', $u, $u);
        (new AuditService)->record('logout', $u, $u);

        $response = $this->actingAs($super)->get('/dashboard/audit-log?event=login');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('audit-log/index')
            ->where('logs.data.0.event', 'login')
        );
    }

    public function test_no_destroy_route(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        $u = User::factory()->create();
        $log = (new AuditService)->record('login', $u, $u);

        $response = $this->actingAs($super)->delete("/dashboard/audit-log/{$log->id}");

        $this->assertContains($response->status(), [404, 405]);
    }
}
