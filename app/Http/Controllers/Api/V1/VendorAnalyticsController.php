<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorAnalyticsController extends Controller
{
    /**
     * Get vendor sales analytics for mobile dashboard.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'vendor' && $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Vendor access required.'], 403);
        }

        $vendorId = $user->role === 'admin' && $request->filled('vendor_id')
            ? $request->input('vendor_id')
            : $user->id;

        $validPeriods = ['today', 'week', 'month', 'year'];
        $period = in_array($request->input('period'), $validPeriods)
            ? $request->input('period')
            : 'month';
        $completedStatuses = ['fulfilled', 'delivered'];

        [$currentStart, $currentEnd] = $this->getMobileDateRange($period);
        [$previousStart, $previousEnd] = $this->getMobilePreviousDateRange($period);

        // Single query for current + previous period stats (replaces 4 queries)
        $stats = Order::query()
            ->where('vendor_id', $vendorId)
            ->whereIn('status', $completedStatuses)
            ->where(function ($q) use ($currentStart, $currentEnd, $previousStart, $previousEnd) {
                $q->whereBetween('created_at', [$currentStart, $currentEnd])
                    ->orWhereBetween('created_at', [$previousStart, $previousEnd]);
            })
            ->selectRaw('
                SUM(CASE WHEN created_at BETWEEN ? AND ? THEN total ELSE 0 END) as current_revenue,
                SUM(CASE WHEN created_at BETWEEN ? AND ? THEN total ELSE 0 END) as previous_revenue,
                COUNT(CASE WHEN created_at BETWEEN ? AND ? THEN 1 END) as current_orders,
                COUNT(CASE WHEN created_at BETWEEN ? AND ? THEN 1 END) as previous_orders
            ', [
                $currentStart, $currentEnd,
                $previousStart, $previousEnd,
                $currentStart, $currentEnd,
                $previousStart, $previousEnd,
            ])
            ->first();

        $currentRevenue = (float) ($stats->current_revenue ?? 0);
        $previousRevenue = (float) ($stats->previous_revenue ?? 0);
        $currentOrders = (int) ($stats->current_orders ?? 0);
        $previousOrders = (int) ($stats->previous_orders ?? 0);

        return response()->json([
            'data' => [
                'period' => $period,
                'revenue' => [
                    'total' => round((float) $currentRevenue, 2),
                    'previous_total' => round((float) $previousRevenue, 2),
                    'growth_percentage' => $this->calculateGrowth($currentRevenue, $previousRevenue),
                ],
                'orders' => [
                    'total' => $currentOrders,
                    'previous_total' => $previousOrders,
                    'growth_percentage' => $this->calculateGrowth($currentOrders, $previousOrders),
                ],
                'revenue_by_category' => $this->getMobileRevenueByCategory($vendorId, $currentStart, $currentEnd),
                'top_products' => $this->getMobileTopProducts($vendorId, $currentStart, $currentEnd),
            ],
        ]);
    }

    /**
     * Get overview statistics.
     */
    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'vendor' && $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Vendor access required.'], 403);
        }

        $vendorId = $user->role === 'admin' && $request->filled('vendor_id')
            ? $request->input('vendor_id')
            : $user->id;

        $period = $request->input('period', 'month');
        $dateRange = $this->getDateRange($period, $request);

        return response()->json([
            'success' => true,
            'data' => $this->getOverviewStats($vendorId, $dateRange),
        ]);
    }

    /**
     * Get revenue breakdown by category.
     */
    public function revenueByCategory(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'vendor' && $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Vendor access required.'], 403);
        }

        $vendorId = $user->role === 'admin' && $request->filled('vendor_id')
            ? $request->input('vendor_id')
            : $user->id;

        $period = $request->input('period', 'month');
        $dateRange = $this->getDateRange($period, $request);

        return response()->json([
            'success' => true,
            'data' => $this->getRevenueByCategory($vendorId, $dateRange),
        ]);
    }

    /**
     * Get top performing products.
     */
    public function topProducts(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'vendor' && $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Vendor access required.'], 403);
        }

        $vendorId = $user->role === 'admin' && $request->filled('vendor_id')
            ? $request->input('vendor_id')
            : $user->id;

        $period = $request->input('period', 'month');
        $limit = $request->input('limit', 10);
        $dateRange = $this->getDateRange($period, $request);

        return response()->json([
            'success' => true,
            'data' => $this->getTopProducts($vendorId, $dateRange, $limit),
        ]);
    }

    /**
     * Get sales trends over time.
     */
    public function trends(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'vendor' && $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Vendor access required.'], 403);
        }

        $vendorId = $user->role === 'admin' && $request->filled('vendor_id')
            ? $request->input('vendor_id')
            : $user->id;

        $period = $request->input('period', 'month');
        $dateRange = $this->getDateRange($period, $request);

        return response()->json([
            'success' => true,
            'data' => [
                'orders' => $this->getOrdersTrend($vendorId, $dateRange),
                'revenue' => $this->getRevenueTrend($vendorId, $dateRange),
            ],
        ]);
    }

    /**
     * Get overview stats including progress, revenue, orders.
     *
     * @param  array{start: Carbon, end: Carbon}  $dateRange
     * @return array<string, mixed>
     */
    protected function getOverviewStats(int $vendorId, array $dateRange): array
    {
        $completedStatuses = ['fulfilled', 'delivered'];

        // Current period stats
        $currentPeriodQuery = Order::query()
            ->where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);

        $totalRevenue = (clone $currentPeriodQuery)
            ->whereIn('status', $completedStatuses)
            ->sum('total');

        $totalOrders = (clone $currentPeriodQuery)->count();

        $completedOrders = (clone $currentPeriodQuery)
            ->whereIn('status', $completedStatuses)
            ->count();

        $averageOrderValue = $completedOrders > 0
            ? round($totalRevenue / $completedOrders, 2)
            : 0;

        // Today's stats
        $todayStart = Carbon::today();
        $todayEnd = Carbon::today()->endOfDay();

        $todayQuery = Order::query()
            ->where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$todayStart, $todayEnd]);

        $todayRevenue = (clone $todayQuery)
            ->whereIn('status', $completedStatuses)
            ->sum('total');

        $todayOrders = (clone $todayQuery)->count();

        // Previous period stats for comparison
        $previousDateRange = $this->getPreviousPeriodRange($dateRange);

        $previousPeriodQuery = Order::query()
            ->where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$previousDateRange['start'], $previousDateRange['end']]);

        $previousRevenue = (clone $previousPeriodQuery)
            ->whereIn('status', $completedStatuses)
            ->sum('total');

        $previousOrders = (clone $previousPeriodQuery)->count();

        // Calculate percentage changes
        $revenueChange = $previousRevenue > 0
            ? round((($totalRevenue - $previousRevenue) / $previousRevenue) * 100, 1)
            : ($totalRevenue > 0 ? 100 : 0);

        $ordersChange = $previousOrders > 0
            ? round((($totalOrders - $previousOrders) / $previousOrders) * 100, 1)
            : ($totalOrders > 0 ? 100 : 0);

        // Monthly target (can be customized per vendor in future)
        $monthlyTarget = $this->getMonthlyTarget($vendorId);
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $monthlyRevenue = Order::query()
            ->where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->whereIn('status', $completedStatuses)
            ->sum('total');

        $targetProgress = $monthlyTarget > 0
            ? min(round(($monthlyRevenue / $monthlyTarget) * 100), 100)
            : 0;

        return [
            'monthly_target' => [
                'target' => $monthlyTarget,
                'achieved' => round($monthlyRevenue, 2),
                'progress_percentage' => $targetProgress,
                'currency' => 'GHS',
            ],
            'today' => [
                'revenue' => round($todayRevenue, 2),
                'orders' => $todayOrders,
                'currency' => 'GHS',
            ],
            'period' => [
                'revenue' => round($totalRevenue, 2),
                'orders' => $totalOrders,
                'completed_orders' => $completedOrders,
                'average_order_value' => $averageOrderValue,
                'currency' => 'GHS',
            ],
            'changes' => [
                'revenue_percentage' => $revenueChange,
                'orders_percentage' => $ordersChange,
            ],
            'order_status_breakdown' => $this->getOrderStatusBreakdown($vendorId, $dateRange),
        ];
    }

    /**
     * Get order counts by status.
     *
     * @param  array{start: Carbon, end: Carbon}  $dateRange
     * @return array<string, int>
     */
    protected function getOrderStatusBreakdown(int $vendorId, array $dateRange): array
    {
        $statuses = ['pending', 'confirmed', 'processing', 'fulfilled', 'shipped', 'delivered', 'refunded'];

        // Single query with GROUP BY instead of 7 separate COUNT queries
        $counts = Order::query()
            ->where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->whereIn('status', $statuses)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // Ensure all statuses are present in the result (default 0)
        $breakdown = [];
        foreach ($statuses as $status) {
            $breakdown[$status] = (int) ($counts[$status] ?? 0);
        }

        return $breakdown;
    }

    /**
     * Get revenue breakdown by category.
     *
     * @param  array{start: Carbon, end: Carbon}  $dateRange
     * @return array<int, array<string, mixed>>
     */
    protected function getRevenueByCategory(int $vendorId, array $dateRange): array
    {
        $completedStatuses = ['fulfilled', 'delivered'];

        // Get orders with items for this vendor in the date range
        $categoryRevenue = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.vendor_id', $vendorId)
            ->whereBetween('orders.created_at', [$dateRange['start'], $dateRange['end']])
            ->whereIn('orders.status', $completedStatuses)
            ->where('order_items.orderable_type', Product::class)
            ->join('products', 'order_items.orderable_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select(
                'categories.id',
                'categories.name',
                'categories.icon',
                DB::raw('SUM(order_items.subtotal) as total_revenue'),
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('COUNT(DISTINCT orders.id) as order_count')
            )
            ->groupBy('categories.id', 'categories.name', 'categories.icon')
            ->orderByDesc('total_revenue')
            ->get();

        // Calculate services revenue (bespoke services)
        $servicesRevenue = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.vendor_id', $vendorId)
            ->whereBetween('orders.created_at', [$dateRange['start'], $dateRange['end']])
            ->whereIn('orders.status', $completedStatuses)
            ->where('order_items.orderable_type', Service::class)
            ->select(
                DB::raw('SUM(order_items.subtotal) as total_revenue'),
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('COUNT(DISTINCT orders.id) as order_count')
            )
            ->first();

        $results = $categoryRevenue->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'icon' => $category->icon,
                'revenue' => round((float) $category->total_revenue, 2),
                'quantity' => (int) $category->total_quantity,
                'order_count' => (int) $category->order_count,
                'currency' => 'GHS',
            ];
        })->toArray();

        // Add bespoke services if any
        if ($servicesRevenue && $servicesRevenue->total_revenue > 0) {
            $results[] = [
                'id' => null,
                'name' => 'Bespoke Services',
                'icon' => 'bespoke',
                'revenue' => round((float) $servicesRevenue->total_revenue, 2),
                'quantity' => (int) $servicesRevenue->total_quantity,
                'order_count' => (int) $servicesRevenue->order_count,
                'currency' => 'GHS',
            ];
        }

        // Calculate total for percentage
        $totalRevenue = array_sum(array_column($results, 'revenue'));

        return array_map(function ($item) use ($totalRevenue) {
            $item['percentage'] = $totalRevenue > 0
                ? round(($item['revenue'] / $totalRevenue) * 100, 1)
                : 0;

            return $item;
        }, $results);
    }

    /**
     * Get top performing products.
     *
     * @param  array{start: Carbon, end: Carbon}  $dateRange
     * @return array<int, array<string, mixed>>
     */
    protected function getTopProducts(int $vendorId, array $dateRange, int $limit = 10): array
    {
        $completedStatuses = ['fulfilled', 'delivered'];

        $topProducts = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.vendor_id', $vendorId)
            ->whereBetween('orders.created_at', [$dateRange['start'], $dateRange['end']])
            ->whereIn('orders.status', $completedStatuses)
            ->where('order_items.orderable_type', Product::class)
            ->join('products', 'order_items.orderable_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->select(
                'products.id',
                'products.name',
                'products.thumbnail',
                'categories.name as category_name',
                DB::raw('SUM(order_items.subtotal) as total_revenue'),
                DB::raw('SUM(order_items.quantity) as total_orders'),
                DB::raw('COUNT(DISTINCT orders.id) as order_count'),
                DB::raw('AVG(order_items.subtotal) as avg_order_value')
            )
            ->groupBy('products.id', 'products.name', 'products.thumbnail', 'categories.name')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get();

        return $topProducts->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'thumbnail' => $product->thumbnail,
                'category' => $product->category_name,
                'revenue' => round((float) $product->total_revenue, 2),
                'orders' => (int) $product->total_orders,
                'order_count' => (int) $product->order_count,
                'average_order_value' => round((float) $product->avg_order_value, 2),
                'currency' => 'GHS',
            ];
        })->toArray();
    }

    /**
     * Get orders trend data.
     *
     * @param  array{start: Carbon, end: Carbon}  $dateRange
     * @return array<int, array<string, mixed>>
     */
    protected function getOrdersTrend(int $vendorId, array $dateRange): array
    {
        $dateExpression = $this->getDateGroupExpression($dateRange);

        $orders = Order::query()
            ->where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->select(
                DB::raw("{$dateExpression} as period"),
                DB::raw('COUNT(*) as total_orders'),
                DB::raw("COUNT(CASE WHEN status IN ('fulfilled', 'shipped', 'delivered') THEN 1 END) as completed_orders")
            )
            ->groupBy(DB::raw($dateExpression))
            ->orderBy('period')
            ->get();

        return $orders->map(function ($item) {
            return [
                'period' => $item->period,
                'total' => (int) $item->total_orders,
                'completed' => (int) $item->completed_orders,
            ];
        })->toArray();
    }

    /**
     * Get revenue trend data.
     *
     * @param  array{start: Carbon, end: Carbon}  $dateRange
     * @return array<int, array<string, mixed>>
     */
    protected function getRevenueTrend(int $vendorId, array $dateRange): array
    {
        $dateExpression = $this->getDateGroupExpression($dateRange);
        $completedStatuses = ['fulfilled', 'delivered'];

        $revenue = Order::query()
            ->where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->whereIn('status', $completedStatuses)
            ->select(
                DB::raw("{$dateExpression} as period"),
                DB::raw('SUM(total) as revenue'),
                DB::raw('AVG(total) as average_order_value'),
                DB::raw('COUNT(*) as order_count')
            )
            ->groupBy(DB::raw($dateExpression))
            ->orderBy('period')
            ->get();

        return $revenue->map(function ($item) {
            return [
                'period' => $item->period,
                'revenue' => round((float) $item->revenue, 2),
                'average_order_value' => round((float) $item->average_order_value, 2),
                'order_count' => (int) $item->order_count,
                'currency' => 'GHS',
            ];
        })->toArray();
    }

    /**
     * Get the database-agnostic date grouping expression.
     *
     * @param  array{start: Carbon, end: Carbon}  $dateRange
     */
    protected function getDateGroupExpression(array $dateRange): string
    {
        $days = $dateRange['start']->diffInDays($dateRange['end']);
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            if ($days <= 1) {
                return "TO_CHAR(created_at, 'YYYY-MM-DD HH24:00')";
            } elseif ($days <= 31) {
                return "TO_CHAR(created_at, 'YYYY-MM-DD')";
            } elseif ($days <= 365) {
                return "TO_CHAR(created_at, 'IYYY-IW')";
            } else {
                return "TO_CHAR(created_at, 'YYYY-MM')";
            }
        } elseif ($driver === 'mysql') {
            if ($days <= 1) {
                return "DATE_FORMAT(created_at, '%Y-%m-%d %H:00')";
            } elseif ($days <= 31) {
                return 'DATE(created_at)';
            } elseif ($days <= 365) {
                return "DATE_FORMAT(created_at, '%x-%v')";
            } else {
                return "DATE_FORMAT(created_at, '%Y-%m')";
            }
        } else {
            // SQLite fallback
            if ($days <= 1) {
                return "strftime('%Y-%m-%d %H:00', created_at)";
            } elseif ($days <= 31) {
                return 'date(created_at)';
            } elseif ($days <= 365) {
                return "strftime('%Y-%W', created_at)";
            } else {
                return "strftime('%Y-%m', created_at)";
            }
        }
    }

    /**
     * Get the date range based on period.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    protected function getDateRange(string $period, Request $request): array
    {
        // Custom date range
        if ($request->filled('start_date') && $request->filled('end_date')) {
            return [
                'start' => Carbon::parse($request->input('start_date'))->startOfDay(),
                'end' => Carbon::parse($request->input('end_date'))->endOfDay(),
            ];
        }

        $now = Carbon::now();

        return match ($period) {
            'day' => [
                'start' => $now->copy()->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
            'week' => [
                'start' => $now->copy()->startOfWeek(),
                'end' => $now->copy()->endOfWeek(),
            ],
            'month' => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth(),
            ],
            'quarter' => [
                'start' => $now->copy()->startOfQuarter(),
                'end' => $now->copy()->endOfQuarter(),
            ],
            'year' => [
                'start' => $now->copy()->startOfYear(),
                'end' => $now->copy()->endOfYear(),
            ],
            default => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth(),
            ],
        };
    }

    /**
     * Get the previous period range for comparison.
     *
     * @param  array{start: Carbon, end: Carbon}  $currentRange
     * @return array{start: Carbon, end: Carbon}
     */
    protected function getPreviousPeriodRange(array $currentRange): array
    {
        $duration = $currentRange['start']->diffInDays($currentRange['end']) + 1;

        return [
            'start' => $currentRange['start']->copy()->subDays($duration),
            'end' => $currentRange['start']->copy()->subDay(),
        ];
    }

    /**
     * Get the monthly target for a vendor.
     * This can be customized per vendor in the future.
     */
    protected function getMonthlyTarget(int $vendorId): float
    {
        // Calculate based on previous months average (last 3 months)
        $threeMonthsAgo = Carbon::now()->subMonths(3)->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();
        $completedStatuses = ['fulfilled', 'delivered'];

        $averageMonthlyRevenue = Order::query()
            ->where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$threeMonthsAgo, $lastMonthEnd])
            ->whereIn('status', $completedStatuses)
            ->avg(DB::raw('total * 1.0'));

        // Set target as 110% of average (10% growth target)
        // Minimum target of 1000 GHS
        $calculatedTarget = ($averageMonthlyRevenue ?? 0) * 1.10;

        return max($calculatedTarget, 1000);
    }

    /**
     * Calculate growth percentage between two values.
     */
    protected function calculateGrowth(float|int $current, float|int $previous): float
    {
        if ($previous == 0) {
            return 0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    /**
     * Get current date range for mobile analytics periods.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function getMobileDateRange(string $period): array
    {
        return match ($period) {
            'today' => [Carbon::today(), Carbon::now()],
            'week' => [Carbon::now()->startOfWeek(), Carbon::now()],
            'year' => [Carbon::now()->startOfYear(), Carbon::now()],
            default => [Carbon::now()->startOfMonth(), Carbon::now()],
        };
    }

    /**
     * Get previous date range for mobile analytics comparison periods.
     * Calendar-aligned: yesterday, last week, last month, last year.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function getMobilePreviousDateRange(string $period): array
    {
        return match ($period) {
            'today' => [Carbon::yesterday()->startOfDay(), Carbon::yesterday()->endOfDay()],
            'week' => [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()],
            'year' => [Carbon::now()->subYear()->startOfYear(), Carbon::now()->subYear()->endOfYear()],
            default => [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()],
        };
    }

    /**
     * Get revenue by category for mobile analytics.
     * Top 5 categories by revenue desc, includes "Bespoke Services" bucket.
     *
     * @return array<int, array{category_id: int|null, category_name: string, revenue: float}>
     */
    protected function getMobileRevenueByCategory(int $vendorId, Carbon $start, Carbon $end): array
    {
        $completedStatuses = ['fulfilled', 'delivered'];

        $categoryRevenue = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.vendor_id', $vendorId)
            ->whereBetween('orders.created_at', [$start, $end])
            ->whereIn('orders.status', $completedStatuses)
            ->where('order_items.orderable_type', Product::class)
            ->join('products', 'order_items.orderable_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select(
                'categories.id as category_id',
                'categories.name as category_name',
                DB::raw('SUM(order_items.subtotal) as revenue')
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => [
                'category_id' => (int) $row->category_id,
                'category_name' => $row->category_name,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->toArray();

        $servicesRevenue = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.vendor_id', $vendorId)
            ->whereBetween('orders.created_at', [$start, $end])
            ->whereIn('orders.status', $completedStatuses)
            ->where('order_items.orderable_type', Service::class)
            ->select(DB::raw('SUM(order_items.subtotal) as revenue'))
            ->first();

        if ($servicesRevenue && (float) $servicesRevenue->revenue > 0) {
            $categoryRevenue[] = [
                'category_id' => null,
                'category_name' => 'Bespoke Services',
                'revenue' => round((float) $servicesRevenue->revenue, 2),
            ];

            usort($categoryRevenue, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);
        }

        return array_slice($categoryRevenue, 0, 5);
    }

    /**
     * Get top products/services for mobile analytics.
     * Top 5 by revenue desc, merges Products and Services.
     *
     * @return array<int, array{id: int, name: string, category: string, image_url: string|null, revenue: float, orders_count: int, average_order_value: float}>
     */
    protected function getMobileTopProducts(int $vendorId, Carbon $start, Carbon $end): array
    {
        $completedStatuses = ['fulfilled', 'delivered'];

        $products = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.vendor_id', $vendorId)
            ->whereBetween('orders.created_at', [$start, $end])
            ->whereIn('orders.status', $completedStatuses)
            ->where('order_items.orderable_type', Product::class)
            ->join('products', 'order_items.orderable_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->select(
                'products.id',
                'products.name',
                'categories.name as category',
                'products.thumbnail as image_url',
                DB::raw('SUM(order_items.subtotal) as revenue'),
                DB::raw('COUNT(DISTINCT orders.id) as orders_count')
            )
            ->groupBy('products.id', 'products.name', 'categories.name', 'products.thumbnail')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'category' => $row->category,
                'image_url' => $row->image_url,
                'revenue' => round((float) $row->revenue, 2),
                'orders_count' => (int) $row->orders_count,
                'average_order_value' => (int) $row->orders_count > 0
                    ? round((float) $row->revenue / (int) $row->orders_count, 2)
                    : 0,
            ])
            ->toArray();

        $services = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.vendor_id', $vendorId)
            ->whereBetween('orders.created_at', [$start, $end])
            ->whereIn('orders.status', $completedStatuses)
            ->where('order_items.orderable_type', Service::class)
            ->join('services', 'order_items.orderable_id', '=', 'services.id')
            ->select(
                'services.id',
                'services.name',
                'services.thumbnail as image_url',
                DB::raw('SUM(order_items.subtotal) as revenue'),
                DB::raw('COUNT(DISTINCT orders.id) as orders_count')
            )
            ->groupBy('services.id', 'services.name', 'services.thumbnail')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'category' => 'Bespoke Services',
                'image_url' => $row->image_url,
                'revenue' => round((float) $row->revenue, 2),
                'orders_count' => (int) $row->orders_count,
                'average_order_value' => (int) $row->orders_count > 0
                    ? round((float) $row->revenue / (int) $row->orders_count, 2)
                    : 0,
            ])
            ->toArray();

        $merged = array_merge($products, $services);
        usort($merged, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        return array_slice($merged, 0, 5);
    }
}
