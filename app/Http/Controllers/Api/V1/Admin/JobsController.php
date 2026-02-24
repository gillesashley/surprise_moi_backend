<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JobsController extends Controller
{
    public function logs(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 50);
        $page = $request->get('page', 1);

        // Get recent jobs from jobs table with pagination
        $jobs = DB::table('jobs')
            ->select([
                'id',
                'queue',
                'payload',
                'attempts',
                'reserved_at',
                'available_at',
                'created_at',
            ])
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        // Process job data to extract useful information
        $processedJobs = $jobs->getCollection()->map(function ($job) {
            $payload = json_decode($job->payload, true);

            return [
                'id' => $job->id,
                'queue' => $job->queue,
                'job' => $payload['displayName'] ?? 'Unknown Job',
                'data' => $this->extractJobData($payload),
                'attempts' => $job->attempts,
                'status' => $job->reserved_at ? 'processing' : 'pending',
                'created_at' => $job->created_at,
                'available_at' => $job->available_at,
                'reserved_at' => $job->reserved_at,
            ];
        });

        return response()->json([
            'jobs' => $processedJobs,
            'pagination' => [
                'current_page' => $jobs->currentPage(),
                'last_page' => $jobs->lastPage(),
                'per_page' => $jobs->perPage(),
                'total' => $jobs->total(),
            ],
        ]);
    }

    public function statistics(): JsonResponse
    {
        // Get job statistics
        $pendingJobs = DB::table('jobs')->whereNull('reserved_at')->count();
        $processingJobs = DB::table('jobs')->whereNotNull('reserved_at')->count();
        $failedJobs = DB::table('failed_jobs')->count();

        // Get job batches statistics
        $totalBatches = DB::table('job_batches')->count();
        $completedBatches = DB::table('job_batches')->whereNotNull('finished_at')->count();
        $failedBatches = DB::table('job_batches')->whereNotNull('failed_at')->count();

        // Calculate success rates
        $batchSuccessRate = $totalBatches > 0 ? round(($completedBatches / $totalBatches) * 100, 2) : 0;

        // Get recent activity (last 24 hours)
        $recentJobs = DB::table('jobs')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $recentFailedJobs = DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subDay())
            ->count();

        return response()->json([
            'statistics' => [
                'pending_jobs' => $pendingJobs,
                'processing_jobs' => $processingJobs,
                'failed_jobs' => $failedJobs,
                'total_jobs' => $pendingJobs + $processingJobs + $failedJobs,
                'total_batches' => $totalBatches,
                'completed_batches' => $completedBatches,
                'failed_batches' => $failedBatches,
                'batch_success_rate' => $batchSuccessRate,
                'recent_jobs_24h' => $recentJobs,
                'recent_failed_jobs_24h' => $recentFailedJobs,
            ],
            'timestamp' => now()->toISOString(),
        ]);
    }

    private function extractJobData(array $payload): array
    {
        $data = [];

        if (isset($payload['data'])) {
            $commandData = $payload['data'];

            // Extract common job data fields
            if (isset($commandData['id'])) {
                $data['id'] = $commandData['id'];
            }

            if (isset($commandData['email'])) {
                $data['email'] = $this->maskEmail($commandData['email']);
            }

            if (isset($commandData['phone'])) {
                $data['phone'] = $this->maskPhone($commandData['phone']);
            }

            // Add other relevant fields
            $data = array_merge($data, array_diff_key($commandData, array_flip(['email', 'phone'])));
        }

        return $data;
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) === 2) {
            $local = substr($parts[0], 0, 2).str_repeat('*', max(0, strlen($parts[0]) - 4)).substr($parts[0], -2);

            return $local.'@'.$parts[1];
        }

        return $email;
    }

    private function maskPhone(string $phone): string
    {
        $length = strlen($phone);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return substr($phone, 0, 3).str_repeat('*', $length - 6).substr($phone, -3);
    }
}
