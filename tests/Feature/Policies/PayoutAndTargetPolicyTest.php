<?php

namespace Tests\Feature\Policies;

use App\Models\User;
use App\Policies\PayoutRequestPolicy;
use App\Policies\TargetPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayoutAndTargetPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_target_policy_view_any_accepts_employee(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->assertTrue((new TargetPolicy)->viewAny($employee));
    }

    public function test_target_policy_view_any_accepts_field_agent(): void
    {
        $agent = User::factory()->create(['role' => 'field_agent']);

        $this->assertTrue((new TargetPolicy)->viewAny($agent));
    }

    public function test_target_policy_view_any_rejects_customer(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->assertFalse((new TargetPolicy)->viewAny($customer));
    }

    public function test_payout_request_policy_view_any_accepts_employee(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->assertTrue((new PayoutRequestPolicy)->viewAny($employee));
    }

    public function test_payout_request_policy_create_accepts_employee(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->assertTrue((new PayoutRequestPolicy)->create($employee));
    }

    public function test_payout_request_policy_create_rejects_vendor(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);

        $this->assertFalse((new PayoutRequestPolicy)->create($vendor));
    }
}
