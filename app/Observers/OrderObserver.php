<?php

namespace App\Observers;

use App\Models\Order;
use App\Notifications\OrderStatusChanged;

class OrderObserver
{
    /** @var string[] */
    private const NOTIFIABLE_STATUSES = [
        'confirmed',
        'processing',
        'fulfilled',
        'shipped',
        'delivered',
        'refunded',
    ];

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        $newStatus = $order->status;

        if (! in_array($newStatus, self::NOTIFIABLE_STATUSES)) {
            return;
        }

        $order->user->notify(new OrderStatusChanged($order, $newStatus));
    }
}
