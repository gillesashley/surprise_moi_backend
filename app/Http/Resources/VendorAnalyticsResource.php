<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class VendorAnalyticsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Expects $this->resource to be an array produced by
     * VendorAnalyticsController::computeDashboardAnalytics().
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{
         *   monthly_target: float,
         *   monthly_revenue: float,
         *   today_revenue: float,
         *   last_week_revenue: float,
         *   today_orders: int,
         *   last_week_orders: int,
         *   revenue_by_category: array<int, array<string, mixed>>,
         *   top_products: array<int, array<string, mixed>>,
         * } $data
         */
        $data = $this->resource;

        return [
            'progress' => [
                'monthly_target_amount'   => $data['monthly_target'],
                'monthly_revenue_so_far'  => $data['monthly_revenue'],
                'progress_percentage'     => $data['monthly_target'] > 0
                    ? min((int) round(($data['monthly_revenue'] / $data['monthly_target']) * 100), 100)
                    : 0,
                'revenue_label'           => $this->formatCompact($data['monthly_revenue']),
            ],
            'kpis' => [
                'todays_revenue'                  => $data['today_revenue'],
                'todays_revenue_formatted'        => $this->formatFull($data['today_revenue']),
                'todays_revenue_change_percent'   => $this->changePercent($data['today_revenue'], $data['last_week_revenue']),
                'todays_revenue_is_positive'      => $data['today_revenue'] >= $data['last_week_revenue'],
                'todays_orders'                   => $data['today_orders'],
                'todays_orders_change_percent'    => $this->changePercent($data['today_orders'], $data['last_week_orders']),
                'todays_orders_is_positive'       => $data['today_orders'] >= $data['last_week_orders'],
            ],
            'revenue_by_category' => array_map(
                fn (array $cat) => [
                    'category'          => $cat['name'],
                    'revenue'           => $cat['revenue'],
                    'revenue_formatted' => $this->formatCompact($cat['revenue']),
                ],
                $data['revenue_by_category']
            ),
            'top_products' => array_map(
                fn (array $p) => [
                    'id'                           => $p['id'],
                    'name'                         => $p['name'],
                    'category'                     => $p['category'],
                    'total_revenue'                => $p['revenue'],
                    'total_revenue_formatted'      => $this->formatFull($p['revenue']),
                    'orders_count'                 => $p['order_count'],
                    'average_order_value'          => $p['average_order_value'],
                    'average_order_value_formatted'=> $this->formatFull($p['average_order_value']),
                    'image_url'                    => $this->productImageUrl($p['thumbnail']),
                ],
                $data['top_products']
            ),
        ];
    }

    /**
     * Format an amount as a compact GHS string (e.g. "GHS 42.5k" for thousands).
     */
    private function formatCompact(float $amount): string
    {
        if ($amount >= 1000) {
            $k = $amount / 1000;
            $formatted = rtrim(rtrim(number_format($k, 1), '0'), '.');

            return "GHS {$formatted}k";
        }

        return 'GHS '.number_format($amount, 0);
    }

    /**
     * Format an amount as a full GHS string with thousands separator (e.g. "GHS 1,278").
     */
    private function formatFull(float $amount): string
    {
        return 'GHS '.number_format($amount, 0);
    }

    /**
     * Calculate percentage change from a previous value to a current value.
     */
    private function changePercent(float|int $current, float|int $previous): float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Build a public URL for a product thumbnail stored on the public disk.
     * Strips any leading "storage/" prefix to avoid a double "storage/storage/" path.
     */
    private function productImageUrl(?string $thumbnail): ?string
    {
        if (! $thumbnail) {
            return null;
        }

        $path = preg_replace('#^storage/#', '', $thumbnail);

        return Storage::disk('public')->url($path);
    }
}
