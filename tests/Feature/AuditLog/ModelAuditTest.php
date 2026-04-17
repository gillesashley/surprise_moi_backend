<?php

namespace Tests\Feature\AuditLog;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_user_writes_audit_row(): void
    {
        $user = User::factory()->create(['name' => 'Alice']);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'event' => 'created',
        ]);
    }

    public function test_updating_user_writes_audit_row_with_old_and_new(): void
    {
        $user = User::factory()->create(['name' => 'Alice']);
        ActivityLog::query()->delete(); // prune create row; query builder bypasses model's PreventModification

        $user->update(['name' => 'Alicia']);

        $row = ActivityLog::where('subject_id', $user->id)->latest('id')->first();
        $this->assertSame('updated', $row->event);
        $props = $row->properties->toArray();
        $this->assertSame('Alice', $props['old']['name']);
        $this->assertSame('Alicia', $props['attributes']['name']);
    }

    public function test_deleting_user_writes_audit_row_with_critical_retention(): void
    {
        $user = User::factory()->create();
        $user->delete();

        $row = ActivityLog::where('subject_id', $user->id)->where('event', 'deleted')->first();
        $this->assertNotNull($row);
        $this->assertSame('critical', $row->properties['retention_class']);
    }

    public function test_password_is_redacted(): void
    {
        $user = User::factory()->create();
        $user->update(['password' => bcrypt('newsecret')]);

        $row = ActivityLog::where('subject_id', $user->id)->where('event', 'updated')->latest('id')->first();
        $props = $row->properties->toArray();
        $this->assertArrayNotHasKey('password', $props['attributes'] ?? []);
        $this->assertArrayNotHasKey('password', $props['old'] ?? []);
    }
}
