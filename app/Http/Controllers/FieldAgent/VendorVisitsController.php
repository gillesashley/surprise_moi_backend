<?php

namespace App\Http\Controllers\FieldAgent;

use App\Enums\VendorVisitStatus;
use App\Http\Controllers\Controller;
use App\Models\VendorApplication;
use App\Models\VendorVisit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorVisitsController extends Controller
{
    public function index(Request $request): Response
    {
        $agent = $request->user();

        // Get applications linked to this agent's referral code
        $applications = VendorApplication::query()
            ->with(['user:id,business_name,name,email', 'vendorVisit'])
            ->whereHas('referralCode', function ($query) use ($agent) {
                $query->where('influencer_id', $agent->id);
            })
            ->latest('id')
            ->get();

        return Inertia::render('field-agent/visits/index', [
            'applications' => $applications,
        ]);
    }

    public function start(Request $request, VendorApplication $application): RedirectResponse
    {
        // Ensure application belongs to a referral code from this agent
        abort_unless($application->referralCode?->influencer_id === $request->user()->id, 403);

        $visit = VendorVisit::firstOrCreate(
            [
                'vendor_application_id' => $application->id,
                'field_agent_user_id' => $request->user()->id,
            ],
            [
                'vendor_user_id' => $application->user_id,
                'status' => VendorVisitStatus::Draft->value,
                'started_at' => now(),
            ]
        );

        return redirect("/field-agent/visits/forms/{$visit->id}");
    }

    public function form(Request $request, VendorVisit $visit): Response
    {
        $this->authorizeAgent($request, $visit);

        $visit->load(['vendorApplication.user']);

        return Inertia::render('field-agent/visits/new', [
            'visit' => $visit,
        ]);
    }

    public function submit(Request $request, VendorVisit $visit): RedirectResponse
    {
        $this->authorizeAgent($request, $visit);

        if ($visit->status->isTerminal()) {
            return redirect("/field-agent/visits");
        }

        $validated = $request->validate([
            'ghana_card_number' => ['required', 'string', 'max:255'],
            'tin_number' => ['nullable', 'string', 'max:255'],
            'has_shop' => ['required', 'boolean'],
            'shop_location' => ['required_if:has_shop,true', 'nullable', 'string', 'max:255'],
            'primary_business_address' => ['required_if:has_shop,false', 'nullable', 'string', 'max:255'],
            'storefront_photo' => ['required_if:has_shop,true', 'nullable', 'image', 'max:10240'],
        ]);

        $updates = [
            'ghana_card_number' => $validated['ghana_card_number'],
            'tin_number' => $validated['tin_number'] ?? null,
            'has_shop' => $validated['has_shop'],
            'shop_location' => $validated['has_shop'] ? $validated['shop_location'] : null,
            'primary_business_address' => ! $validated['has_shop'] ? $validated['primary_business_address'] : null,
            'status' => VendorVisitStatus::Submitted->value,
            'submitted_at' => now(),
        ];

        if ($request->hasFile('storefront_photo')) {
            $updates['storefront_photo_path'] = $request->file('storefront_photo')
                ->store('vendor-visits/storefronts', 'public');
        }

        $visit->update($updates);

        return redirect("/field-agent/visits")->with('success', 'Questionnaire submitted successfully.');
    }

    private function authorizeAgent(Request $request, VendorVisit $visit): void
    {
        abort_unless($visit->field_agent_user_id === $request->user()->id, 403);
    }
}
