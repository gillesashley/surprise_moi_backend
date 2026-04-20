<?php

namespace Tests\Feature\Observers;

use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldVerificationInvalidationObserverTest extends TestCase
{
    use RefreshDatabase;

    protected function verifiedVendor(): User
    {
        $vendor = User::factory()->vendor()->create([
            'field_verified_at' => now()->subDay(),
            'field_verified_until' => now()->addMonths(11),
        ]);
        VendorVisit::factory()->passed()->create(['vendor_user_id' => $vendor->id]);

        return $vendor;
    }

    public function test_editing_business_name_invalidates_active_badge(): void
    {
        $vendor = $this->verifiedVendor();

        $vendor->update(['business_name' => 'New Business Name']);

        $vendor->refresh();
        $this->assertNull($vendor->field_verified_at);
        $this->assertNull($vendor->field_verified_until);
    }

    public function test_editing_phone_invalidates_active_badge(): void
    {
        $vendor = $this->verifiedVendor();

        $vendor->update(['phone' => '+233200000001']);

        $vendor->refresh();
        $this->assertNull($vendor->field_verified_until);
    }

    public function test_editing_email_does_not_invalidate_badge(): void
    {
        $vendor = $this->verifiedVendor();

        $vendor->update(['email' => 'new@example.com']);

        $vendor->refresh();
        $this->assertNotNull($vendor->field_verified_until);
    }

    public function test_updating_vendor_application_ghana_card_invalidates_badge(): void
    {
        $vendor = $this->verifiedVendor();
        $application = VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'status' => 'approved',
            'ghana_card_front' => 'old/front.jpg',
        ]);

        $application->update(['ghana_card_front' => 'new/front.jpg']);

        $vendor->refresh();
        $this->assertNull($vendor->field_verified_until);
    }

    public function test_non_verified_vendor_is_not_affected(): void
    {
        $vendor = User::factory()->vendor()->create([
            'field_verified_at' => null,
            'field_verified_until' => null,
            'business_name' => 'Old Name',
        ]);

        $vendor->update(['business_name' => 'New Name']);

        $vendor->refresh();
        $this->assertNull($vendor->field_verified_until);
    }
}
