<?php

namespace Tests\Unit\Actions\Rider;

use App\Actions\Rider\ProvisionShadowRiderAction;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProvisionShadowRiderActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_shadow_rider_for_a_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'name' => 'Ash Admin',
            'email' => 'ash@example.com',
            'phone' => '+233200000001',
        ]);

        $rider = (new ProvisionShadowRiderAction)($admin);

        $this->assertSame($admin->id, $rider->user_id);
        $this->assertSame('Ash Admin', $rider->name);
        $this->assertSame('ash@example.com', $rider->email);
        $this->assertSame('+233200000001', $rider->phone);
        $this->assertSame('approved', $rider->status);
        $this->assertSame('motorbike', $rider->vehicle_category);
        $this->assertTrue((bool) $rider->is_active);
    }

    public function test_it_synthesizes_phone_when_admin_has_none(): void
    {
        $admin = User::factory()->superAdmin()->create(['phone' => null]);

        $rider = (new ProvisionShadowRiderAction)($admin);

        $this->assertSame("admin-{$admin->id}", $rider->phone);
    }

    public function test_it_is_idempotent(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $first = (new ProvisionShadowRiderAction)($admin);
        $second = (new ProvisionShadowRiderAction)($admin);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Rider::where('user_id', $admin->id)->count());
    }
}
