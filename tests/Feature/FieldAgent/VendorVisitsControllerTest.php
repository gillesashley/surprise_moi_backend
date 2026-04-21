<?php

namespace Tests\Feature\FieldAgent;

use App\Enums\VendorVisitStatus;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorVisit;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VendorVisitsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    /**
     * @param  array<string, mixed>  $appAttributes
     */
    protected function approvedVendor(array $appAttributes = []): User
    {
        $vendor = User::factory()->vendor()->create();
        VendorApplication::factory()->create(array_merge([
            'user_id' => $vendor->id,
            'status' => 'approved',
            'has_business_certificate' => false,
            'tin_number' => null,
        ], $appAttributes));

        return $vendor;
    }

    public function test_non_field_agent_cannot_access_visits_index(): void
    {
        // EnsureDashboardAccess logs out non-dashboard roles and redirects to /login.
        $user = User::factory()->vendor()->create();

        $this->actingAs($user)
            ->get('/field-agent/visits')
            ->assertRedirect('/login');
    }

    public function test_field_agent_sees_visits_index(): void
    {
        $agent = User::factory()->fieldAgent()->create();

        $this->actingAs($agent)
            ->get('/field-agent/visits')
            ->assertOk();
    }

    public function test_agent_can_start_a_visit(): void
    {
        $agent = User::factory()->fieldAgent()->create();
        $vendor = $this->approvedVendor();

        $response = $this->actingAs($agent)->postJson("/field-agent/visits/{$vendor->id}/start", [
            'latitude' => 5.56,
            'longitude' => -0.2,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vendor_visits', [
            'vendor_user_id' => $vendor->id,
            'field_agent_user_id' => $agent->id,
            'status' => VendorVisitStatus::Draft->value,
        ]);
    }

    public function test_agent_cannot_start_a_visit_without_gps(): void
    {
        $agent = User::factory()->fieldAgent()->create();
        $vendor = $this->approvedVendor();

        $this->actingAs($agent)
            ->postJson("/field-agent/visits/{$vendor->id}/start", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_agent_cannot_start_a_visit_on_unapproved_vendor(): void
    {
        $agent = User::factory()->fieldAgent()->create();
        $vendor = User::factory()->vendor()->create();
        VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'status' => 'pending',
        ]);

        $this->actingAs($agent)
            ->postJson("/field-agent/visits/{$vendor->id}/start", [
                'latitude' => 5.56, 'longitude' => -0.2,
            ])
            ->assertStatus(422);
    }

    public function test_agent_cannot_open_another_agents_draft(): void
    {
        $agentA = User::factory()->fieldAgent()->create();
        $agentB = User::factory()->fieldAgent()->create();
        $visit = VendorVisit::factory()->create(['field_agent_user_id' => $agentA->id]);

        $this->actingAs($agentB)
            ->get("/field-agent/visits/forms/{$visit->id}")
            ->assertForbidden();
    }

    public function test_agent_can_update_a_single_item(): void
    {
        $agent = User::factory()->fieldAgent()->create();
        $vendor = $this->approvedVendor();
        $this->actingAs($agent)->postJson("/field-agent/visits/{$vendor->id}/start", [
            'latitude' => 5.56, 'longitude' => -0.2,
        ]);
        $visit = VendorVisit::where('field_agent_user_id', $agent->id)->firstOrFail();
        $item = $visit->items()->firstOrFail();

        $this->actingAs($agent)
            ->patchJson("/field-agent/visits/forms/{$visit->id}/items/{$item->id}", [
                'passed' => true,
                'note' => 'checked and matches',
            ])
            ->assertOk();

        $this->assertSame(true, $item->fresh()->passed);
        $this->assertSame('checked and matches', $item->fresh()->note);
    }

    public function test_submit_with_all_critical_pass_yields_passed(): void
    {
        Storage::fake('public');
        $agent = User::factory()->fieldAgent()->create();
        $vendor = $this->approvedVendor();

        $this->actingAs($agent)->postJson("/field-agent/visits/{$vendor->id}/start", [
            'latitude' => 5.56, 'longitude' => -0.2,
        ]);
        $visit = VendorVisit::where('field_agent_user_id', $agent->id)->firstOrFail();
        $visit->items()->update(['passed' => true]);

        $response = $this->actingAs($agent)->postJson("/field-agent/visits/forms/{$visit->id}/submit", [
            'storefront_photo' => UploadedFile::fake()->create('sf.jpg', 100, 'image/jpeg'),
            'owner_photo' => UploadedFile::fake()->create('ow.jpg', 100, 'image/jpeg'),
            'notes' => 'all good',
            'escalated' => false,
        ]);

        $response->assertRedirect();
        $this->assertSame(VendorVisitStatus::Passed, $visit->fresh()->status);
        $vendor->refresh();
        $this->assertNotNull($vendor->field_verified_until);
    }

    public function test_submit_is_idempotent_once_terminal(): void
    {
        Storage::fake('public');
        $agent = User::factory()->fieldAgent()->create();
        $visit = VendorVisit::factory()->passed()->create(['field_agent_user_id' => $agent->id]);
        $firstSubmittedAt = $visit->submitted_at;

        $this->actingAs($agent)->postJson("/field-agent/visits/forms/{$visit->id}/submit", [
            'storefront_photo' => UploadedFile::fake()->create('new.jpg', 100, 'image/jpeg'),
            'owner_photo' => UploadedFile::fake()->create('new2.jpg', 100, 'image/jpeg'),
        ])->assertRedirect();

        $this->assertTrue($firstSubmittedAt->equalTo($visit->fresh()->submitted_at));
    }
}
