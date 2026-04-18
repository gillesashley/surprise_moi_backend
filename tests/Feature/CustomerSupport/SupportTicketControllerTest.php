<?php

namespace Tests\Feature\CustomerSupport;

use App\Contracts\Sms\SmsProviderInterface;
use App\Models\SupportInteraction;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
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

    public function test_admin_can_view_ticket_index(): void
    {
        SupportTicket::factory()->count(3)->create([
            'created_by' => $this->admin->id,
            'assigned_to' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get('/dashboard/customer-support');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('customer-support/index')
            ->has('tickets.data', 3)
            ->has('statuses')
            ->has('priorities')
            ->has('categories'),
        );
    }

    public function test_index_filters_by_status(): void
    {
        SupportTicket::factory()->create([
            'status' => SupportTicket::STATUS_OPEN,
            'created_by' => $this->admin->id,
            'assigned_to' => $this->admin->id,
        ]);
        SupportTicket::factory()->create([
            'status' => SupportTicket::STATUS_CLOSED,
            'created_by' => $this->admin->id,
            'assigned_to' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get('/dashboard/customer-support?status=open');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('customer-support/index')
            ->has('tickets.data', 1)
            ->where('tickets.data.0.status', 'open'),
        );
    }

    public function test_non_admin_cannot_view_index(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)->get('/dashboard/customer-support');

        $response->assertRedirect(route('login'));
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

    public function test_assigned_to_defaults_to_creator_when_not_provided(): void
    {
        $response = $this->actingAs($this->admin)->post('/dashboard/customer-support', [
            'subject' => 'Check in',
            'category' => 'check_in',
            'contact_name' => 'Kofi Appiah',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('support_tickets', [
            'subject' => 'Check in',
            'created_by' => $this->admin->id,
            'assigned_to' => $this->admin->id,
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

    public function test_show_loads_with_timeline(): void
    {
        $ticket = SupportTicket::factory()->create([
            'created_by' => $this->admin->id,
            'assigned_to' => $this->admin->id,
        ]);
        SupportInteraction::factory()->create([
            'ticket_id' => $ticket->id,
            'created_by' => $this->admin->id,
            'channel' => 'phone_call',
        ]);

        $response = $this->actingAs($this->admin)->get("/dashboard/customer-support/{$ticket->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('customer-support/show')
            ->where('ticket.id', $ticket->id)
            ->has('timeline', 1)
            ->has('assignees')
            ->has('templates')
            ->has('channels'),
        );
    }

    public function test_admin_can_log_an_interaction(): void
    {
        $ticket = SupportTicket::factory()->create([
            'created_by' => $this->admin->id,
            'assigned_to' => $this->admin->id,
            'status' => SupportTicket::STATUS_OPEN,
        ]);

        $response = $this->actingAs($this->admin)->post(
            "/dashboard/customer-support/{$ticket->id}/interactions",
            [
                'channel' => 'phone_call',
                'direction' => 'outbound',
                'summary' => 'Called to check on order ETA.',
                'follow_up_at' => today()->addDays(2)->toDateString(),
            ],
        );

        $response->assertRedirect("/dashboard/customer-support/{$ticket->id}");
        $this->assertDatabaseHas('support_interactions', [
            'ticket_id' => $ticket->id,
            'channel' => 'phone_call',
            'created_by' => $this->admin->id,
        ]);
        $this->assertSame(SupportTicket::STATUS_IN_PROGRESS, $ticket->fresh()->status);
    }

    public function test_interaction_requires_valid_channel(): void
    {
        $ticket = SupportTicket::factory()->create([
            'created_by' => $this->admin->id,
            'assigned_to' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->post(
            "/dashboard/customer-support/{$ticket->id}/interactions",
            ['channel' => 'invalid_channel', 'direction' => 'outbound', 'summary' => 'x'],
        );

        $response->assertSessionHasErrors('channel');
    }

    public function test_sending_sms_persists_message_and_calls_provider(): void
    {
        $ticket = SupportTicket::factory()->create([
            'created_by' => $this->admin->id,
            'assigned_to' => $this->admin->id,
            'status' => SupportTicket::STATUS_OPEN,
            'contact_phone' => '+233244111222',
        ]);

        $mock = Mockery::mock(SmsProviderInterface::class);
        $mock->shouldReceive('send')
            ->once()
            ->with('+233244111222', 'Hi there, just checking in.')
            ->andReturn(['success' => true, 'message' => 'ok', 'data' => null]);
        $this->app->instance(SmsProviderInterface::class, $mock);

        $response = $this->actingAs($this->admin)->post(
            "/dashboard/customer-support/{$ticket->id}/messages",
            ['body' => 'Hi there, just checking in.', 'template_key' => 'follow_up'],
        );

        $response->assertRedirect("/dashboard/customer-support/{$ticket->id}");
        $this->assertDatabaseHas('support_messages', [
            'ticket_id' => $ticket->id,
            'to_phone' => '+233244111222',
            'body' => 'Hi there, just checking in.',
            'template_key' => 'follow_up',
            'status' => SupportMessage::STATUS_SENT,
            'sent_by' => $this->admin->id,
        ]);
        $this->assertSame(SupportTicket::STATUS_IN_PROGRESS, $ticket->fresh()->status);
    }

    public function test_sms_failure_is_recorded_with_reason(): void
    {
        $ticket = SupportTicket::factory()->create([
            'created_by' => $this->admin->id,
            'assigned_to' => $this->admin->id,
            'contact_phone' => '+233244999000',
        ]);

        $mock = Mockery::mock(SmsProviderInterface::class);
        $mock->shouldReceive('send')
            ->once()
            ->andReturn(['success' => false, 'message' => 'Provider rejected number', 'data' => null]);
        $this->app->instance(SmsProviderInterface::class, $mock);

        $response = $this->actingAs($this->admin)->post(
            "/dashboard/customer-support/{$ticket->id}/messages",
            ['body' => 'Test', 'template_key' => 'custom'],
        );

        $response->assertRedirect("/dashboard/customer-support/{$ticket->id}");
        $this->assertDatabaseHas('support_messages', [
            'ticket_id' => $ticket->id,
            'status' => SupportMessage::STATUS_FAILED,
            'failed_reason' => 'Provider rejected number',
        ]);
    }

    public function test_sms_fails_gracefully_without_phone(): void
    {
        $ticket = SupportTicket::factory()->create([
            'created_by' => $this->admin->id,
            'assigned_to' => $this->admin->id,
            'user_id' => null,
            'contact_phone' => null,
        ]);

        // Provider must not be called when no phone exists.
        $mock = Mockery::mock(SmsProviderInterface::class);
        $mock->shouldNotReceive('send');
        $this->app->instance(SmsProviderInterface::class, $mock);

        $response = $this->actingAs($this->admin)->post(
            "/dashboard/customer-support/{$ticket->id}/messages",
            ['body' => 'Hello', 'template_key' => 'custom'],
        );

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('support_messages', 0);
    }

    public function test_admin_can_mark_ticket_in_progress(): void
    {
        $ticket = SupportTicket::factory()->create([
            'created_by' => $this->admin->id,
            'assigned_to' => $this->admin->id,
            'status' => SupportTicket::STATUS_OPEN,
        ]);

        $response = $this->actingAs($this->admin)->patch(
            "/dashboard/customer-support/{$ticket->id}/status",
            ['status' => SupportTicket::STATUS_IN_PROGRESS],
        );

        $response->assertRedirect();
        $this->assertSame(SupportTicket::STATUS_IN_PROGRESS, $ticket->fresh()->status);
    }

    public function test_closing_ticket_requires_closure_note(): void
    {
        $ticket = SupportTicket::factory()->create([
            'created_by' => $this->admin->id,
            'assigned_to' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->patch(
            "/dashboard/customer-support/{$ticket->id}/status",
            ['status' => SupportTicket::STATUS_CLOSED],
        );

        $response->assertSessionHasErrors('closure_note');
    }

    public function test_admin_can_close_ticket_with_note(): void
    {
        $ticket = SupportTicket::factory()->create([
            'created_by' => $this->admin->id,
            'assigned_to' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->patch(
            "/dashboard/customer-support/{$ticket->id}/status",
            ['status' => SupportTicket::STATUS_CLOSED, 'closure_note' => 'Issue resolved by phone.'],
        );

        $response->assertRedirect();
        $fresh = $ticket->fresh();
        $this->assertSame(SupportTicket::STATUS_CLOSED, $fresh->status);
        $this->assertSame('Issue resolved by phone.', $fresh->closure_note);
        $this->assertNotNull($fresh->closed_at);
        $this->assertSame($this->admin->id, $fresh->closed_by);
    }

    public function test_admin_can_reassign_ticket(): void
    {
        $ticket = SupportTicket::factory()->create([
            'created_by' => $this->admin->id,
            'assigned_to' => $this->admin->id,
        ]);
        $otherAdmin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($this->admin)->patch(
            "/dashboard/customer-support/{$ticket->id}/assign",
            ['assigned_to' => $otherAdmin->id],
        );

        $response->assertRedirect();
        $this->assertSame($otherAdmin->id, $ticket->fresh()->assigned_to);
    }

    public function test_cannot_reassign_to_non_admin(): void
    {
        $ticket = SupportTicket::factory()->create([
            'created_by' => $this->admin->id,
            'assigned_to' => $this->admin->id,
        ]);
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($this->admin)->patch(
            "/dashboard/customer-support/{$ticket->id}/assign",
            ['assigned_to' => $customer->id],
        );

        $response->assertSessionHasErrors('assigned_to');
        $this->assertSame($this->admin->id, $ticket->fresh()->assigned_to);
    }
}
