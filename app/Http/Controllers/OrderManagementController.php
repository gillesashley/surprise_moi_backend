<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Services\VendorBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderManagementController extends Controller
{
    /** @var string[] */
    private const STATUSES = [
        'pending',
        'confirmed',
        'processing',
        'fulfilled',
        'shipped',
        'delivered',
        'refunded',
    ];

    /**
     * Allowed status transitions map.
     *
     * @var array<string, string[]>
     */
    private const TRANSITIONS = [
        'pending' => ['confirmed'],
        'confirmed' => ['processing', 'fulfilled'],
        'processing' => ['fulfilled'],
        'fulfilled' => ['shipped'],
        'shipped' => ['delivered'],
    ];

    public function __construct(private VendorBalanceService $vendorBalanceService) {}

    /**
     * Display a paginated list of orders with search, status filter, and sorting.
     */
    /** @var string[] */
    private const DELIVERY_METHODS = [
        'vendor_self',
        'platform_rider',
        'third_party_courier',
    ];

    public function index(Request $request): Response
    {
        $query = Order::query()
            ->with(['user:id,name,email', 'vendor:id,name', 'items']);

        // Search by order number or customer name
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'ilike', "%{$search}%")
                    ->orWhereIn('user_id', User::query()
                        ->where('name', 'ilike', "%{$search}%")
                        ->select('id'));
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by delivery method
        if ($request->filled('delivery_method')) {
            $query->where('delivery_method', $request->input('delivery_method'));
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $allowedSorts = ['total', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->latest();
        }

        $orders = $query->paginate(15)->withQueryString();

        return Inertia::render('orders/index', [
            'orders' => $orders,
            'statuses' => self::STATUSES,
            'deliveryMethods' => self::DELIVERY_METHODS,
            'filters' => [
                'search' => $request->input('search'),
                'status' => $request->input('status'),
                'delivery_method' => $request->input('delivery_method'),
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    /**
     * Display the order detail page with allowed status transitions.
     */
    public function show(Order $order): Response
    {
        $order->load([
            'user:id,name,email,phone',
            'vendor:id,name,email,phone',
            'items.orderable',
            'deliveryAddress',
            'latestPayment',
        ]);

        $allowedTransitions = self::TRANSITIONS[$order->status] ?? [];

        $latestDeliveryRequest = $order->deliveryRequests()
            ->with('assignedRider')
            ->latest()
            ->first();

        return Inertia::render('orders/show', [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'currency' => $order->currency ?? 'GHS',
                'subtotal' => (string) $order->subtotal,
                'discount_amount' => (string) ($order->discount_amount ?? '0.00'),
                'delivery_fee' => (string) ($order->delivery_fee ?? '0.00'),
                'delivery_method' => $order->delivery_method,
                'total' => (string) $order->total,
                'platform_commission_amount' => (string) ($order->platform_commission_amount ?? '0.00'),
                'vendor_payout_amount' => (string) ($order->vendor_payout_amount ?? '0.00'),
                'customer' => $order->user ? [
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                    'phone' => $order->user->phone ?? null,
                ] : null,
                'vendor' => $order->vendor ? [
                    'name' => $order->vendor->name,
                    'email' => $order->vendor->email,
                    'phone' => $order->vendor->phone ?? null,
                ] : null,
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->snapshot['name'] ?? ($item->orderable->name ?? 'Unknown Item'),
                    'thumbnail' => $item->snapshot['thumbnail'] ?? ($item->orderable->thumbnail ?? null),
                    'quantity' => $item->quantity,
                    'unit_price' => (string) $item->unit_price,
                    'subtotal' => (string) $item->subtotal,
                ])->toArray(),
                'delivery_address' => $order->deliveryAddress ? [
                    'address_line_1' => $order->deliveryAddress->address_line_1,
                    'city' => $order->deliveryAddress->city,
                    'state' => $order->deliveryAddress->state,
                    'postal_code' => $order->deliveryAddress->postal_code,
                    'country' => $order->deliveryAddress->country,
                ] : null,
                'receiver_name' => $order->receiver_name,
                'receiver_phone' => $order->receiver_phone,
                'special_instructions' => $order->special_instructions,
                'occasion' => $order->occasion,
                'delivery_request' => $latestDeliveryRequest ? [
                    'id' => $latestDeliveryRequest->id,
                    'status' => $latestDeliveryRequest->status,
                    'rider_name' => $latestDeliveryRequest->assignedRider?->name,
                    'rider_phone' => $latestDeliveryRequest->assignedRider?->phone,
                    'pickup_address' => $latestDeliveryRequest->pickup_address,
                    'pickup_latitude' => (float) $latestDeliveryRequest->pickup_latitude,
                    'pickup_longitude' => (float) $latestDeliveryRequest->pickup_longitude,
                    'dropoff_address' => $latestDeliveryRequest->dropoff_address,
                    'dropoff_latitude' => (float) $latestDeliveryRequest->dropoff_latitude,
                    'dropoff_longitude' => (float) $latestDeliveryRequest->dropoff_longitude,
                    'delivery_fee' => (string) ($latestDeliveryRequest->delivery_fee ?? '0.00'),
                    'distance_km' => (string) ($latestDeliveryRequest->distance_km ?? '0.00'),
                    'created_at' => $latestDeliveryRequest->created_at?->toIso8601String(),
                    'accepted_at' => $latestDeliveryRequest->accepted_at?->toIso8601String(),
                    'picked_up_at' => $latestDeliveryRequest->picked_up_at?->toIso8601String(),
                    'delivered_at' => $latestDeliveryRequest->delivered_at?->toIso8601String(),
                ] : null,
                'payment' => $order->latestPayment ? [
                    'reference' => $order->latestPayment->reference,
                    'status' => $order->latestPayment->status,
                    'channel' => $order->latestPayment->channel,
                    'amount' => (string) $order->latestPayment->amount,
                    'currency' => $order->latestPayment->currency ?? 'GHS',
                    'paid_at' => $order->latestPayment->paid_at?->toIso8601String(),
                ] : null,
                'created_at' => $order->created_at?->toIso8601String(),
                'confirmed_at' => $order->confirmed_at?->toIso8601String(),
                'fulfilled_at' => $order->fulfilled_at?->toIso8601String(),
                'shipped_at' => $order->shipped_at?->toIso8601String(),
                'delivered_at' => $order->delivered_at?->toIso8601String(),
            ],
            'allowedTransitions' => $allowedTransitions,
        ]);
    }

    /**
     * Update the status of an order based on allowed transitions.
     */
    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $allowedTransitions = self::TRANSITIONS[$order->status] ?? [];

        $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', $allowedTransitions)],
        ]);

        $newStatus = $request->input('status');

        match ($newStatus) {
            'confirmed' => $order->markAsConfirmed(),
            'processing' => $order->update(['status' => 'processing']),
            'fulfilled' => $order->markAsFulfilled(),
            'shipped' => $order->markAsShipped(),
            'delivered' => $order->markAsDelivered(),
        };

        // Release funds for fulfilled/shipped/delivered if order is paid and funds not yet released
        if (in_array($newStatus, ['fulfilled', 'shipped', 'delivered'])
            && $order->isPaid()
            && ! $order->funds_released) {
            $this->vendorBalanceService->releaseFunds($order);
            $order->update(['funds_released' => true]);
        }

        return redirect()->back()->with('success', "Order status updated to {$newStatus}.");
    }
}
