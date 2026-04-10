<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserEarningCapableTest extends TestCase
{
    use RefreshDatabase;

    public function test_influencer_is_earning_capable(): void
    {
        $user = User::factory()->create(['role' => 'influencer']);
        $this->assertTrue($user->isEarningCapable());
    }

    public function test_field_agent_is_earning_capable(): void
    {
        $user = User::factory()->create(['role' => 'field_agent']);
        $this->assertTrue($user->isEarningCapable());
    }

    public function test_marketer_is_earning_capable(): void
    {
        $user = User::factory()->create(['role' => 'marketer']);
        $this->assertTrue($user->isEarningCapable());
    }

    public function test_customer_is_not_earning_capable(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->assertFalse($user->isEarningCapable());
    }

    public function test_vendor_is_not_earning_capable(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $this->assertFalse($user->isEarningCapable());
    }

    public function test_admin_is_not_earning_capable(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->assertFalse($user->isEarningCapable());
    }

    public function test_super_admin_is_not_earning_capable(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);
        $this->assertFalse($user->isEarningCapable());
    }

    public function test_null_role_is_not_earning_capable(): void
    {
        // role is NOT NULL in the DB, so we use make() to test the in-memory
        // null-role path without violating the DB constraint.
        $user = User::factory()->make(['role' => null]);
        $this->assertFalse($user->isEarningCapable());
    }
}
