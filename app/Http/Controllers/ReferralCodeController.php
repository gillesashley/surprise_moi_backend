<?php

namespace App\Http\Controllers;

use App\Models\ReferralCode;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReferralCodeController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', ReferralCode::class);

        $codes = ReferralCode::with('influencer')
            ->latest()
            ->paginate(15);

        return Inertia::render('referral-codes/index', [
            'codes' => $codes,
        ]);
    }

    public function create()
    {
        $this->authorize('create', ReferralCode::class);

        $influencers = User::where('role', 'influencer')->get();

        return Inertia::render('referral-codes/create', [
            'influencers' => $influencers,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', ReferralCode::class);

        $validated = $request->validate([
            'influencer_id' => 'required|exists:users,id',
            'description' => 'nullable|string|max:255',
            'registration_bonus' => 'required|numeric|min:0',
            'commission_rate' => 'required|numeric|min:0|max:100',
            'commission_duration_months' => 'required|integer|min:1|max:120',
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'max_usages' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date|after:today',
        ]);

        ReferralCode::create([
            ...$validated,
            'is_active' => true,
            'usage_count' => 0,
        ]);

        return redirect()->route('referral-codes.index')
            ->with('success', 'Referral code created successfully.');
    }

    public function show(ReferralCode $referralCode)
    {
        $this->authorize('view', $referralCode);

        $referralCode->load(['influencer', 'referrals.vendor']);

        return Inertia::render('referral-codes/show', [
            'code' => $referralCode,
        ]);
    }

    public function edit(ReferralCode $referralCode)
    {
        $this->authorize('update', $referralCode);

        $influencers = User::where('role', 'influencer')->get();

        return Inertia::render('referral-codes/edit', [
            'code' => $referralCode->load('influencer'),
            'influencers' => $influencers,
        ]);
    }

    public function update(Request $request, ReferralCode $referralCode)
    {
        $this->authorize('update', $referralCode);

        $validated = $request->validate([
            'description' => 'nullable|string|max:255',
            'registration_bonus' => 'required|numeric|min:0',
            'commission_rate' => 'required|numeric|min:0|max:100',
            'commission_duration_months' => 'required|integer|min:1|max:120',
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'max_usages' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date',
            'is_active' => 'required|boolean',
        ]);

        $referralCode->update($validated);

        return redirect()->route('referral-codes.index')
            ->with('success', 'Referral code updated successfully.');
    }

    public function destroy(ReferralCode $referralCode)
    {
        $this->authorize('delete', $referralCode);

        $referralCode->delete();

        return redirect()->back()
            ->with('success', 'Referral code deleted successfully.');
    }

    public function toggle(ReferralCode $referralCode)
    {
        $this->authorize('update', $referralCode);

        $referralCode->update(['is_active' => ! $referralCode->is_active]);

        return back()->with('success', 'Referral code status updated.');
    }
}
