<?php

namespace Tests\Feature\AuditLog;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AuthAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_audited(): void
    {
        $user = User::factory()->create();

        Event::dispatch(new Login('web', $user, false));

        $row = ActivityLog::where('event', 'login')->first();
        $this->assertNotNull($row);
        $this->assertSame($user->id, $row->causer_id);
        $this->assertSame('standard', $row->properties['retention_class']);
    }

    public function test_logout_is_audited(): void
    {
        $user = User::factory()->create();

        Event::dispatch(new Logout('web', $user));

        $this->assertDatabaseHas('activity_log', ['event' => 'logout', 'causer_id' => $user->id]);
    }

    public function test_failed_login_is_audited_with_null_causer(): void
    {
        Event::dispatch(new Failed('web', null, ['email' => 'noone@example.com']));

        $row = ActivityLog::where('event', 'login_failed')->first();
        $this->assertNotNull($row);
        $this->assertNull($row->causer_id);
        $this->assertSame('noone@example.com', $row->properties['extra']['email_attempted']);
    }

    public function test_password_reset_is_audited_with_critical_retention(): void
    {
        $user = User::factory()->create();

        Event::dispatch(new PasswordReset($user));

        $row = ActivityLog::where('event', 'password_reset')->first();
        $this->assertNotNull($row);
        $this->assertSame('critical', $row->properties['retention_class']);
    }
}
