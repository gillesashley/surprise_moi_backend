<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupTestDataCommand extends Command
{
    protected $signature = 'app:cleanup-test-data
                            {--execute : Actually delete records (default is dry-run)}';

    protected $description = 'Remove orphaned transactional data from deleted test users';

    public function handle(): int
    {
        $isDryRun = ! $this->option('execute');

        if ($isDryRun) {
            $this->info('DRY RUN MODE — no records will be deleted.');
            $this->newLine();
        }

        // Find orphaned order IDs (user or vendor no longer exists)
        $orphanedOrderIds = DB::table('orders')
            ->leftJoin('users as customer', 'orders.user_id', '=', 'customer.id')
            ->leftJoin('users as vendor', 'orders.vendor_id', '=', 'vendor.id')
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('orders.user_id')
                        ->whereNull('customer.id');
                })->orWhere(function ($q2) {
                    $q2->whereNotNull('orders.vendor_id')
                        ->whereNull('vendor.id');
                });
            })
            ->pluck('orders.id')
            ->toArray();

        // Find orphaned vendor IDs (vendor_id not in users)
        $orphanedVendorIds = DB::table('vendor_balances')
            ->leftJoin('users', 'vendor_balances.vendor_id', '=', 'users.id')
            ->whereNull('users.id')
            ->pluck('vendor_balances.vendor_id')
            ->toArray();

        // Find orphaned user IDs in earnings/payouts
        $orphanedEarningUserIds = DB::table('earnings')
            ->leftJoin('users', 'earnings.user_id', '=', 'users.id')
            ->whereNull('users.id')
            ->pluck('earnings.user_id')
            ->unique()
            ->toArray();

        $orphanedPayoutUserIds = DB::table('payout_requests')
            ->leftJoin('users', 'payout_requests.user_id', '=', 'users.id')
            ->whereNull('users.id')
            ->pluck('payout_requests.user_id')
            ->unique()
            ->toArray();

        // Count what would be affected (deletion order: leaf tables first)
        $counts = [
            'coupon_usages' => DB::table('coupon_usages')->whereIn('order_id', $orphanedOrderIds)->count(),
            'order_items' => DB::table('order_items')->whereIn('order_id', $orphanedOrderIds)->count(),
            'payments' => DB::table('payments')->whereIn('order_id', $orphanedOrderIds)->count(),
            'vendor_transactions' => DB::table('vendor_transactions')
                ->where(function ($q) use ($orphanedOrderIds, $orphanedVendorIds) {
                    $q->whereIn('order_id', $orphanedOrderIds)
                        ->orWhereIn('vendor_id', $orphanedVendorIds);
                })->count(),
            'earnings' => DB::table('earnings')->whereIn('user_id', $orphanedEarningUserIds)->count(),
            'payout_requests' => DB::table('payout_requests')->whereIn('user_id', $orphanedPayoutUserIds)->count(),
            'vendor_balances' => DB::table('vendor_balances')->whereIn('vendor_id', $orphanedVendorIds)->count(),
            'orders' => count($orphanedOrderIds),
        ];

        $totalAffected = array_sum($counts);

        // Display summary
        $this->table(
            ['Table', 'Orphaned Records'],
            collect($counts)->map(fn ($count, $table) => [$table, $count])->toArray()
        );
        $this->newLine();
        $this->info("Total records affected: {$totalAffected}");

        if ($totalAffected === 0) {
            $this->info('No orphaned records found. Database is clean.');

            return Command::SUCCESS;
        }

        if ($isDryRun) {
            $this->newLine();
            $this->warn('To delete these records, run again with --execute:');
            $this->line('  php artisan app:cleanup-test-data --execute');

            return Command::SUCCESS;
        }

        // Confirmation prompt
        if (! $this->confirm('This will permanently delete the records above. Continue?')) {
            $this->info('Aborted.');

            return Command::SUCCESS;
        }

        // Execute deletion inside a transaction
        try {
            DB::transaction(function () use ($orphanedOrderIds, $orphanedVendorIds, $orphanedEarningUserIds, $orphanedPayoutUserIds, $counts) {
                if ($counts['coupon_usages'] > 0) {
                    DB::table('coupon_usages')->whereIn('order_id', $orphanedOrderIds)->delete();
                    $this->line("  Deleted {$counts['coupon_usages']} coupon_usages");
                }

                if ($counts['order_items'] > 0) {
                    DB::table('order_items')->whereIn('order_id', $orphanedOrderIds)->delete();
                    $this->line("  Deleted {$counts['order_items']} order_items");
                }

                if ($counts['payments'] > 0) {
                    DB::table('payments')->whereIn('order_id', $orphanedOrderIds)->delete();
                    $this->line("  Deleted {$counts['payments']} payments");
                }

                if ($counts['vendor_transactions'] > 0) {
                    DB::table('vendor_transactions')
                        ->where(function ($q) use ($orphanedOrderIds, $orphanedVendorIds) {
                            $q->whereIn('order_id', $orphanedOrderIds)
                                ->orWhereIn('vendor_id', $orphanedVendorIds);
                        })->delete();
                    $this->line("  Deleted {$counts['vendor_transactions']} vendor_transactions");
                }

                if ($counts['earnings'] > 0) {
                    DB::table('earnings')->whereIn('user_id', $orphanedEarningUserIds)->delete();
                    $this->line("  Deleted {$counts['earnings']} earnings");
                }

                if ($counts['payout_requests'] > 0) {
                    DB::table('payout_requests')->whereIn('user_id', $orphanedPayoutUserIds)->delete();
                    $this->line("  Deleted {$counts['payout_requests']} payout_requests");
                }

                if ($counts['vendor_balances'] > 0) {
                    DB::table('vendor_balances')->whereIn('vendor_id', $orphanedVendorIds)->delete();
                    $this->line("  Deleted {$counts['vendor_balances']} vendor_balances");
                }

                if ($counts['orders'] > 0) {
                    DB::table('orders')->whereIn('id', $orphanedOrderIds)->delete();
                    $this->line("  Deleted {$counts['orders']} orders");
                }
            });

            $this->newLine();
            $this->info('Cleanup completed successfully.');
            Log::info('Test data cleanup completed', $counts);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Cleanup failed — all changes rolled back.');
            $this->error($e->getMessage());
            Log::error('Test data cleanup failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
