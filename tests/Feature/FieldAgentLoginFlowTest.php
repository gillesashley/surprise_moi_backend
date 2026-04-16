<?php

namespace Tests\Feature;

use App\Models\FieldAgentApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FieldAgentLoginFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_field_agent_logs_in_and_redirects_to_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => 'field_agent',
            'email' => 'agent@example.com',
            'password' => Hash::make('AgentPass1'),
        ]);

        $response = $this->post('/login', [
            'email' => 'agent@example.com',
            'password' => 'AgentPass1',
        ]);

        $response->assertRedirect('/field-agent/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_pending_applicant_with_correct_password_sees_under_review_message(): void
    {
        FieldAgentApplication::factory()->pending()->create([
            'email' => 'pending@example.com',
            'password' => Hash::make('Correct1'),
        ]);

        $response = $this->post('/login', [
            'email' => 'pending@example.com',
            'password' => 'Correct1',
        ]);

        $response->assertSessionHasErrors('email');
        $errors = session('errors')->get('email');
        $this->assertStringContainsString('under review', strtolower(implode(' ', $errors)));
        $this->assertGuest();
    }

    public function test_rejected_applicant_with_correct_password_sees_not_approved_message(): void
    {
        FieldAgentApplication::factory()->rejected()->create([
            'email' => 'rejected@example.com',
            'password' => Hash::make('Correct1'),
        ]);

        $response = $this->post('/login', [
            'email' => 'rejected@example.com',
            'password' => 'Correct1',
        ]);

        $response->assertSessionHasErrors('email');
        $errors = session('errors')->get('email');
        $this->assertStringContainsString('not approved', strtolower(implode(' ', $errors)));
        $this->assertGuest();
    }

    public function test_wrong_password_on_application_returns_generic_error(): void
    {
        FieldAgentApplication::factory()->pending()->create([
            'email' => 'pending2@example.com',
            'password' => Hash::make('Correct1'),
        ]);

        $response = $this->post('/login', [
            'email' => 'pending2@example.com',
            'password' => 'WrongPass!',
        ]);

        $response->assertSessionHasErrors('email');
        $errors = session('errors')->get('email');
        $this->assertStringNotContainsString('under review', strtolower(implode(' ', $errors)));
        $this->assertGuest();
    }
}
