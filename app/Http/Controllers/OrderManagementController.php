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
            'filters' => [
                'search' => $request->input('search'),
                'status' => $request->input('status'),
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
            'vendor:id,name,email',
            'items.orderable',
            'deliveryAddress',
            'coupon',
            'latestPayment',
        ]);

        $allowedTransitions = self::TRANSITIONS[$order->status] ?? [];

        return Inertia::render('orders/show', [
            'order' => $order,
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
