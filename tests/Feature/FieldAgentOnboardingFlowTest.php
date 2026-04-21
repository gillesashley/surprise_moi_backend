<?php

namespace Tests\Feature;

use App\Models\ReferralCode;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorVisit;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FieldAgentOnboardingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /** @test */
    public function it_handles_the_full_onboarding_questionnaire_and_approval_flow()
    {
        // 1. Setup field agent and referral code
        $fieldAgent = User::factory()->create(['role' => 'field_agent', 'name' => 'Agent Smith']);
        $referralService = app(ReferralService::class);
        $referralCode = tap(
            $referralService->createReferralCode($fieldAgent, null, 'Test Code', 0, null, null, 'FA'),
            function ($code) {
                // Ensure usage is tracked properly
            }
        );

        // 2. Setup pending vendor application using the field agent's code
        $vendor = User::factory()->create(['role' => 'user', 'name' => 'John Vendor']);
        $application = VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'status' => VendorApplication::STATUS_PENDING,
            'referral_code_id' => $referralCode->id,
            'referral_code_used' => $referralCode->code,
            'current_step' => 4,
            'completed_step' => 4,
            'payment_required' => true,
            'payment_completed' => true,
            'submitted_at' => now(),
        ]);

        \App\Models\Referral::create([
            'referral_code_id' => $referralCode->id,
            'influencer_id' => $referralCode->influencer_id,
            'vendor_id' => $vendor->id,
            'vendor_application_id' => $application->id,
            'status' => \App\Models\Referral::STATUS_PENDING,
        ]);

        // 3. Field Agent views their pending visits
        $response = $this->actingAs($fieldAgent)
            ->get(route('field-agent.visits.index'));
        
        $response->assertStatus(200);
        $response->assertSee($vendor->name); // Should see the application in the dashboard

        // 4. Field Agent starts a visit
        $response = $this->actingAs($fieldAgent)
            ->post(route('field-agent.visits.start', $application));
            
        $response->assertRedirect();
        
        // Assert visit record created
        $this->assertDatabaseHas('vendor_visits', [
            'vendor_application_id' => $application->id,
            'field_agent_user_id' => $fieldAgent->id,
            'status' => 'draft',
        ]);

        $visit = VendorVisit::where('vendor_application_id', $application->id)->first();

        // 5. Field Agent submits the questionnaire
        $photo = UploadedFile::fake()->image('storefront.jpg');

        $response = $this->actingAs($fieldAgent)
            ->post(route('field-agent.visits.submit', $visit), [
                'ghana_card_number' => 'GHA-123456789-0',
                'tin_number' => 'P0000000001',
                'has_shop' => true,
                'shop_location' => 'Accra Mall',
                'storefront_photo' => $photo,
            ]);

        $response->assertRedirect(route('field-agent.visits.index'));
        $response->assertSessionHas('success', 'Questionnaire submitted successfully.');

        $this->assertDatabaseHas('vendor_visits', [
            'id' => $visit->id,
            'status' => 'submitted',
            'ghana_card_number' => 'GHA-123456789-0',
            'has_shop' => true,
        ]);

        // 6. Admin views the Vendor Application and approves it
        $admin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($admin)
            ->get(route('vendor-applications.show', $application));

        $response->assertStatus(200);
        // Inertia assert to ensure questionnaire is loaded
        $response->assertInertia(fn ($page) => $page
            ->component('vendor-applications/show')
            ->has('application.questionnaire.id')
            ->where('application.questionnaire.ghana_card_number', 'GHA-123456789-0')
        );

        $response = $this->actingAs($admin)
            ->post(route('vendor-applications.approve', $application));

        $response->assertRedirect(route('vendor-applications.show', $application));
        $response->assertSessionHas('success');

        // 7. Verify the Vendor is approved and referral points/referral status are updated
        $this->assertDatabaseHas('vendor_applications', [
            'id' => $application->id,
            'status' => VendorApplication::STATUS_APPROVED,
        ]);
        
        $this->assertDatabaseHas('users', [
            'id' => $vendor->id,
            'role' => 'vendor',
        ]);

        // Verify referral was activated
        $this->assertDatabaseHas('referrals', [
            'vendor_application_id' => $application->id,
            'status' => 'active',
        ]);
    }
}
