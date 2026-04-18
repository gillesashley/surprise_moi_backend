<?php

namespace Tests\Feature\AuditLog;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        DB::statement('TRUNCATE TABLE activity_log RESTART IDENTITY CASCADE');

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

    public function test_vendor_application_update_logs_with_critical_retention(): void
    {
        $app = \App\Models\VendorApplication::factory()->create();
        DB::statement('TRUNCATE TABLE activity_log RESTART IDENTITY CASCADE');

        $app->update(['rejection_reason' => 'test reason']);

        $row = ActivityLog::where('subject_type', \App\Models\VendorApplication::class)
            ->where('subject_id', $app->id)
            ->latest('id')->first();
        $this->assertNotNull($row);
        $this->assertSame('critical', $row->properties['retention_class']);
    }

    public function test_setting_create_is_not_logged(): void
    {
        \App\Models\Setting::create(['key' => 'test_key_no_log', 'value' => 'v', 'type' => 'string']);

        $this->assertDatabaseMissing('activity_log', [
            'subject_type' => \App\Models\Setting::class,
            'event' => 'created',
        ]);
    }

    public function test_setting_update_is_logged(): void
    {
        $s = \App\Models\Setting::create(['key' => 'test_key_logged', 'value' => 'v1', 'type' => 'string']);
        DB::statement('TRUNCATE TABLE activity_log RESTART IDENTITY CASCADE');

        $s->update(['value' => 'v2']);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => \App\Models\Setting::class,
            'subject_id' => $s->id,
            'event' => 'updated',
        ]);
    }

    public function test_order_create_is_not_logged(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $order = \App\Models\Order::factory()->create(['user_id' => $customer->id]);

        $this->assertDatabaseMissing('activity_log', [
            'subject_type' => \App\Models\Order::class,
            'subject_id' => $order->id,
            'event' => 'created',
        ]);
    }

    public function test_order_update_is_logged_with_standard_retention(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $order = \App\Models\Order::factory()->create(['user_id' => $customer->id, 'status' => 'pending']);
        DB::statement('TRUNCATE TABLE activity_log RESTART IDENTITY CASCADE');

        $order->update(['status' => 'confirmed']);

        $row = ActivityLog::where('subject_id', $order->id)->latest('id')->first();
        $this->assertSame('updated', $row->event);
        $this->assertSame('standard', $row->properties['retention_class']);
    }
}
