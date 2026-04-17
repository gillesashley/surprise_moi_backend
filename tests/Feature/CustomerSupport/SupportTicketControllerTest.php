<?php

namespace Tests\Feature\CustomerSupport;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_can_create_a_ticket(): void
    {
        $response = $this->actingAs($this->admin)->post('/dashboard/customer-support', [
            'subject' => 'Cannot place order',
            'category' => 'order_issue',
            'priority' => 'normal',
            'contact_name' => 'Ama Mensah',
            'contact_phone' => '+233244111222',
            'assigned_to' => $this->admin->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('support_tickets', [
            'subject' => 'Cannot place order',
            'category' => 'order_issue',
            'created_by' => $this->admin->id,
            'assigned_to' => $this->admin->id,
            'status' => 'open',
        ]);
    }

    public function test_subject_is_required(): void
    {
        $response = $this->actingAs($this->admin)->post('/dashboard/customer-support', [
            'category' => 'general_inquiry',
            'contact_name' => 'Ama',
            'assigned_to' => $this->admin->id,
        ]);

        $response->assertSessionHasErrors('subject');
    }

    public function test_non_admin_cannot_create_ticket(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)->post('/dashboard/customer-support', []);

        // EnsureDashboardAccess will redirect non-admins
        $response->assertRedirect(route('login'));
    }
}
