<?php

namespace App\Http\Controllers;

use App\Http\Requests\FlagVendorApplicationRequest;
use App\Models\Order;
use App\Models\VendorApplication;
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
            ->with(['user:id,name,email', 'latestOnboardingPayment'])
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
                'payment_completed' => $app->payment_completed,
                'payment_status' => $app->latestOnboardingPayment?->status,
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
        $vendorApplication->load(['user', 'reviewer', 'bespokeServices', 'latestOnboardingPayment', 'vendorVisit.fieldAgent']);

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

                'questionnaire' => $vendorApplication->vendorVisit ? [
                    'id' => $vendorApplication->vendorVisit->id,
                    'status' => $vendorApplication->vendorVisit->status,
                    'ghana_card_number' => $vendorApplication->vendorVisit->ghana_card_number,
                    'tin_number' => $vendorApplication->vendorVisit->tin_number,
                    'has_shop' => $vendorApplication->vendorVisit->has_shop,
                    'shop_location' => $vendorApplication->vendorVisit->shop_location,
                    'primary_business_address' => $vendorApplication->vendorVisit->primary_business_address,
                    'storefront_photo' => $vendorApplication->vendorVisit->storefront_photo_path ? Storage::url($vendorApplication->vendorVisit->storefront_photo_path) : null,
                    'submitted_at' => $vendorApplication->vendorVisit->submitted_at?->toIso8601String(),
                    'field_agent' => $vendorApplication->vendorVisit->fieldAgent ? [
                        'name' => $vendorApplication->vendorVisit->fieldAgent->name,
                    ] : null,
                ] : null,
            ],
            'vendorOrders' => $this->getVendorOrders($vendorApplication),
        ]);
    }

    /**
     * Get vendor orders data if the application is approved.
     *
     * @return array{stats: array, orders: array}|null
     */
    private function getVendorOrders(VendorApplication $vendorApplication): ?array
    {
        if ($vendorApplication->status !== VendorApplication::STATUS_APPROVED) {
            return null;
        }

        $vendorId = $vendorApplication->user_id;

        $ordersQuery = Order::query()->where('vendor_id', $vendorId);

        $stats = [
            'total' => (clone $ordersQuery)->count(),
            'pending' => (clone $ordersQuery)->where('status', 'pending')->count(),
            'confirmed' => (clone $ordersQuery)->where('status', 'confirmed')->count(),
            'processing' => (clone $ordersQuery)->where('status', 'processing')->count(),
            'fulfilled' => (clone $ordersQuery)->where('status', 'fulfilled')->count(),
            'shipped' => (clone $ordersQuery)->where('status', 'shipped')->count(),
            'delivered' => (clone $ordersQuery)->where('status', 'delivered')->count(),
            'refunded' => (clone $ordersQuery)->where('status', 'refunded')->count(),
            'total_revenue' => (clone $ordersQuery)->where('payment_status', 'paid')->sum('total'),
            'total_payout' => (clone $ordersQuery)->where('payment_status', 'paid')->sum('vendor_payout_amount'),
        ];

        $orders = Order::query()
            ->where('vendor_id', $vendorId)
            ->with('user:id,name,email')
            ->latest()
            ->paginate(10, ['*'], 'orders_page')
            ->through(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer' => $order->user ? [
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                ] : null,
                'total' => $order->total,
                'currency' => $order->currency,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'created_at' => $order->created_at->toIso8601String(),
            ]);

        return [
            'stats' => $stats,
            'orders' => $orders,
        ];
    }

    /**
     * Approve a vendor application.
     */
    public function approve(VendorApplication $vendorApplication, \App\Services\ReferralService $referralService)
    {
        // Check if application is complete and ready for review
        if (! $vendorApplication->canBeReviewed()) {
            return back()->with('error', 'This application cannot be reviewed. Ensure all steps are completed, payment is made, and the application has been submitted.');
        }

        // Check if application is in a state that can be approved
        if (! in_array($vendorApplication->status, [VendorApplication::STATUS_PENDING, VendorApplication::STATUS_UNDER_REVIEW, VendorApplication::STATUS_FLAGGED], true)) {
            return back()->with('error', 'This application cannot be approved in its current state.');
        }

        $vendorApplication->approve(Auth::id());

        app(\App\Services\AuditService::class)->record(
            'vendor_application.approved',
            $vendorApplication,
            Auth::user(),
            retentionClass: 'critical'
        );

        // Activate referral if one exists
        if ($vendorApplication->referral_code_id) {
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
        if (! in_array($vendorApplication->status, [VendorApplication::STATUS_PENDING, VendorApplication::STATUS_UNDER_REVIEW, VendorApplication::STATUS_FLAGGED], true)) {
            return back()->with('error', 'This application cannot be rejected in its current state.');
        }

        $vendorApplication->reject(Auth::id(), $request->rejection_reason);

        app(\App\Services\AuditService::class)->record(
            'vendor_application.rejected',
            $vendorApplication,
            Auth::user(),
            extra: ['reason' => $request->input('rejection_reason')],
            retentionClass: 'critical'
        );

        return redirect()->route('vendor-applications.show', $vendorApplication)
            ->with('success', 'Vendor application rejected.');
    }

    /**
     * Flag a vendor application for missing or unclear details.
     */
    public function flag(FlagVendorApplicationRequest $request, VendorApplication $vendorApplication)
    {
        if (! $vendorApplication->canBeReviewed()) {
            return back()->with('error', 'This application cannot be reviewed. Ensure all steps are completed, payment is made, and the application has been submitted.');
        }

        if (! in_array($vendorApplication->status, [
            VendorApplication::STATUS_PENDING,
            VendorApplication::STATUS_UNDER_REVIEW,
            VendorApplication::STATUS_FLAGGED,
        ], true)) {
            return back()->with('error', 'This application cannot be flagged in its current state.');
        }

        $vendorApplication->flag(Auth::id(), $request->input('flag_reason'));

        app(\App\Services\AuditService::class)->record(
            'vendor_application.flagged',
            $vendorApplication,
            Auth::user(),
            extra: [
                'reason' => $request->input('flag_reason'),
                'grace_period_ends_at' => $vendorApplication->grace_period_ends_at?->toIso8601String(),
            ],
            retentionClass: 'critical'
        );

        return redirect()->route('vendor-applications.show', $vendorApplication)
            ->with('success', 'Vendor application flagged. The vendor has been notified.');
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

    /**
     * Permanently delete a vendor application and clean up all associated files.
     */
    public function destroy(Request $request, VendorApplication $vendorApplication)
    {
        if (! Auth::user()->isSuperAdmin()) {
            return back()->with('error', 'Only super admins can delete vendor applications.');
        }

        $request->validate([
            'confirmation' => ['required', 'string', 'in:DELETE'],
        ], [
            'confirmation.in' => 'You must type "DELETE" to confirm this action.',
        ]);

        // If the vendor was approved, revert their role
        if ($vendorApplication->status === VendorApplication::STATUS_APPROVED) {
            $vendorApplication->user->update([
                'role' => 'user',
                'vendor_tier' => null,
            ]);
        }

        $applicantName = $vendorApplication->user->name;

        // forceDelete triggers VendorApplicationObserver::forceDeleting for R2 cleanup
        $vendorApplication->forceDelete();

        return redirect()->route('vendor-applications.index')
            ->with('success', "Vendor application for {$applicantName} has been permanently deleted.");
    }
}
