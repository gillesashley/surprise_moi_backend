<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlatformCommissionController extends Controller
{
    /**
     * Get platform commission earnings and statistics.
     * Only accessible by admins and super_admins.
     */
    public function index(Request $request): JsonResponse
    {
        // Verify user is admin or super_admin
        if (! in_array($request->user()->role, ['admin', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        // Get date filters
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $period = $request->input('period', 'all'); // all, today, week, month, year

        // Build query for orders with commissions
        $query = Order::whereNotNull('platform_commission_amount')
            ->where('payment_status', 'paid'); // Only count paid orders

        // Apply period filter
        switch ($period) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
                break;
            case 'year':
                $query->whereYear('created_at', now()->year);
                break;
            case 'custom':
                if ($startDate) {
                    $query->whereDate('created_at', '>=', $startDate);
                }
                if ($endDate) {
                    $query->whereDate('created_at', '<=', $endDate);
                }
                break;
        }

        // Calculate commission statistics
        $stats = $query->selectRaw('
            COUNT(*) as total_orders,
            SUM(total) as total_order_value,
            SUM(platform_commission_amount) as total_commission_earned,
            AVG(platform_commission_rate) as average_commission_rate,
            SUM(vendor_payout_amount) as total_vendor_payouts
        ')->first();

        // Get commission breakdown by vendor tier
        $tierBreakdown = Order::select('users.vendor_tier')
            ->selectRaw('
                COUNT(orders.id) as order_count,
                SUM(orders.platform_commission_amount) as commission_earned,
                AVG(orders.platform_commission_rate) as avg_commission_rate
            ')
            ->join('users', 'orders.vendor_id', '=', 'users.id')
            ->whereNotNull('orders.platform_commission_amount')
            ->where('orders.payment_status', 'paid')
            ->when($period !== 'all', function ($q) use ($period, $startDate, $endDate) {
                switch ($period) {
                    case 'today':
                        return $q->whereDate('orders.created_at', today());
                    case 'week':
                        return $q->whereBetween('orders.created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                    case 'month':
                        return $q->whereMonth('orders.created_at', now()->month)
                            ->whereYear('orders.created_at', now()->year);
                    case 'year':
                        return $q->whereYear('orders.created_at', now()->year);
                    case 'custom':
                        if ($startDate) {
                            $q->whereDate('orders.created_at', '>=', $startDate);
                        }
                        if ($endDate) {
                            $q->whereDate('orders.created_at', '<=', $endDate);
                        }

                        return $q;
                }
            })
            ->groupBy('users.vendor_tier')
            ->get()
            ->map(function ($item) {
                return [
                    'tier' => $item->vendor_tier,
                    'tier_name' => $item->vendor_tier === 1 ? 'Tier 1 (Registered Business)' : 'Tier 2 (Individual Vendor)',
                    'order_count' => (int) $item->order_count,
                    'commission_earned' => (float) $item->commission_earned,
                    'average_commission_rate' => (float) $item->avg_commission_rate,
                ];
            });

        // Get top earning vendors (by commission generated)
        $topVendors = Order::select('users.id', 'users.name', 'users.vendor_tier')
            ->selectRaw('
                COUNT(orders.id) as order_count,
                SUM(orders.total) as total_sales,
                SUM(orders.platform_commission_amount) as commission_generated
            ')
            ->join('users', 'orders.vendor_id', '=', 'users.id')
            ->whereNotNull('orders.platform_commission_amount')
            ->where('orders.payment_status', 'paid')
            ->when($period !== 'all', function ($q) use ($period, $startDate, $endDate) {
                switch ($period) {
                    case 'today':
                        return $q->whereDate('orders.created_at', today());
                    case 'week':
                        return $q->whereBetween('orders.created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                    case 'month':
                        return $q->whereMonth('orders.created_at', now()->month)
                            ->whereYear('orders.created_at', now()->year);
                    case 'year':
                        return $q->whereYear('orders.created_at', now()->year);
                    case 'custom':
                        if ($startDate) {
                            $q->whereDate('orders.created_at', '>=', $startDate);
                        }
                        if ($endDate) {
                            $q->whereDate('orders.created_at', '<=', $endDate);
                        }

                        return $q;
                }
            })
            ->groupBy('users.id', 'users.name', 'users.vendor_tier')
            ->orderByDesc('commission_generated')
            ->limit(10)
            ->get()
            ->map(function ($vendor) {
                return [
                    'vendor_id' => (int) $vendor->id,
                    'vendor_name' => $vendor->name,
                    'vendor_tier' => $vendor->vendor_tier,
                    'order_count' => (int) $vendor->order_count,
                    'total_sales' => (float) $vendor->total_sales,
                    'commission_generated' => (float) $vendor->commission_generated,
                ];
            });

        return response()->json([
            'data' => [
                'summary' => [
                    'total_orders' => (int) $stats->total_orders,
                    'total_order_value' => (float) $stats->total_order_value,
                    'total_commission_earned' => (float) $stats->total_commission_earned,
                    'average_commission_rate' => (float) $stats->average_commission_rate,
                    'total_vendor_payouts' => (float) $stats->total_vendor_payouts,
                ],
                'tier_breakdown' => $tierBreakdown,
                'top_vendors' => $topVendors,
                'period' => $period,
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
            ],
        ]);
    }

    /**
     * Get monthly commission trend data for charts.
     */
    public function monthlyTrend(Request $request): JsonResponse
    {
        // Verify user is admin or super_admin
        if (! in_array($request->user()->role, ['admin', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $months = $request->input('months', 12); // Last N months

        $trend = DB::table('orders')
            ->selectRaw("
                DATE_TRUNC('month', created_at) as month,
                COUNT(*) as order_count,
                SUM(total) as total_sales,
                SUM(platform_commission_amount) as commission_earned,
                AVG(total) as average_order_value
            ")
            ->whereNotNull('platform_commission_amount')
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', now()->subMonths($months))
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get()
            ->map(function ($item) {
                $monthDate = \Carbon\Carbon::parse($item->month);

                return [
                    'month' => $monthDate->format('Y-m'),
                    'month_label' => $monthDate->format('M Y'),
                    'order_count' => (int) $item->order_count,
                    'total_sales' => (float) $item->total_sales,
                    'commission_earned' => (float) $item->commission_earned,
                    'average_order_value' => round((float) $item->average_order_value, 2),
                ];
            });

        return response()->json([
            'data' => [
                'trend_data' => $trend,
                'summary' => [
                    'total_months' => $trend->count(),
                    'total_commission' => $trend->sum('commission_earned'),
                    'total_orders' => $trend->sum('order_count'),
                    'average_monthly_commission' => $trend->count() > 0 ? round($trend->avg('commission_earned'), 2) : 0,
                ],
            ],
        ]);
    }
}
