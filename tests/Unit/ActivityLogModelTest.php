<?php

namespace Tests\Unit;

use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ActivityLogModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_created(): void
    {
        $row = ActivityLog::create([
            'log_name' => 'default',
            'description' => 'test',
            'event' => 'created',
            'properties' => ['retention_class' => 'standard'],
        ]);

        $this->assertDatabaseHas('activity_log', ['id' => $row->id]);
    }

    public function test_cannot_be_updated_after_creation(): void
    {
        $row = ActivityLog::create([
            'log_name' => 'default',
            'description' => 'test',
            'event' => 'created',
            'properties' => ['retention_class' => 'standard'],
        ]);

        $this->expectException(RuntimeException::class);

        $row->description = 'tampered';
        $row->save();
    }

    public function test_cannot_be_deleted(): void
    {
        $row = ActivityLog::create([
            'log_name' => 'default',
            'description' => 'test',
            'event' => 'created',
            'properties' => ['retention_class' => 'standard'],
        ]);

        $this->expectException(RuntimeException::class);

        $row->delete();
    }
}
