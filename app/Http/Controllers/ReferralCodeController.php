<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkGenerateReferralCodeRequest;
use App\Models\ReferralCode;
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

        return Inertia::render('referral-codes/create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', ReferralCode::class);

        $validated = $request->validate([
            'influencer_id' => 'required|exists:users,id',
            'description' => 'nullable|string|max:255',
            'registration_bonus' => 'required|numeric|min:0',
            'max_usages' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date|after:today',
        ]);

        // Note: commission_rate and commission_duration_months are intentionally
        // not in $validated — the Phase 1 plan deprecates those fields from the
        // admin form in favor of the new points/milestone system. Newly created
        // codes fall back to the DB column defaults (0 / 3), which means no
        // commission is accrued on vendor orders. Existing codes with non-zero
        // commission values keep working via the commission flow in
        // ReferralService::calculateCommission.
        //
        // The discount_percentage column was dropped entirely on 2026-04-11
        // (see drop_discount_percentage_from_referral_codes_table migration)
        // because it was never read by any production code path.
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

        return Inertia::render('referral-codes/edit', [
            'code' => $referralCode->load('influencer'),
        ]);
    }

    public function update(Request $request, ReferralCode $referralCode)
    {
        $this->authorize('update', $referralCode);

        $validated = $request->validate([
            'description' => 'nullable|string|max:255',
            'registration_bonus' => 'required|numeric|min:0',
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
            'role' => 'required|in:customer,vendor,influencer,field_agent,marketer,admin,super_admin',
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
