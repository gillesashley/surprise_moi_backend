<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPhoneUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_can_update_user_phone_number(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['phone' => '0240000001']);

        $response = $this->actingAs($admin)
            ->withSession(['user_management.verified_at' => time()])
            ->put(route('users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => '0240000002',
                'role' => $user->role,
            ]);

        $response->assertRedirect(route('users.show', $user));
        $this->assertSame('0240000002', $user->refresh()->phone);
    }

    public function test_admin_cannot_update_user_phone_to_duplicate(): void
    {
        $admin = User::factory()->admin()->create();
        $existingUser = User::factory()->create(['phone' => '0240000001']);
        $user = User::factory()->create(['phone' => '0240000002']);

        $response = $this->actingAs($admin)
            ->withSession(['user_management.verified_at' => time()])
            ->put(route('users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => '0240000001', // already taken by $existingUser
                'role' => $user->role,
            ]);

        $response->assertSessionHasErrors('phone');
        $this->assertSame('0240000002', $user->refresh()->phone);
    }

    public function test_admin_can_keep_same_phone_on_update(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['phone' => '0240000001']);

        $response = $this->actingAs($admin)
            ->withSession(['user_management.verified_at' => time()])
            ->put(route('users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => '0240000001', // same phone, should not fail unique check
                'role' => $user->role,
            ]);

        $response->assertRedirect(route('users.show', $user));
        $this->assertSame('0240000001', $user->refresh()->phone);
    }
}
