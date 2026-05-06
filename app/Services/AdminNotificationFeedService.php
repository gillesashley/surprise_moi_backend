<?php

namespace App\Services;

use App\Enums\FieldAgentApplicationStatus;
use App\Models\FieldAgentApplication;
use App\Models\TierUpgradeRequest;
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
        $allowed = ['vendor_onboarding', 'tier_upgrade', 'field_agent'];
        $selected = empty($categories) ? $allowed : array_values(array_intersect($categories, $allowed));

        $rows = collect();
        if (in_array('vendor_onboarding', $selected, true)) {
            $rows = $rows->concat($this->vendorApplicationRows());
        }
        if (in_array('tier_upgrade', $selected, true)) {
            $rows = $rows->concat($this->tierUpgradeRows());
        }
        if (in_array('field_agent', $selected, true)) {
            $rows = $rows->concat($this->fieldAgentRows());
        }

        // ISO-8601 strings sort lexicographically in chronological order; id is a deterministic tiebreaker.
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
                'label' => ($app->user?->name ?? 'Unknown vendor').' — '.$tierLabel,
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

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function tierUpgradeRows(): Collection
    {
        $requests = TierUpgradeRequest::query()
            ->with('vendor:id,name')
            ->get();

        return $requests->flatMap(function (TierUpgradeRequest $req): array {
            $rows = [];
            $actor = $req->vendor ? ['id' => $req->vendor->id, 'name' => $req->vendor->name] : null;
            $subject = [
                'id' => $req->id,
                'type' => 'tier_upgrade_request',
                'label' => ($req->vendor?->name ?? 'Unknown vendor').' — Tier Upgrade',
            ];
            $url = "/dashboard/tier-upgrades/{$req->id}";

            $emit = function (string $type, ?Carbon $at) use (&$rows, $req, $actor, $subject, $url): void {
                if ($at === null) {
                    return;
                }
                $rows[] = [
                    'id' => "tier_upgrade_request:{$req->id}:{$type}",
                    'category' => 'tier_upgrade',
                    'type' => $type,
                    'occurred_at' => $at->toIso8601String(),
                    'actor' => $actor,
                    'subject' => $subject,
                    'action_url' => $url,
                ];
            };

            $emit('submitted', $req->created_at);
            $emit('paid', $req->payment_verified_at);
            if ($req->status === TierUpgradeRequest::STATUS_APPROVED) {
                $emit('approved', $req->reviewed_at);
            }
            if ($req->status === TierUpgradeRequest::STATUS_REJECTED) {
                $emit('rejected', $req->reviewed_at);
            }

            return $rows;
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fieldAgentRows(): Collection
    {
        $applications = FieldAgentApplication::query()->get();

        return $applications->flatMap(function (FieldAgentApplication $app): array {
            $rows = [];
            $applicantName = trim($app->first_name.' '.$app->last_name);
            $subject = [
                'id' => $app->id,
                'type' => 'field_agent_application',
                'label' => ($applicantName !== '' ? $applicantName : 'Unknown applicant').' — Field Agent',
            ];
            $url = "/dashboard/field-agent-applications/{$app->id}";

            $emit = function (string $type, ?Carbon $at) use (&$rows, $app, $subject, $url): void {
                if ($at === null) {
                    return;
                }
                $rows[] = [
                    'id' => "field_agent_application:{$app->id}:{$type}",
                    'category' => 'field_agent',
                    'type' => $type,
                    'occurred_at' => $at->toIso8601String(),
                    'actor' => null,
                    'subject' => $subject,
                    'action_url' => $url,
                ];
            };

            $emit('submitted', $app->created_at);
            if ($app->status === FieldAgentApplicationStatus::Approved) {
                $emit('approved', $app->reviewed_at);
            }
            if ($app->status === FieldAgentApplicationStatus::Rejected) {
                $emit('rejected', $app->reviewed_at);
            }

            return $rows;
        });
    }
}
