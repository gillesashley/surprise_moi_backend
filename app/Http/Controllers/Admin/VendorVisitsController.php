<?php

namespace App\Http\Controllers\Admin;

use App\Actions\VendorVisit\OverrideVendorVisit;
use App\Actions\VendorVisit\RevokeFieldVerificationBadge;
use App\Enums\VendorVisitStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OverrideVendorVisitRequest;
use App\Http\Requests\Admin\RevokeFieldVerificationBadgeRequest;
use App\Models\VendorVisit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorVisitsController extends Controller
{
    public function index(Request $request): Response
    {
        $tab = $request->string('tab', 'needs-review')->toString();

        $query = VendorVisit::query()
            ->with(['vendor:id,business_name,name', 'fieldAgent:id,name'])
            ->latest('started_at');

        if ($tab === 'needs-review') {
            $query->where('status', VendorVisitStatus::Submitted->value);
        } elseif ($tab === 'recent-failures') {
            $query->where('status', VendorVisitStatus::Failed->value)
                ->where('submitted_at', '>=', now()->subDays(30));
        }

        return Inertia::render('admin/vendor-visits/index', [
            'tab' => $tab,
            'visits' => $query->paginate(25)->withQueryString(),
        ]);
    }

    public function show(VendorVisit $vendorVisit): Response
    {
        $vendorVisit->load([
            'items',
            'vendor:id,business_name,name,email,phone,field_verified_at,field_verified_until',
            'fieldAgent:id,name',
            'overrideBy:id,name',
        ]);

        return Inertia::render('admin/vendor-visits/show', [
            'visit' => $vendorVisit,
        ]);
    }

    public function override(OverrideVendorVisitRequest $request, VendorVisit $vendorVisit, OverrideVendorVisit $action): RedirectResponse
    {
        $action->execute(
            visit: $vendorVisit,
            admin: $request->user(),
            newResult: VendorVisitStatus::from($request->validated('result')),
            reason: $request->validated('reason'),
        );

        return back()->with('success', 'Visit outcome overridden.');
    }

    public function revoke(RevokeFieldVerificationBadgeRequest $request, VendorVisit $vendorVisit, RevokeFieldVerificationBadge $action): RedirectResponse
    {
        $action->execute($vendorVisit, $request->user(), $request->validated('reason'));

        return back()->with('success', 'Badge revoked.');
    }
}
