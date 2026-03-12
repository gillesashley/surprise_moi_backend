<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VendorApplicationDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_deleting_user_force_deletes_their_vendor_applications(): void
    {
        Storage::fake();

        $user = User::factory()->create();
        $application = VendorApplication::factory()
            ->for($user)
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->readyToSubmit()
            ->create([
                'payment_required' => true,
                'payment_completed' => true,
            ]);

        // Store fake files at the paths the factory created
        Storage::disk()->put($application->ghana_card_front, 'fake');
        Storage::disk()->put($application->ghana_card_back, 'fake');
        Storage::disk()->put($application->selfie_image, 'fake');
        Storage::disk()->put($application->proof_of_business, 'fake');

        $applicationId = $application->id;
        $ghanaCardFront = $application->ghana_card_front;

        $user->delete();

        // Application should be hard deleted (not in soft deleted either)
        $this->assertDatabaseMissing('vendor_applications', ['id' => $applicationId]);

        // Files should be cleaned up
        Storage::disk()->assertMissing($ghanaCardFront);
    }

    public function test_super_admin_can_delete_vendor_application(): void
    {
        Storage::fake();

        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $applicant = User::factory()->create(['role' => 'customer']);

        $application = VendorApplication::factory()
            ->for($applicant)
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->readyToSubmit()
            ->create([
                'payment_required' => true,
                'payment_completed' => true,
            ]);

        Storage::disk()->put($application->ghana_card_front, 'fake');
        Storage::disk()->put($application->selfie_image, 'fake');

        $response = $this->actingAs($superAdmin)
            ->delete("/dashboard/vendor-applications/{$application->id}", [
                'confirmation' => 'DELETE',
            ]);

        $response->assertRedirect('/dashboard/vendor-applications');

        // Application should be permanently deleted
        $this->assertDatabaseMissing('vendor_applications', ['id' => $application->id]);

        // Files should be cleaned up
        Storage::disk()->assertMissing($application->ghana_card_front);
        Storage::disk()->assertMissing($application->selfie_image);

        // User should still exist
        $this->assertDatabaseHas('users', ['id' => $applicant->id]);
    }

    public function test_deleting_approved_vendor_application_reverts_user_role(): void
    {
        Storage::fake();

        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $applicant = User::factory()->create(['role' => 'vendor', 'vendor_tier' => 2]);

        $application = VendorApplication::factory()
            ->for($applicant)
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->readyToSubmit()
            ->approved()
            ->create([
                'payment_required' => true,
                'payment_completed' => true,
            ]);

        $response = $this->actingAs($superAdmin)
            ->delete("/dashboard/vendor-applications/{$application->id}", [
                'confirmation' => 'DELETE',
            ]);

        $response->assertRedirect('/dashboard/vendor-applications');

        // User role should be reverted
        $applicant->refresh();
        $this->assertEquals('user', $applicant->role);
        $this->assertNull($applicant->vendor_tier);
    }

    public function test_non_super_admin_cannot_delete_vendor_application(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $applicant = User::factory()->create();

        $application = VendorApplication::factory()
            ->for($applicant)
            ->withGhanaCard()
            ->create();

        $response = $this->actingAs($admin)
            ->delete("/dashboard/vendor-applications/{$application->id}", [
                'confirmation' => 'DELETE',
            ]);

        $response->assertRedirect();

        // Application should still exist
        $this->assertDatabaseHas('vendor_applications', ['id' => $application->id]);
    }

    public function test_delete_requires_confirmation_text(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $applicant = User::factory()->create();

        $application = VendorApplication::factory()
            ->for($applicant)
            ->withGhanaCard()
            ->create();

        // Missing confirmation
        $response = $this->actingAs($superAdmin)
            ->delete("/dashboard/vendor-applications/{$application->id}", [
                'confirmation' => '',
            ]);

        $response->assertSessionHasErrors('confirmation');

        // Wrong confirmation text
        $response = $this->actingAs($superAdmin)
            ->delete("/dashboard/vendor-applications/{$application->id}", [
                'confirmation' => 'REMOVE',
            ]);

        $response->assertSessionHasErrors('confirmation');

        // Application should still exist
        $this->assertDatabaseHas('vendor_applications', ['id' => $application->id]);
    }
}
