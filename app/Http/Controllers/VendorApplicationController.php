<?php

namespace App\Http\Controllers;

use App\Models\VendorApplication;
use App\Services\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class VendorApplicationController extends Controller
{
    /**
     * Display a listing of vendor applications.
     */
    public function index(Request $request)
    {
        $query = VendorApplication::query()
            ->with('user:id,name,email')
            ->latest('submitted_at');

        // Filter by status if provided
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Sorting functionality
        $sortBy = $request->input('sort_by', 'submitted_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $allowedSorts = ['status', 'is_registered_vendor', 'submitted_at', 'reviewed_at', 'completed_step'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->latest('submitted_at');
        }

        $applications = $query->paginate(15);

        return Inertia::render('vendor-applications/index', [
            'applications' => $applications->through(fn ($app) => [
                'id' => $app->id,
                'user' => [
                    'id' => $app->user->id,
                    'name' => $app->user->name,
                    'email' => $app->user->email,
                ],
                'status' => $app->status,
                'is_registered_vendor' => $app->isRegisteredVendor(),
                'submitted_at' => $app->submitted_at?->toIso8601String(),
                'reviewed_at' => $app->reviewed_at?->toIso8601String(),
                'current_step' => $app->current_step,
                'completed_step' => $app->completed_step,
            ]),
            'filters' => [
                'status' => $request->status,
                'search' => $request->search,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
            'statuses' => VendorApplication::getStatuses(),
        ]);
    }

    /**
     * Display the specified vendor application.
     */
    public function show(VendorApplication $vendorApplication)
    {
        $vendorApplication->load(['user', 'reviewer', 'bespokeServices', 'latestOnboardingPayment']);

        return Inertia::render('vendor-applications/show', [
            'application' => [
                'id' => $vendorApplication->id,
                'status' => $vendorApplication->status,
                'current_step' => $vendorApplication->current_step,
                'completed_step' => $vendorApplication->completed_step,
                'is_registered_vendor' => $vendorApplication->isRegisteredVendor(),

                // User info
                'user' => [
                    'id' => $vendorApplication->user->id,
                    'name' => $vendorApplication->user->name,
                    'email' => $vendorApplication->user->email,
                    'phone' => $vendorApplication->user->phone,
                    'role' => $vendorApplication->user->role,
                ],

                // Step 1: Ghana Card
                'ghana_card_front' => $vendorApplication->ghana_card_front
                    ? Storage::url($vendorApplication->ghana_card_front)
                    : null,
                'ghana_card_back' => $vendorApplication->ghana_card_back
                    ? Storage::url($vendorApplication->ghana_card_back)
                    : null,

                // Step 2: Business Registration Flags
                'has_business_certificate' => $vendorApplication->has_business_certificate,
                'has_tin' => $vendorApplication->has_tin,

                // Step 3A: Registered Vendor Documents
                'business_certificate_document' => $vendorApplication->business_certificate_document
                    ? Storage::url($vendorApplication->business_certificate_document)
                    : null,
                'tin_document' => $vendorApplication->tin_document
                    ? Storage::url($vendorApplication->tin_document)
                    : null,

                // Step 3B: Unregistered Vendor Documents
                'selfie_image' => $vendorApplication->selfie_image
                    ? Storage::url($vendorApplication->selfie_image)
                    : null,
                'proof_of_business' => $vendorApplication->proof_of_business
                    ? Storage::url($vendorApplication->proof_of_business)
                    : null,
                'mobile_money_number' => $vendorApplication->mobile_money_number,
                'mobile_money_provider' => $vendorApplication->mobile_money_provider,

                // Social Media
                'facebook_handle' => $vendorApplication->facebook_handle,
                'instagram_handle' => $vendorApplication->instagram_handle,
                'twitter_handle' => $vendorApplication->twitter_handle,

                // Step 4: Bespoke Services
                'bespoke_services' => $vendorApplication->bespokeServices->map(fn ($service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                ]),

                // Review Details
                'submitted_at' => $vendorApplication->submitted_at?->toIso8601String(),
                'reviewed_at' => $vendorApplication->reviewed_at?->toIso8601String(),
                'reviewed_by' => $vendorApplication->reviewer ? [
                    'id' => $vendorApplication->reviewer->id,
                    'name' => $vendorApplication->reviewer->name,
                ] : null,
                'rejection_reason' => $vendorApplication->rejection_reason,

                // Payment info
                'payment_required' => $vendorApplication->payment_required,
                'payment_completed' => $vendorApplication->payment_completed,
                'payment_completed_at' => $vendorApplication->payment_completed_at?->toIso8601String(),
                'onboarding_fee' => $vendorApplication->onboarding_fee,
                'discount_amount' => $vendorApplication->discount_amount,
                'final_amount' => $vendorApplication->final_amount,
                'payment' => $vendorApplication->latestOnboardingPayment ? [
                    'status' => $vendorApplication->latestOnboardingPayment->status,
                    'amount' => $vendorApplication->latestOnboardingPayment->amount,
                    'currency' => $vendorApplication->latestOnboardingPayment->currency,
                    'channel' => $vendorApplication->latestOnboardingPayment->channel,
                    'reference' => $vendorApplication->latestOnboardingPayment->reference,
                    'card_last4' => $vendorApplication->latestOnboardingPayment->card_last4,
                    'card_bank' => $vendorApplication->latestOnboardingPayment->card_bank,
                    'mobile_money_number' => $vendorApplication->latestOnboardingPayment->mobile_money_number,
                    'mobile_money_provider' => $vendorApplication->latestOnboardingPayment->mobile_money_provider,
                    'paid_at' => $vendorApplication->latestOnboardingPayment->paid_at?->toIso8601String(),
                    'failure_reason' => $vendorApplication->latestOnboardingPayment->failure_reason,
                ] : null,

                // Review eligibility
                'can_be_reviewed' => $vendorApplication->canBeReviewed(),
            ],
        ]);
    }

    /**
     * Approve a vendor application.
     */
    public function approve(VendorApplication $vendorApplication, ReferralService $referralService)
    {
        // Check if application is complete and ready for review
        if (! $vendorApplication->canBeReviewed()) {
            return back()->with('error', 'This application cannot be reviewed. Ensure all steps are completed, payment is made, and the application has been submitted.');
        }

        // Check if application is in a state that can be approved
        if (! in_array($vendorApplication->status, [VendorApplication::STATUS_PENDING, VendorApplication::STATUS_UNDER_REVIEW])) {
            return back()->with('error', 'This application cannot be approved in its current state.');
        }

        $vendorApplication->approve(Auth::id());

        // Activate referral if one exists
        if ($vendorApplication->referral_code) {
            try {
                $referralService->activateReferral($vendorApplication);
            } catch (\Exception $e) {
                // Log error but don't fail the approval
                Log::warning('Failed to activate referral for vendor application', [
                    'application_id' => $vendorApplication->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('vendor-applications.show', $vendorApplication)
            ->with('success', 'Vendor application approved successfully. User is now a vendor.');
    }

    /**
     * Reject a vendor application.
     */
    public function reject(Request $request, VendorApplication $vendorApplication)
    {
        $request->validate([
            'rejection_reason' => 'required|string|min:10|max:1000',
        ]);

        // Check if application is complete and ready for review
        if (! $vendorApplication->canBeReviewed()) {
            return back()->with('error', 'This application cannot be reviewed. Ensure all steps are completed, payment is made, and the application has been submitted.');
        }

        // Check if application is in a state that can be rejected
        if (! in_array($vendorApplication->status, [VendorApplication::STATUS_PENDING, VendorApplication::STATUS_UNDER_REVIEW])) {
            return back()->with('error', 'This application cannot be rejected in its current state.');
        }

        $vendorApplication->reject(Auth::id(), $request->rejection_reason);

        return redirect()->route('vendor-applications.show', $vendorApplication)
            ->with('success', 'Vendor application rejected.');
    }

    /**
     * Mark application as under review.
     */
    public function markUnderReview(VendorApplication $vendorApplication)
    {
        if ($vendorApplication->markUnderReview()) {
            return back()->with('success', 'Application marked as under review.');
        }

        return back()->with('error', 'Application cannot be marked as under review.');
    }
}
