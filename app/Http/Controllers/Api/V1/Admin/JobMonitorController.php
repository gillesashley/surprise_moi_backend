<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class JobMonitorController extends Controller
{
    /**
     * List failed jobs with optional filtering by queue.
     */
    public function index(Request $request): JsonResponse
    {
        $queue = $request->get('queue');
        $perPage = $request->get('per_page', 20);
        $page = $request->get('page', 1);

        $query = DB::table('failed_jobs')
            ->orderBy('failed_at', 'desc');

        if ($queue) {
            $query->where('queue', $queue);
        }

        $total = $query->count();
        $failedJobs = $query->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'failed_jobs' => $failedJobs->map(function ($job) {
                    return [
                        'id' => $job->id,
                        'uuid' => $job->uuid,
                        'connection' => $job->connection,
                        'queue' => $job->queue,
                        'payload' => json_decode($job->payload, true),
                        'exception' => $job->exception,
                        'failed_at' => $job->failed_at,
                    ];
                }),
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => ceil($total / $perPage),
                ],
            ],
        ]);
    }

    /**
     * Show a specific failed job with full details.
     */
    public function show(string $id): JsonResponse
    {
        $failedJob = DB::table('failed_jobs')
            ->where('id', $id)
            ->first();

        if (! $failedJob) {
            return response()->json([
                'success' => false,
                'message' => 'Failed job not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $failedJob->id,
                'uuid' => $failedJob->uuid,
                'connection' => $failedJob->connection,
                'queue' => $failedJob->queue,
                'payload' => json_decode($failedJob->payload, true),
                'exception' => $failedJob->exception,
                'failed_at' => $failedJob->failed_at,
                'created_at' => $failedJob->created_at ?? null,
            ],
        ]);
    }

    /**
     * Retry a specific failed job.
     */
    public function retry(string $id): JsonResponse
    {
        $failedJob = DB::table('failed_jobs')
            ->where('id', $id)
            ->first();

        if (! $failedJob) {
            return response()->json([
                'success' => false,
                'message' => 'Failed job not found',
            ], 404);
        }

        try {
            Queue::connection($failedJob->connection)->pushRaw(
                $failedJob->payload,
                $failedJob->queue
            );

            DB::table('failed_jobs')
                ->where('id', $id)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Job re-enqueued successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry job: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retry all failed jobs for a specific queue.
     */
    public function retryAll(Request $request): JsonResponse
    {
        $queue = $request->get('queue');

        $query = DB::table('failed_jobs');

        if ($queue) {
            $query->where('queue', $queue);
        }

        $failedJobs = $query->get();

        if ($failedJobs->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No failed jobs to retry',
                'count' => 0,
            ]);
        }

        $retryCount = 0;
        $errors = [];

        foreach ($failedJobs as $job) {
            try {
                Queue::connection($job->connection)->pushRaw(
                    $job->payload,
                    $job->queue
                );

                DB::table('failed_jobs')
                    ->where('id', $job->id)
                    ->delete();

                $retryCount++;
            } catch (\Exception $e) {
                $errors[] = [
                    'job_id' => $job->id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Retried {$retryCount} job(s)",
            'count' => $retryCount,
            'errors' => $errors,
        ]);
    }

    /**
     * Get queue statistics.
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_failed' => DB::table('failed_jobs')->count(),
            'by_queue' => DB::table('failed_jobs')
                ->select('queue', DB::raw('count(*) as count'))
                ->groupBy('queue')
                ->pluck('count', 'queue')
                ->toArray(),
            'recent_failures' => DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subHours(24))
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Clear all failed jobs (use with caution).
     */
    public function clear(Request $request): JsonResponse
    {
        $queue = $request->get('queue');

        $query = DB::table('failed_jobs');

        if ($queue) {
            $query->where('queue', $queue);
        }

        $count = $query->count();
        $query->delete();

        return response()->json([
            'success' => true,
            'message' => "Cleared {$count} failed job(s)",
        ]);
    }
}
