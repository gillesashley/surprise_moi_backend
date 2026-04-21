<?php

namespace App\Http\Controllers\FieldAgent;

use App\Actions\VendorVisit\CompleteVendorVisit;
use App\Actions\VendorVisit\StartVendorVisit;
use App\Enums\VendorVisitStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\FieldAgent\StartVendorVisitRequest;
use App\Http\Requests\FieldAgent\SubmitVendorVisitRequest;
use App\Http\Requests\FieldAgent\UpdateVendorVisitItemRequest;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorVisit;
use App\Models\VendorVisitItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VendorVisitsController extends Controller
{
    public function index(Request $request): Response
    {
        $agent = $request->user();

        $needsVisit = User::query()
            ->where('role', 'vendor')
            ->whereHas('vendorApplications', fn ($q) => $q->where('status', VendorApplication::STATUS_APPROVED))
            ->where(function ($q) {
                $q->whereNull('field_verified_until')
                    ->orWhere('field_verified_until', '<=', now());
            })
            ->select(['id', 'business_name', 'name', 'field_verified_until'])
            ->orderBy('field_verified_until')
            ->limit(50)
            ->get();

        $expiringSoon = User::query()
            ->where('role', 'vendor')
            ->whereBetween('field_verified_until', [now(), now()->addDays(30)])
            ->select(['id', 'business_name', 'name', 'field_verified_until'])
            ->orderBy('field_verified_until')
            ->limit(50)
            ->get();

        $drafts = VendorVisit::query()
            ->where('field_agent_user_id', $agent->id)
            ->where('status', VendorVisitStatus::Draft->value)
            ->with('vendor:id,business_name,name')
            ->orderByDesc('started_at')
            ->get();

        return Inertia::render('field-agent/visits/index', [
            'needsVisit' => $needsVisit,
            'expiringSoon' => $expiringSoon,
            'drafts' => $drafts,
        ]);
    }

    public function show(Request $request, User $vendor): Response
    {
        abort_unless($vendor->role === 'vendor', 404);

        $application = VendorApplication::query()
            ->where('user_id', $vendor->id)
            ->latest('id')
            ->first();

        abort_unless($application?->status === VendorApplication::STATUS_APPROVED, 404, 'Vendor is not approved yet.');

        $visits = VendorVisit::query()
            ->where('vendor_user_id', $vendor->id)
            ->orderByDesc('started_at')
            ->limit(10)
            ->get();

        return Inertia::render('field-agent/visits/show', [
            'vendor' => $vendor->only(['id', 'business_name', 'name', 'email', 'phone']),
            'application' => $application?->only([
                'id', 'has_business_certificate', 'tin_number', 'ghana_card_front',
                'ghana_card_back', 'selfie_image', 'business_certificate_document',
                'proof_of_business', 'mobile_money_number', 'mobile_money_provider',
                'facebook_handle', 'instagram_handle', 'twitter_handle',
            ]),
            'recentVisits' => $visits,
        ]);
    }

    public function start(StartVendorVisitRequest $request, User $vendor, StartVendorVisit $start): RedirectResponse
    {
        $application = VendorApplication::query()
            ->where('user_id', $vendor->id)
            ->latest('id')
            ->first();

        if ($application?->status !== VendorApplication::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'vendor' => "This vendor isn't approved yet — there's nothing to verify.",
            ]);
        }

        $visit = $start->execute(
            vendor: $vendor,
            agent: $request->user(),
            latitude: (float) $request->validated('latitude'),
            longitude: (float) $request->validated('longitude'),
        );

        return redirect("/field-agent/visits/forms/{$visit->id}");
    }

    public function form(Request $request, VendorVisit $visit): Response
    {
        $this->authorizeAgent($request, $visit);

        $visit->load(['items', 'vendor:id,business_name,name']);

        return Inertia::render('field-agent/visits/new', [
            'visit' => $visit,
            'items' => $visit->items,
        ]);
    }

    public function updateItem(UpdateVendorVisitItemRequest $request, VendorVisit $visit, VendorVisitItem $item): JsonResponse
    {
        $this->authorizeAgent($request, $visit);
        abort_unless($item->vendor_visit_id === $visit->id, 404);
        abort_if($visit->status->isTerminal(), 422, 'Visit is already submitted.');

        $item->update($request->validated());

        return response()->json(['ok' => true, 'item' => $item->fresh()]);
    }

    public function submit(SubmitVendorVisitRequest $request, VendorVisit $visit, CompleteVendorVisit $complete): RedirectResponse
    {
        $this->authorizeAgent($request, $visit);

        if ($visit->status->isTerminal()) {
            return redirect("/field-agent/visits/forms/{$visit->id}");
        }

        $updates = $request->only(['notes']);
        $updates['escalated'] = (bool) $request->boolean('escalated');

        if ($request->hasFile('storefront_photo')) {
            $updates['storefront_photo_path'] = $request->file('storefront_photo')
                ->store('vendor-visits/storefronts', 'public');
        }
        if ($request->hasFile('owner_photo')) {
            $updates['owner_photo_path'] = $request->file('owner_photo')
                ->store('vendor-visits/owners', 'public');
        }

        $visit->update($updates);

        $complete->execute($visit->fresh('items'));

        return redirect("/field-agent/visits/forms/{$visit->id}");
    }

    private function authorizeAgent(Request $request, VendorVisit $visit): void
    {
        abort_unless($visit->field_agent_user_id === $request->user()->id, 403);
    }
}
