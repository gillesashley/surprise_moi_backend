<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\VendorAnalyticsResource;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class VendorAnalyticsController extends Controller
{
    /**
     * Get comprehensive vendor analytics dashboard data.
     *
     * Returns the single JSON object needed to render the vendor sales analytics dashboard.
     * Results are cached for 5 minutes per vendor to avoid heavy DB load on every load.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'vendor' && $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Vendor access required.'], 403);
        }

        $vendorId = $user->role === 'admin' && $request->filled('vendor_id')
            ? (int) $request->input('vendor_id')
            : $user->id;

        $data = Cache::remember("vendor_analytics_dashboard_{$vendorId}", 300, function () use ($vendorId) {
            return $this->computeDashboardAnalytics($vendorId);
        });

        return response()->json((new VendorAnalyticsResource($data))->toArray($request));
    }

    /**
     * Compute all raw data needed for the dashboard analytics response.
     *
     * @return array<string, mixed>
     */
    private function computeDashboardAnalytics(int $vendorId): array
    {
        $completedStatuses = ['fulfilled', 'delivered'];
        $today           = Carbon::today();
        $todayEnd        = Carbon::today()->endOfDay();
        $lastWeekDay     = Carbon::today()->subWeek();
        $lastWeekDayEnd  = Carbon::today()->subWeek()->endOfDay();
        $monthStart      = Carbon::now()->startOfMonth();
        $monthEnd        = Carbon::now()->endOfMonth();

        $dateRange = ['start' => $monthStart, 'end' => $monthEnd];

        // Today's completed revenue
        $todayRevenue = (float) Order::query()
            ->where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$today, $todayEnd])
            ->whereIn('status', $completedStatuses)
            ->sum('total');

        // Today's total order count (all statuses)
        $todayOrders = Order::query()
            ->where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$today, $todayEnd])
            ->count();

        // Same calendar day last week — comparison baseline
        $lastWeekRevenue = (float) Order::query()
            ->where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$lastWeekDay, $lastWeekDayEnd])
            ->whereIn('status', $completedStatuses)
            ->sum('total');

        $lastWeekOrders = Order::query()
            ->where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$lastWeekDay, $lastWeekDayEnd])
            ->count();

        // Monthly progress towards target
        $monthlyTarget = $this->getMonthlyTarget($vendorId);

        $monthlyRevenue = (float) Order::query()
            ->where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->whereIn('status', $completedStatuses)
            ->sum('total');

        return [
            'monthly_target'      => round($monthlyTarget, 2),
            'monthly_revenue'     => round($monthlyRevenue, 2),
            'today_revenue'       => round($todayRevenue, 2),
            'last_week_revenue'   => round($lastWeekRevenue, 2),
            'today_orders'        => $todayOrders,
            'last_week_orders'    => $lastWeekOrders,
            'revenue_by_category' => $this->getRevenueByCategory($vendorId, $dateRange),
            'top_products'        => $this->getTopProducts($vendorId, $dateRange, 5),
        ];
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
     * Get order counts by status using a single grouped query.
     *
     * @param  array{start: Carbon, end: Carbon}  $dateRange
     * @return array<string, int>
     */
    protected function getOrderStatusBreakdown(int $vendorId, array $dateRange): array
    {
        $statuses = ['pending', 'confirmed', 'processing', 'fulfilled', 'shipped', 'delivered', 'refunded'];

        $counts = Order::query()
            ->where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->whereIn('status', $statuses)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

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
                DB::raw('COUNT(DISTINCT orders.id) as order_count')
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
                'average_order_value' => $product->order_count > 0
                    ? round((float) $product->total_revenue / $product->order_count, 2)
                    : 0,
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
}
