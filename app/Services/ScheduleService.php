<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ScheduleService
{
    /**
     * Get schedule data by executing artisan command
     */
    public function getScheduleData(): array
    {
        try {
            // Execute the schedule:list command with timeout
            Artisan::call('schedule:list');
            $commandOutput = Artisan::output();

            return [
                'success' => true,
                'data' => $this->parseScheduleListOutput($commandOutput),
                'raw_output' => $commandOutput,
                'executed_at' => now()->toIso8601String(),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to fetch schedule list: '.$e->getMessage());

            return [
                'success' => false,
                'error' => 'Failed to fetch schedule information: '.$e->getMessage(),
                'data' => [],
                'executed_at' => now()->toIso8601String(),
            ];
        }
    }

    /**
     * Parse the output of php artisan schedule:list into structured data
     */
    public function parseScheduleListOutput(string $output = ''): array
    {
        $tasks = [];
        $lines = explode("\n", trim($output ?? ''));

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Skip header/empty lines and separator lines
            if (str_contains($line, 'Description') || str_contains($line, '────')) {
                continue;
            }

            // Parse the line - format is typically:
            // <cron expr>  <command> .. Next Due: <time>
            // Example: "  0  0 * * *  Closure at: routes/console.php:14 .. Next Due: 21 hours from now"

            // Find ".. Next Due:" delimiter
            if (str_contains($line, '.. Next Due:')) {
                $parts = explode('.. Next Due:', $line, 2);
                $commandPart = trim($parts[0]);
                $nextDuePart = trim($parts[1] ?? '');

                // Extract cron expression and command from the first part
                // Format: "<5 cron fields>  <command>"
                // Example: "0  0 * * *  Closure at: routes/console.php:14"
                // The cron expression has exactly 5 fields: minute hour day month weekday
                $words = preg_split('/\s+/', $commandPart);

                if (count($words) >= 6) {
                    // First 5 words are cron expression
                    $cronExpression = implode(' ', array_slice($words, 0, 5));
                    $command = trim(implode(' ', array_slice($words, 5)));
                    // Remove trailing dots/artisan padding
                    $command = preg_replace('/\s+\.+$/', '', $command);
                } else {
                    $cronExpression = '';
                    $command = $commandPart;
                }

                // Determine frequency from cron expression
                $frequency = $this->formatCronExpression($cronExpression);

                $tasks[] = [
                    'command' => $command,
                    'frequency' => $frequency,
                    'next_due' => $nextDuePart,
                    'overdue' => '',
                    'raw_line' => $line,
                ];
            }
        }

        return $tasks;
    }

    /**
     * Format cron expression to human-readable frequency
     */
    private function formatCronExpression(string $cron): string
    {
        $cron = trim($cron);

        $maps = [
            '* * * * *' => 'Every minute',
            '0 0 * * *' => 'Daily at midnight',
            '5 0 * * *' => 'Daily at 00:05',
            '0 2 * * *' => 'Daily at 2:00 AM',
            '30 2 * * *' => 'Daily at 2:30 AM',
            '0 * * * *' => 'Hourly',
            '0 */2 * * *' => 'Every 2 hours',
            '0 */6 * * *' => 'Every 6 hours',
            '0 */12 * * *' => 'Every 12 hours',
            '0 0 * * 0' => 'Weekly (Sunday)',
            '0 0 1 * *' => 'Monthly',
        ];

        return $maps[$cron] ?? $cron;
    }

    /**
     * Get schedule data with caching for performance
     */
    public function getScheduleDataWithCache(int $cacheMinutes = 5): array
    {
        $cacheKey = 'admin_schedule_data';

        // Try to get from cache first
        if (cache()->has($cacheKey)) {
            return cache()->get($cacheKey);
        }

        // Get fresh data
        $data = $this->getScheduleData();

        // Cache the data
        cache()->put($cacheKey, $data, now()->addMinutes($cacheMinutes));

        return $data;
    }

    /**
     * Clear the schedule data cache
     */
    public function clearScheduleCache(): void
    {
        $cacheKey = 'admin_schedule_data';
        cache()->forget($cacheKey);
    }

    /**
     * Validate if a command output looks like schedule list format
     */
    public function isValidScheduleOutput(string $output): bool
    {
        return ! empty(trim($output)) &&
               str_contains($output, '.. Next Due:');
    }
}
