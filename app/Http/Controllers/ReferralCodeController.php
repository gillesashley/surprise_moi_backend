<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkGenerateReferralCodeRequest;
use App\Models\ReferralCode;
use App\Models\Setting;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReferralCodeController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected ReferralService $referralService) {}

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

        return Inertia::render('referral-codes/create', [
            'bonusPercentages' => [
                'customer' => (float) Setting::get('referral_bonus_customer_pct', 15),
                'vendor' => (float) Setting::get('referral_bonus_vendor_pct', 20),
                'influencer' => (float) Setting::get('referral_bonus_influencer_pct', 25),
                'field_agent' => (float) Setting::get('referral_bonus_field_agent_pct', 30),
                'employee' => (float) Setting::get('referral_bonus_employee_pct', 20),
            ],
            'tierFees' => [
                'tier1' => (float) Setting::get('vendor_tier1_onboarding_fee', 150),
                'tier2' => (float) Setting::get('vendor_tier2_onboarding_fee', 100),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', ReferralCode::class);

        $validated = $request->validate([
            'influencer_id' => 'required|exists:users,id',
            'description' => 'nullable|string|max:255',
            'max_usages' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date|after:today',
        ]);

        $user = User::findOrFail($validated['influencer_id']);
        $prefix = ReferralCode::getPrefixForRole($user->role ?? 'customer');

        $this->referralService->createReferralCode(
            influencer: $user,
            description: $validated['description'] ?? null,
            maxUsages: $validated['max_usages'] ?? null,
            expiresAt: isset($validated['expires_at']) ? new \DateTime($validated['expires_at']) : null,
            prefix: $prefix,
        );

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

        return Inertia::render('referral-codes/edit', [
            'code' => $referralCode->load('influencer'),
        ]);
    }

    public function update(Request $request, ReferralCode $referralCode)
    {
        $this->authorize('update', $referralCode);

        $validated = $request->validate([
            'description' => 'nullable|string|max:255',
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

    /**
     * Return a paginated list of users matching a given role for the
     * cascading user-picker dropdown on the referral code create form.
     *
     * Accepts an optional `q` query parameter for name/email typeahead search.
     * Customer role query also matches users with null role (legacy users).
     */
    public function usersByRole(Request $request): JsonResponse
    {
        $this->authorize('create', ReferralCode::class);

        // Beyond the create policy, this enumeration endpoint is admin-only —
        // influencers can create codes but must not be able to enumerate admin
        // or other user lists.
        abort_if(
            ! $request->user()->isAdmin() && ! $request->user()->isSuperAdmin(),
            403
        );

        $validated = $request->validate([
            'role' => 'required|in:customer,vendor,influencer,field_agent,employee,admin,super_admin',
            'q' => 'nullable|string|max:100',
        ]);

        $query = User::query();

        if ($validated['role'] === 'customer') {
            // Legacy customers may have role = null
            $query->where(function ($q) {
                $q->where('role', 'customer')->orWhereNull('role');
            });
        } else {
            $query->where('role', $validated['role']);
        }

        if (! empty($validated['q'])) {
            $search = $validated['q'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $users = $query->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email', 'role']);

        return response()->json(['data' => $users]);
    }

    public function bulkGeneratePreview(BulkGenerateReferralCodeRequest $request): JsonResponse
    {
        $role = $request->validated('role');

        $query = User::query();
        if ($role === 'customer') {
            $query->where(function ($q) {
                $q->where('role', 'customer')->orWhereNull('role');
            });
        } else {
            $query->where('role', $role);
        }

        $total = $query->count();

        $withCode = (clone $query)->whereHas('referralCodes', function ($q) {
            $q->where('is_active', true);
        })->count();

        return response()->json([
            'total' => $total,
            'with_code' => $withCode,
            'without_code' => $total - $withCode,
            'prefix' => ReferralCode::getPrefixForRole($role),
        ]);
    }

    public function bulkGenerate(BulkGenerateReferralCodeRequest $request): JsonResponse
    {
        $role = $request->validated('role');
        $count = $this->referralService->bulkGenerateCodes($role);

        return response()->json([
            'generated' => $count,
        ]);
    }
}
