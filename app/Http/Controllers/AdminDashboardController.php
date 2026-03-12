<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(): Response
    {
        // Get current month dates
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();
        $startOfLastMonth = now()->subMonth()->startOfMonth();
        $endOfLastMonth = now()->subMonth()->endOfMonth();

        // Calculate total users
        $totalUsers = User::count();
        $lastMonthUsers = User::where('created_at', '<', $startOfMonth)->count();
        $usersTrend = $lastMonthUsers > 0
            ? round((($totalUsers - $lastMonthUsers) / $lastMonthUsers) * 100, 1)
            : 0;

        // Calculate active orders (pending, confirmed, processing, fulfilled)
        $activeOrders = Order::whereIn('status', ['pending', 'confirmed', 'processing', 'fulfilled'])
            ->count();
        $lastWeekActiveOrders = Order::whereIn('status', ['pending', 'confirmed', 'processing', 'fulfilled'])
            ->where('created_at', '<', now()->subWeek())
            ->count();
        $activeOrdersTrend = $lastWeekActiveOrders > 0
            ? round((($activeOrders - $lastWeekActiveOrders) / $lastWeekActiveOrders) * 100, 1)
            : 0;

        // Calculate surprises sent (delivered orders this month)
        $surprisesSent = Order::where('status', 'delivered')
            ->whereBetween('delivered_at', [$startOfMonth, $endOfMonth])
            ->count();
        $lastMonthSurprises = Order::where('status', 'delivered')
            ->whereBetween('delivered_at', [$startOfLastMonth, $endOfLastMonth])
            ->count();
        $surprisesTrend = $lastMonthSurprises > 0
            ? round((($surprisesSent - $lastMonthSurprises) / $lastMonthSurprises) * 100, 1)
            : 0;

        // Calculate revenue (total from paid orders this month)
        $revenue = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('total');
        $lastMonthRevenue = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
            ->sum('total');
        $revenueTrend = $lastMonthRevenue > 0
            ? round((($revenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1)
            : 0;

        // Fetch pending vendor applications
        $pendingApplications = VendorApplication::with('user:id,name,email')
            ->whereIn('status', [
                VendorApplication::STATUS_PENDING,
                VendorApplication::STATUS_UNDER_REVIEW,
            ])
            ->latest('submitted_at')
            ->take(10)
            ->get(['id', 'user_id', 'status', 'submitted_at', 'current_step', 'completed_step']);

        return Inertia::render('dashboard', [
            'stats' => [
                'totalUsers' => [
                    'value' => $totalUsers,
                    'trend' => $usersTrend,
                    'trendUp' => $usersTrend >= 0,
                ],
                'activeOrders' => [
                    'value' => $activeOrders,
                    'trend' => $activeOrdersTrend,
                    'trendUp' => $activeOrdersTrend >= 0,
                ],
                'surprisesSent' => [
                    'value' => $surprisesSent,
                    'trend' => $surprisesTrend,
                    'trendUp' => $surprisesTrend >= 0,
                ],
                'revenue' => [
                    'value' => $revenue,
                    'trend' => $revenueTrend,
                    'trendUp' => $revenueTrend >= 0,
                ],
            ],
            'pendingApplications' => $pendingApplications->map(fn ($app) => [
                'id' => $app->id,
                'user' => [
                    'id' => $app->user->id,
                    'name' => $app->user->name,
                    'email' => $app->user->email,
                ],
                'status' => $app->status,
                'submitted_at' => $app->submitted_at?->toIso8601String(),
                'progress' => $app->completed_step.'/4',
            ]),
        ]);
    }

    public function commissionStatistics(): Response
    {
        $totalOrders = Order::where('payment_status', 'paid')->count();
        $totalSales = Order::where('payment_status', 'paid')->sum('total') ?? 0;
        $totalCommission = Order::where('payment_status', 'paid')->sum('platform_commission_amount') ?? 0;
        $totalPayouts = PayoutRequest::where('status', 'paid')->sum('amount') ?? 0;

        $averageCommissionRate = $totalOrders > 0 && $totalSales > 0
            ? (($totalCommission / $totalSales) * 100)
            : 0;

        // Commission by tier
        $tierBreakdown = Order::where('payment_status', 'paid')
            ->select(
                DB::raw("CASE WHEN platform_commission_amount / total * 100 >= 12 THEN 'Tier 1 (Registered Business)' ELSE 'Tier 2 (Limited Business)' END as tier_name"),
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(platform_commission_amount) as commission_earned')
            )
            ->groupBy('tier_name')
            ->get()
            ->map(fn ($item) => [
                'tier_name' => $item->tier_name,
                'order_count' => $item->order_count,
                'commission_earned' => (string) $item->commission_earned,
            ])
            ->toArray();

        // Top vendors
        $topVendors = Order::with('vendor:id,name')
            ->where('payment_status', 'paid')
            ->select('vendor_id', DB::raw('COUNT(*) as order_count'), DB::raw('SUM(total) as total_sales'), DB::raw('SUM(platform_commission_amount) as commission_generated'))
            ->groupBy('vendor_id')
            ->orderByDesc('commission_generated')
            ->take(5)
            ->get()
            ->map(fn ($item) => [
                'vendor_name' => $item->vendor->name ?? 'Unknown',
                'order_count' => $item->order_count,
                'total_sales' => (string) $item->total_sales,
                'commission_generated' => (string) $item->commission_generated,
            ])
            ->toArray();

        // Monthly trend (last 12 months)
        $trendData = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $orders = Order::where('payment_status', 'paid')
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->get();

            $monthOrders = $orders->count();
            $monthCommission = $orders->sum('platform_commission_amount') ?? 0;
            $monthSales = $orders->sum('total') ?? 0;
            $avgOrder = $monthOrders > 0 ? $monthSales / $monthOrders : 0;

            $trendData[] = [
                'month_label' => $date->format('M Y'),
                'order_count' => $monthOrders,
                'commission_earned' => (string) $monthCommission,
                'average_order_value' => (string) $avgOrder,
            ];
        }

        return Inertia::render('commission-statistics/index', [
            'stats' => [
                'summary' => [
                    'total_orders' => $totalOrders,
                    'total_order_value' => (string) $totalSales,
                    'total_commission_earned' => (string) $totalCommission,
                    'average_commission_rate' => number_format($averageCommissionRate, 1),
                    'total_vendor_payouts' => (string) $totalPayouts,
                ],
                'tier_breakdown' => $tierBreakdown,
                'top_vendors' => $topVendors,
                'trend_data' => $trendData,
            ],
        ]);
    }

    public function vendorPayouts(): Response
    {
        $status = request()->input('status', 'pending');

        $query = PayoutRequest::with(['user:id,name,email,phone', 'user.payoutDetails', 'processedBy:id,name']);

        if ($status !== 'all' && $status) {
            $query->where('status', $status);
        }

        $payouts = $query->latest()->paginate(15);

        $statistics = [
            'total_pending' => PayoutRequest::where('status', 'pending')->count(),
            'total_approved' => PayoutRequest::where('status', 'approved')->count(),
            'total_paid' => PayoutRequest::where('status', 'paid')->count(),
            'total_rejected' => PayoutRequest::where('status', 'rejected')->count(),
            'pending_amount' => PayoutRequest::where('status', 'pending')->sum('amount') ?? 0,
        ];

        return Inertia::render('vendor-payouts/index', [
            'initialData' => [
                'success' => true,
                'payouts' => [
                    'current_page' => $payouts->currentPage(),
                    'data' => collect($payouts->items())->map(fn ($payout) => [
                        'id' => $payout->id,
                        'request_number' => $payout->request_number,
                        'user_id' => $payout->user_id,
                        'user_name' => $payout->user->name ?? 'Unknown',
                        'user_email' => $payout->user->email ?? 'N/A',
                        'user_phone' => $payout->user->phone ?? 'N/A',
                        'user_role' => $payout->user_role,
                        'amount' => (string) $payout->amount,
                        'currency' => $payout->currency,
                        'status' => $payout->status,
                        'payout_method' => $payout->payout_method,
                        'mobile_money_number' => $payout->mobile_money_number,
                        'mobile_money_provider' => $payout->mobile_money_provider,
                        'created_at' => $payout->created_at->toIso8601String(),
                        'processed_by_name' => $payout->processedBy->name ?? null,
                        'processed_at' => $payout->processed_at?->toIso8601String(),
                        'vendor_payout_details' => ($payout->user?->payoutDetails ?? collect())->map(fn ($detail) => [
                            'id' => $detail->id,
                            'payout_method' => $detail->payout_method,
                            'account_name' => $detail->account_name,
                            'account_number' => $detail->account_number,
                            'bank_code' => $detail->bank_code,
                            'bank_name' => $detail->bank_name,
                            'provider' => $detail->provider,
                            'is_verified' => $detail->is_verified,
                            'is_default' => $detail->is_default,
                        ])->toArray(),
                    ])->toArray(),
                    'total' => $payouts->total(),
                    'per_page' => $payouts->perPage(),
                ],
                'statistics' => [
                    'total_pending' => $statistics['total_pending'],
                    'total_approved' => $statistics['total_approved'],
                    'total_paid' => $statistics['total_paid'],
                    'total_rejected' => $statistics['total_rejected'],
                    'pending_amount' => (string) $statistics['pending_amount'],
                ],
            ],
        ]);
    }

    public function allTransactions(): Response
    {
        $type = request()->input('type', 'order');
        $status = request()->input('status');
        $dateFrom = request()->input('date_from');
        $dateTo = request()->input('date_to');

        $transactions = collect();

        // Get orders (sales transactions)
        if ($type === 'all' || $type === 'order') {
            $ordersQuery = Order::with([
                'user:id,name,email,phone',
                'vendor:id,name,email,phone',
                'rider:id,name,phone',
                'items.orderable',
                'deliveryAddress',
            ]);

            if ($status && $status !== 'all') {
                $ordersQuery->where('status', $status);
            }

            if ($dateFrom) {
                $ordersQuery->whereDate('created_at', '>=', $dateFrom);
            }

            if ($dateTo) {
                $ordersQuery->whereDate('created_at', '<=', $dateTo);
            }

            $orders = $ordersQuery->latest()->paginate(20);

            $mappedOrders = $orders->through(fn ($order) => [
                'id' => $order->id,
                'type' => 'order',
                'transaction_number' => $order->order_number,
                'customer' => [
                    'name' => $order->user->name ?? 'Guest',
                    'email' => $order->user->email ?? null,
                    'phone' => $order->user->phone ?? null,
                ],
                'receiver' => [
                    'name' => $order->receiver_name ?? $order->user->name ?? 'Guest',
                    'phone' => $order->receiver_phone ?? $order->user->phone ?? null,
                    'address' => $order->deliveryAddress ? [
                        'label' => $order->deliveryAddress->label,
                        'address_line_1' => $order->deliveryAddress->address_line_1,
                        'address_line_2' => $order->deliveryAddress->address_line_2,
                        'city' => $order->deliveryAddress->city,
                        'state' => $order->deliveryAddress->state,
                    ] : null,
                ],
                'vendor' => [
                    'name' => $order->vendor->name ?? 'Unknown',
                    'email' => $order->vendor->email ?? null,
                    'phone' => $order->vendor->phone ?? null,
                ],
                'items' => $order->items->map(fn ($item) => [
                    'name' => $item->snapshot['name'] ?? ($item->orderable->name ?? 'Unknown Item'),
                    'type' => class_basename($item->orderable_type),
                    'quantity' => $item->quantity,
                    'unit_price' => (string) $item->unit_price,
                    'subtotal' => (string) $item->subtotal,
                ])->toArray(),
                'amount' => (string) $order->total,
                'subtotal' => (string) $order->subtotal,
                'delivery_fee' => (string) ($order->delivery_fee ?? 0),
                'discount_amount' => (string) ($order->discount_amount ?? 0),
                'platform_commission' => (string) ($order->platform_commission_amount ?? 0),
                'vendor_payout' => (string) ($order->vendor_payout_amount ?? 0),
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'delivery_method' => $order->delivery_method ?? 'vendor_self',
                'scheduled_datetime' => $order->scheduled_datetime?->toIso8601String(),
                'delivered_at' => $order->delivered_at?->toIso8601String(),
                'rider' => $order->rider ? [
                    'name' => $order->rider->name,
                    'phone' => $order->rider->phone,
                ] : null,
                'created_at' => $order->created_at->toIso8601String(),
                'description' => 'Order #'.$order->order_number,
            ]);

            return Inertia::render('transactions/index', [
                'orders' => $mappedOrders,
                'statistics' => $this->getOrderStatistics(),
                'filters' => [
                    'type' => $type,
                    'status' => $status,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                ],
            ]);
        }

        // Get payout requests (payout transactions)
        if ($type === 'payout') {
            $payoutsQuery = PayoutRequest::with(['user:id,name,email,phone']);

            if ($status && $status !== 'all') {
                $payoutsQuery->where('status', $status);
            }

            if ($dateFrom) {
                $payoutsQuery->whereDate('created_at', '>=', $dateFrom);
            }

            if ($dateTo) {
                $payoutsQuery->whereDate('created_at', '<=', $dateTo);
            }

            $payouts = $payoutsQuery->latest()->paginate(20);

            $mappedPayouts = $payouts->through(fn ($payout) => [
                'id' => $payout->id,
                'type' => 'payout',
                'transaction_number' => $payout->request_number,
                'customer' => [
                    'name' => $payout->user->name ?? 'Unknown',
                    'email' => $payout->user->email ?? null,
                    'phone' => $payout->user->phone ?? null,
                ],
                'receiver' => null,
                'vendor' => null,
                'items' => [],
                'amount' => (string) $payout->amount,
                'subtotal' => '0',
                'delivery_fee' => '0',
                'discount_amount' => '0',
                'platform_commission' => '0',
                'vendor_payout' => (string) $payout->amount,
                'status' => $payout->status,
                'payment_status' => null,
                'delivery_method' => null,
                'scheduled_datetime' => null,
                'delivered_at' => null,
                'rider' => null,
                'created_at' => $payout->created_at->toIso8601String(),
                'description' => 'Payout to '.($payout->user->name ?? 'Unknown'),
            ]);

            return Inertia::render('transactions/index', [
                'orders' => $mappedPayouts,
                'statistics' => $this->getOrderStatistics(),
                'filters' => [
                    'type' => $type,
                    'status' => $status,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                ],
            ]);
        }

        return Inertia::render('transactions/index', [
            'orders' => [],
            'statistics' => $this->getOrderStatistics(),
            'filters' => [
                'type' => $type,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function vendorOnboardingStats(): Response
    {
        $totalVendors = User::where('role', 'vendor')->count();
        $tier1Vendors = User::where('role', 'vendor')->where('vendor_tier', 1)->count();
        $tier2Vendors = User::where('role', 'vendor')->where('vendor_tier', 2)->count();

        $approvedApps = VendorApplication::where('status', VendorApplication::STATUS_APPROVED);

        $totalOnboardingFees = (clone $approvedApps)->sum('onboarding_fee');
        $tier1OnboardingFees = (clone $approvedApps)->where('has_business_certificate', true)->sum('onboarding_fee');
        $tier2OnboardingFees = (clone $approvedApps)->where('has_business_certificate', false)->sum('onboarding_fee');

        return Inertia::render('vendor-onboarding-stats/index', [
            'stats' => [
                'total_vendors' => $totalVendors,
                'tier1_vendors' => $tier1Vendors,
                'tier2_vendors' => $tier2Vendors,
                'total_onboarding_fees' => number_format($totalOnboardingFees, 2, '.', ''),
                'tier1_onboarding_fees' => number_format($tier1OnboardingFees, 2, '.', ''),
                'tier2_onboarding_fees' => number_format($tier2OnboardingFees, 2, '.', ''),
            ],
        ]);
    }

    private function getOrderStatistics(): array
    {
        return [
            'total_orders' => Order::count(),
            'total_sales' => (string) (Order::sum('total') ?? 0),
            'total_commission' => (string) (Order::sum('platform_commission_amount') ?? 0),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'delivered_orders' => Order::where('status', 'delivered')->count(),
            'total_payouts' => (string) (PayoutRequest::where('status', 'paid')->sum('amount') ?? 0),
            'pending_payouts' => (string) (PayoutRequest::where('status', 'pending')->sum('amount') ?? 0),
            'net_income' => (string) ((Order::sum('platform_commission_amount') ?? 0) - (PayoutRequest::where('status', 'paid')->sum('amount') ?? 0)),
        ];
    }
}
