<?php

namespace App\Services;

use App\Models\VendorApplication;
use App\Models\VendorOnboardingPayment;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AdminNotificationFeedService
{
    /**
     * Build the system-wide admin notification feed.
     *
     * @param  array<int, string>  $categories  subset of ['vendor_onboarding','tier_upgrade','field_agent']; empty means all
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function feed(array $categories = [], int $perPage = 30, int $page = 1): LengthAwarePaginator
    {
        $rows = $this->vendorApplicationRows();

        $rows = $rows
            ->sortByDesc(fn (array $row) => [$row['occurred_at'], $row['id']])
            ->values();

        return new LengthAwarePaginator(
            items: $rows->forPage($page, $perPage)->values()->all(),
            total: $rows->count(),
            perPage: $perPage,
            currentPage: $page,
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function vendorApplicationRows(): Collection
    {
        $applications = VendorApplication::query()
            ->with([
                'user:id,name',
                'onboardingPayments' => fn ($q) => $q->where('status', VendorOnboardingPayment::STATUS_SUCCESS),
            ])
            ->get();

        return $applications->flatMap(function (VendorApplication $app): array {
            $rows = [];
            $actor = $app->user ? ['id' => $app->user->id, 'name' => $app->user->name] : null;
            $tierLabel = $app->has_business_certificate ? 'Tier 1 (Business)' : 'Tier 2 (Individual)';
            $subject = [
                'id' => $app->id,
                'type' => 'vendor_application',
                'label' => trim(($app->user?->name ?? 'Unknown vendor').' — '.$tierLabel),
            ];
            $url = "/dashboard/vendor-applications/{$app->id}";

            $emit = function (string $type, ?Carbon $at) use (&$rows, $app, $actor, $subject, $url): void {
                if ($at === null) {
                    return;
                }
                $rows[] = [
                    'id' => "vendor_application:{$app->id}:{$type}",
                    'category' => 'vendor_onboarding',
                    'type' => $type,
                    'occurred_at' => $at->toIso8601String(),
                    'actor' => $actor,
                    'subject' => $subject,
                    'action_url' => $url,
                ];
            };

            $emit('submitted', $app->submitted_at);

            $successfulPayment = $app->onboardingPayments->first();
            $emit('paid', $successfulPayment?->paid_at);

            if ($app->status === VendorApplication::STATUS_APPROVED) {
                $emit('approved', $app->updated_at);
            }
            if ($app->status === VendorApplication::STATUS_REJECTED) {
                $emit('rejected', $app->updated_at);
            }

            $emit('flagged', $app->flagged_at);
            $emit('flag_reminded', $app->flag_reminder_sent_at);
            $emit('flag_expired', $app->flag_expired_alert_sent_at);

            return $rows;
        });
    }
}
