<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ActivityLog::query()
            ->with(['causer:id,name,email,role'])
            ->latest('id');

        if ($event = $request->string('event')->toString()) {
            $query->where('event', $event);
        }

        if ($subjectType = $request->string('subject_type')->toString()) {
            $full = str_contains($subjectType, '\\') ? $subjectType : "App\\Models\\{$subjectType}";
            $query->where('subject_type', $full);
        }

        if ($userFilter = $request->string('user')->toString()) {
            if (is_numeric($userFilter)) {
                $query->where('causer_id', (int) $userFilter);
            } else {
                $ids = User::query()
                    ->where('name', 'ilike', "%{$userFilter}%")
                    ->orWhere('email', 'ilike', "%{$userFilter}%")
                    ->pluck('id');
                $query->whereIn('causer_id', $ids);
            }
        }

        if ($from = $request->date('from')) {
            $query->where('created_at', '>=', $from);
        }

        if ($to = $request->date('to')) {
            $query->where('created_at', '<=', $to->endOfDay());
        }

        $logs = $query->paginate((int) $request->integer('per_page', 20))->withQueryString();

        return Inertia::render('audit-log/index', [
            'logs' => $logs,
            'filters' => $request->only(['event', 'subject_type', 'user', 'from', 'to', 'per_page']),
            'entityOptions' => $this->entityOptions(),
            'eventOptions' => $this->eventOptions(),
        ]);
    }

    /** @return array<int, array{value: string, label: string}> */
    private function entityOptions(): array
    {
        return collect([
            'User' => 'User',
            'VendorApplication' => 'Vendor Application',
            'FieldAgentApplication' => 'Field Agent Application',
            'PayoutRequest' => 'Payout Request',
            'TreasuryTransfer' => 'Treasury Transfer',
            'ReferralCode' => 'Referral Code',
            'Referral' => 'Referral',
            'ReferralMilestoneReward' => 'Referral Milestone Reward',
            'Setting' => 'Setting',
            'Product' => 'Product',
            'Category' => 'Category',
            'Order' => 'Order',
            'Advertisement' => 'Advertisement',
            'Coupon' => 'Coupon',
            'BespokeService' => 'Bespoke Service',
        ])->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all();
    }

    /** @return array<int, array{value: string, label: string}> */
    private function eventOptions(): array
    {
        return [
            ['value' => 'created', 'label' => 'Created'],
            ['value' => 'updated', 'label' => 'Updated'],
            ['value' => 'deleted', 'label' => 'Deleted'],
            ['value' => 'login', 'label' => 'Login'],
            ['value' => 'logout', 'label' => 'Logout'],
            ['value' => 'login_failed', 'label' => 'Login Failed'],
            ['value' => 'password_reset', 'label' => 'Password Reset'],
            ['value' => 'vendor_application.approved', 'label' => 'Vendor App Approved'],
            ['value' => 'vendor_application.rejected', 'label' => 'Vendor App Rejected'],
            ['value' => 'field_agent_application.approved', 'label' => 'Field Agent Approved'],
            ['value' => 'field_agent_application.rejected', 'label' => 'Field Agent Rejected'],
            ['value' => 'payout.approved', 'label' => 'Payout Approved'],
            ['value' => 'payout.rejected', 'label' => 'Payout Rejected'],
            ['value' => 'payout.paid', 'label' => 'Payout Paid'],
            ['value' => 'payout.processed', 'label' => 'Payout Processed'],
            ['value' => 'payout.finalized', 'label' => 'Payout Finalized'],
            ['value' => 'referral_milestone.fulfilled', 'label' => 'Milestone Fulfilled'],
            ['value' => 'user.role_changed', 'label' => 'User Role Changed'],
            ['value' => 'settings.updated', 'label' => 'Settings Updated'],
        ];
    }
}
