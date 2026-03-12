<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class CleanupTestOrders extends Command
{
    /**
     * @var string
     */
    protected $signature = 'app:cleanup-test-orders';

    /**
     * @var string
     */
    protected $description = 'Soft-delete test orders from the database (IDs 93-106)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $orderIds = range(93, 106);

        $orders = Order::whereIn('id', $orderIds)->get();

        if ($orders->isEmpty()) {
            $this->info('No test orders found to clean up.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Order Number', 'Total', 'Payment Status', 'Status', 'Created At'],
            $orders->map(fn (Order $order) => [
                $order->id,
                $order->order_number,
                'GHS '.$order->total,
                $order->payment_status,
                $order->status,
                $order->created_at->toDateTimeString(),
            ])
        );

        if (! $this->confirm("Soft-delete these {$orders->count()} test orders?")) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $deleted = Order::whereIn('id', $orderIds)->delete();

        $this->info("Successfully soft-deleted {$deleted} test orders.");

        return self::SUCCESS;
    }
}
