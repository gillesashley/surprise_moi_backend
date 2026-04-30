<?php

namespace Tests\Feature\Admin;

use App\Models\FieldAgentApplication;
use App\Models\User;
use App\Notifications\FieldAgentApprovedNotification;
use App\Notifications\FieldAgentRejectedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FieldAgentApplicationAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_non_admin_cannot_access_index(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)->get('/dashboard/field-agent-applications');

        // customer fails canAccessDashboard — middleware logs them out and redirects to login
        $response->assertRedirect(route('login'));
    }

    public function test_admin_index_lists_applications(): void
    {
        FieldAgentApplication::factory()->count(3)->pending()->create();

        $this->actingAs($this->admin)
            ->get('/dashboard/field-agent-applications')
            ->assertOk();
    }

    public function test_admin_can_filter_by_status(): void
    {
        FieldAgentApplication::factory()->count(2)->pending()->create();
        FieldAgentApplication::factory()->count(1)->approved()->create();

        $this->actingAs($this->admin)
            ->get('/dashboard/field-agent-applications?status=pending')
            ->assertOk();
    }

    public function test_admin_can_view_application_detail(): void
    {
        $app = FieldAgentApplication::factory()->pending()->create();

        $this->actingAs($this->admin)
            ->get("/dashboard/field-agent-applications/{$app->id}")
            ->assertOk();
    }

    public function test_show_returns_image_urls_from_default_disk_not_public_disk(): void
    {
        Storage::fake('r2');

        $app = FieldAgentApplication::factory()->pending()->create([
            'ghana_card_image_path' => 'field-agents/ghana-cards/front.jpg',
            'ghana_card_back_image_path' => 'field-agents/ghana-cards/back.jpg',
            'selfie_path' => 'field-agents/selfies/selfie.jpg',
        ]);

        $defaultDisk = Storage::disk(config('filesystems.default'));

        $this->actingAs($this->admin)
            ->get("/dashboard/field-agent-applications/{$app->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('application.ghana_card_image_url', $defaultDisk->url($app->ghana_card_image_path))
                ->where('application.ghana_card_back_image_url', $defaultDisk->url($app->ghana_card_back_image_path))
                ->where('application.selfie_url', $defaultDisk->url($app->selfie_path))
                ->etc()
            );
    }

    public function test_approval_creates_field_agent_user_and_clears_password(): void
    {
        $app = FieldAgentApplication::factory()->pending()->create([
            'email' => 'newagent@example.com',
            'password' => Hash::make('AgentSecret1'),
        ]);
        $originalHash = $app->password;

        $this->actingAs($this->admin)
            ->post("/dashboard/field-agent-applications/{$app->id}/approve")
            ->assertRedirect();

        $user = User::where('email', 'newagent@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('field_agent', $user->role);
        $this->assertTrue(Hash::check('AgentSecret1', $user->password));
        $this->assertSame($originalHash, $user->password);

        $app->refresh();
        $this->assertSame('approved', $app->status->value);
        $this->assertSame($this->admin->id, $app->reviewed_by);
        $this->assertNotNull($app->reviewed_at);
        $this->assertSame($user->id, $app->approved_user_id);
        $this->assertNull($app->password);

        Notification::assertSentTo([$app->fresh()], FieldAgentApprovedNotification::class);
    }

    public function test_rejection_requires_reason(): void
    {
        $app = FieldAgentApplication::factory()->pending()->create();

        $this->actingAs($this->admin)
            ->post("/dashboard/field-agent-applications/{$app->id}/reject", [])
            ->assertSessionHasErrors('rejection_reason');

        $app->refresh();
        $this->assertSame('pending', $app->status->value);
    }

    public function test_rejection_updates_application_and_sends_notification(): void
    {
        $app = FieldAgentApplication::factory()->pending()->create();

        $this->actingAs($this->admin)
            ->post("/dashboard/field-agent-applications/{$app->id}/reject", [
                'rejection_reason' => 'Ghana card image is blurry.',
            ])
            ->assertRedirect();

        $app->refresh();
        $this->assertSame('rejected', $app->status->value);
        $this->assertSame('Ghana card image is blurry.', $app->rejection_reason);
        $this->assertSame($this->admin->id, $app->reviewed_by);

        Notification::assertSentTo([$app->fresh()], FieldAgentRejectedNotification::class);
    }

    public function test_cannot_approve_already_reviewed_application(): void
    {
        $app = FieldAgentApplication::factory()->approved()->create();

        $this->actingAs($this->admin)
            ->post("/dashboard/field-agent-applications/{$app->id}/approve")
            ->assertSessionHasErrors();
    }

    public function test_cannot_reject_already_reviewed_application(): void
    {
        $app = FieldAgentApplication::factory()->rejected()->create();

        $this->actingAs($this->admin)
            ->post("/dashboard/field-agent-applications/{$app->id}/reject", [
                'rejection_reason' => 'Another reason here.',
            ])
            ->assertSessionHasErrors();
    }

    public function test_approval_creates_a_referral_code_for_the_new_user(): void
    {
        $app = FieldAgentApplication::factory()->pending()->create([
            'email' => 'newagent@example.com',
            'password' => Hash::make('AgentSecret1'),
        ]);

        app(\App\Services\FieldAgentApprovalService::class)->approve($app->fresh(), $this->admin);

        $newUser = User::where('email', $app->email)->firstOrFail();
        $code = \App\Models\ReferralCode::where('influencer_id', $newUser->id)->first();

        $this->assertNotNull($code, 'A ReferralCode should be created for the new agent');
    }

    public function test_generated_referral_code_uses_the_f_a_prefix(): void
    {
        $app = FieldAgentApplication::factory()->pending()->create([
            'email' => 'newagent@example.com',
            'password' => Hash::make('AgentSecret1'),
        ]);

        app(\App\Services\FieldAgentApprovalService::class)->approve($app->fresh(), $this->admin);

        $newUser = User::where('email', $app->email)->firstOrFail();
        $code = \App\Models\ReferralCode::where('influencer_id', $newUser->id)->firstOrFail();

        $this->assertStringStartsWith('FA-', $code->code);
    }

    public function test_generated_referral_code_is_active_with_no_expiry_or_max_usages(): void
    {
        $app = FieldAgentApplication::factory()->pending()->create([
            'email' => 'newagent@example.com',
            'password' => Hash::make('AgentSecret1'),
        ]);

        app(\App\Services\FieldAgentApprovalService::class)->approve($app->fresh(), $this->admin);

        $newUser = User::where('email', $app->email)->firstOrFail();
        $code = \App\Models\ReferralCode::where('influencer_id', $newUser->id)->firstOrFail();

        $this->assertTrue($code->is_active);
        $this->assertNull($code->expires_at);
        $this->assertNull($code->max_usages);
    }
}
