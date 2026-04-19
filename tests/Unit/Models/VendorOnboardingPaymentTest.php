<?php

namespace Tests\Unit\Models;

use App\Models\ReferralCode;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorOnboardingPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorOnboardingPaymentTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_persists_a_referral_code_id_and_loads_the_relation(): void
    {
        $user = User::factory()->create();
        $application = VendorApplication::factory()->create(['user_id' => $user->id]);
        $code = ReferralCode::factory()->create();

        $payment = VendorOnboardingPayment::create([
            'user_id' => $user->id,
            'vendor_application_id' => $application->id,
            'referral_code_id' => $code->id,
            'reference' => VendorOnboardingPayment::generateReference(),
            'amount' => 150.00,
            'amount_in_kobo' => 15000,
            'discount_amount' => 50.00,
            'currency' => 'GHS',
            'status' => VendorOnboardingPayment::STATUS_PENDING,
        ]);

        $this->assertSame($code->id, $payment->fresh()->referral_code_id);
        $this->assertInstanceOf(ReferralCode::class, $payment->referralCode);
        $this->assertSame($code->id, $payment->referralCode->id);
    }
}
