<?php

namespace Tests\Unit\Jobs;

use App\Jobs\BaseJob;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\TestCase;

class BaseJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();
    }

    public function test_base_job_has_correct_defaults(): void
    {
        $job = new class extends BaseJob
        {
            public function executeJob(): void
            {
                // Test implementation
            }
        };

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(60, $job->timeout);
        $this->assertEquals([60, 180, 300], $job->backoff);
    }

    public function test_base_job_queue_can_be_set(): void
    {
        $job = new class extends BaseJob
        {
            public function executeJob(): void
            {
                // Test implementation
            }
        };

        $this->assertEquals('default', $job->queue);
    }

    public function test_base_job_extends_should_queue(): void
    {
        $job = new class extends BaseJob
        {
            public function executeJob(): void
            {
                // Test implementation
            }
        };

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }

    public function test_get_job_id_returns_null_when_no_job_set(): void
    {
        $job = new class extends BaseJob
        {
            public function executeJob(): void
            {
                // Test implementation
            }
        };

        $this->assertNull($job->getJobId());
    }

    public function test_get_display_name_returns_class_basename(): void
    {
        $job = new class extends BaseJob
        {
            public function executeJob(): void
            {
                // Test implementation
            }
        };

        $this->assertEquals('class', $job->getDisplayName());
    }
}
