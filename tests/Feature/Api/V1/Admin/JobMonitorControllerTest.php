<?php

namespace Tests\Feature\Api\V1\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JobMonitorControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a failed job for testing
        DB::table('failed_jobs')->insert([
            'uuid' => 'test-uuid-123',
            'connection' => 'redis',
            'queue' => 'emails',
            'payload' => json_encode(['job' => 'test-job']),
            'exception' => 'Test exception message',
            'failed_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function test_list_failed_jobs_returns_paginated_results(): void
    {
        $response = $this->getJson('/api/v1/admin/jobs/failed');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'failed_jobs' => [
                        '*' => [
                            'id',
                            'uuid',
                            'connection',
                            'queue',
                            'payload',
                            'exception',
                            'failed_at',
                        ],
                    ],
                    'pagination',
                ],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.failed_jobs.0.queue', 'emails');
    }

    public function test_list_failed_jobs_filters_by_queue(): void
    {
        // Create another failed job with different queue
        DB::table('failed_jobs')->insert([
            'uuid' => 'test-uuid-456',
            'connection' => 'redis',
            'queue' => 'tokens',
            'payload' => json_encode(['job' => 'test-job-2']),
            'exception' => 'Test exception message 2',
            'failed_at' => now(),
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/admin/jobs/failed?queue=emails');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $jobs = $response->json('data.failed_jobs');
        $this->assertEquals(1, count($jobs));
        $this->assertEquals('emails', $jobs[0]['queue']);
    }

    public function test_show_failed_job_returns_details(): void
    {
        $jobId = DB::table('failed_jobs')->first()->id;

        $response = $this->getJson("/api/v1/admin/jobs/failed/{$jobId}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'uuid',
                    'connection',
                    'queue',
                    'payload',
                    'exception',
                    'failed_at',
                    'created_at',
                ],
            ])
            ->assertJsonPath('success', true);
    }

    public function test_show_failed_job_returns_404_when_not_found(): void
    {
        $response = $this->getJson('/api/v1/admin/jobs/failed/999999');

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Failed job not found');
    }

    public function test_retry_failed_job_removes_from_table(): void
    {
        $jobId = DB::table('failed_jobs')->first()->id;
        $initialCount = DB::table('failed_jobs')->count();

        $response = $this->postJson("/api/v1/admin/jobs/failed/{$jobId}/retry");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Job re-enqueued successfully');

        $this->assertEquals($initialCount - 1, DB::table('failed_jobs')->count());
    }

    public function test_retry_all_failed_jobs_retries_multiple_jobs(): void
    {
        // Create multiple failed jobs
        DB::table('failed_jobs')->insert([
            [
                'uuid' => 'test-uuid-789',
                'connection' => 'redis',
                'queue' => 'emails',
                'payload' => json_encode(['job' => 'test-job-3']),
                'exception' => 'Test exception 3',
                'failed_at' => now(),
                'created_at' => now(),
            ],
            [
                'uuid' => 'test-uuid-101',
                'connection' => 'redis',
                'queue' => 'emails',
                'payload' => json_encode(['job' => 'test-job-4']),
                'exception' => 'Test exception 4',
                'failed_at' => now(),
                'created_at' => now(),
            ],
        ]);

        $initialCount = DB::table('failed_jobs')->count();

        $response = $this->postJson('/api/v1/admin/jobs/retry-all');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('count', $initialCount);

        $this->assertEquals(0, DB::table('failed_jobs')->count());
    }

    public function test_retry_all_with_queue_filter(): void
    {
        // Create jobs in different queues
        DB::table('failed_jobs')->insert([
            [
                'uuid' => 'test-uuid-111',
                'connection' => 'redis',
                'queue' => 'tokens',
                'payload' => json_encode(['job' => 'test-job-token']),
                'exception' => 'Test exception token',
                'failed_at' => now(),
                'created_at' => now(),
            ],
        ]);

        $response = $this->postJson('/api/v1/admin/jobs/retry-all?queue=emails');

        $response->assertOk()
            ->assertJsonPath('success', true);

        // Only email jobs should be retried
        $this->assertEquals(1, DB::table('failed_jobs')->count());
        $this->assertEquals('tokens', DB::table('failed_jobs')->first()->queue);
    }

    public function test_clear_all_failed_jobs(): void
    {
        $initialCount = DB::table('failed_jobs')->count();

        $response = $this->deleteJson('/api/v1/admin/jobs/clear');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('count', $initialCount);

        $this->assertEquals(0, DB::table('failed_jobs')->count());
    }

    public function test_clear_with_queue_filter(): void
    {
        // Create jobs in different queues
        DB::table('failed_jobs')->insert([
            [
                'uuid' => 'test-uuid-222',
                'connection' => 'redis',
                'queue' => 'tokens',
                'payload' => json_encode(['job' => 'test-job-token-2']),
                'exception' => 'Test exception token 2',
                'failed_at' => now(),
                'created_at' => now(),
            ],
        ]);

        $response = $this->deleteJson('/api/v1/admin/jobs/clear?queue=emails');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('count', 1);

        // Only email jobs should be cleared
        $this->assertEquals(1, DB::table('failed_jobs')->count());
        $this->assertEquals('tokens', DB::table('failed_jobs')->first()->queue);
    }

    public function test_stats_returns_queue_breakdown(): void
    {
        // Add more jobs to different queues
        DB::table('failed_jobs')->insert([
            [
                'uuid' => 'test-uuid-333',
                'connection' => 'redis',
                'queue' => 'tokens',
                'payload' => json_encode(['job' => 'test-job-5']),
                'exception' => 'Test exception 5',
                'failed_at' => now(),
                'created_at' => now(),
            ],
        ]);

        $response = $this->getJson('/api/v1/admin/jobs/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_failed',
                    'by_queue',
                    'recent_failures',
                ],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_failed', 2);
    }
}
