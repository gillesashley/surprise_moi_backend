<?php

namespace Tests\Unit;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\AuditService;
use App\Support\AuditContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_minimal_event(): void
    {
        $user = User::factory()->create();

        (new AuditService)->record('login', $user, $user);

        $row = ActivityLog::where('event', 'login')->first();
        $this->assertNotNull($row);
        $this->assertSame(User::class, $row->subject_type);
        $this->assertSame($user->id, $row->subject_id);
        $this->assertSame(User::class, $row->causer_type);
        $this->assertSame($user->id, $row->causer_id);
        $this->assertSame('standard', $row->properties['retention_class']);
    }

    public function test_picks_up_context_metadata(): void
    {
        AuditContext::set('10.0.0.1', 'TestAgent/1.0');
        $user = User::factory()->create();

        (new AuditService)->record('login', $user, $user);

        $row = ActivityLog::where('event', 'login')->first();
        $this->assertSame('10.0.0.1', $row->properties['ip']);
        $this->assertSame('TestAgent/1.0', $row->properties['user_agent']);

        AuditContext::forget();
    }

    public function test_null_causer_is_allowed(): void
    {
        $user = User::factory()->create();

        (new AuditService)->record('system.reconciled', $user, null, retentionClass: 'critical');

        $row = ActivityLog::where('event', 'system.reconciled')->first();
        $this->assertNull($row->causer_id);
        $this->assertSame('critical', $row->properties['retention_class']);
    }
}
