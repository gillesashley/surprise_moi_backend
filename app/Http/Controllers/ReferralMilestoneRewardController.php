<?php

namespace App\Http\Controllers;

use App\Models\ReferralMilestoneReward;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReferralMilestoneRewardController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        $this->authorize('viewAny', ReferralMilestoneReward::class);

        $rewards = ReferralMilestoneReward::query()
            ->with(['user:id,name,email,role', 'fulfilledBy:id,name'])
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 ELSE 1 END")
            ->latest()
            ->paginate(15);

        return Inertia::render('referral-milestones/index', [
            'rewards' => $rewards,
        ]);
    }

    public function fulfill(Request $request, ReferralMilestoneReward $reward): RedirectResponse
    {
        $this->authorize('update', $reward);

        $validated = $request->validate([
            'reward_type' => 'nullable|string|max:50',
            'reward_description' => 'nullable|string|max:500',
            'reward_value' => 'nullable|numeric|min:0',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $reward->update([
            ...$validated,
            'status' => ReferralMilestoneReward::STATUS_FULFILLED,
            'fulfilled_by' => $request->user()->id,
            'fulfilled_at' => now(),
        ]);

        app(\App\Services\AuditService::class)->record(
            'referral_milestone.fulfilled',
            $reward,
            $request->user(),
            retentionClass: 'critical'
        );

        return back()->with('success', 'Milestone reward marked as fulfilled.');
    }
}
