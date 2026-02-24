<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\VendorRegistration\StoreBespokeServicesRequest;
use App\Http\Requests\Api\V1\VendorRegistration\StoreBusinessRegistrationRequest;
use App\Http\Requests\Api\V1\VendorRegistration\StoreGhanaCardRequest;
use App\Http\Requests\Api\V1\VendorRegistration\StoreRegisteredVendorDocumentsRequest;
use App\Http\Requests\Api\V1\VendorRegistration\StoreUnregisteredVendorDocumentsRequest;
use App\Http\Resources\BespokeServiceResource;
use App\Http\Resources\VendorApplicationResource;
use App\Models\BespokeService;
use App\Models\VendorApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VendorRegistrationController extends Controller
{
    /**
     * Get current application status and progress.
     */
    public function status(): JsonResponse
    {
        $application = auth()->user()->vendorApplications()->latest()->first();

        if (! $application) {
            return response()->json([
                'success' => true,
                'data' => [
                    'has_application' => false,
                    'can_start_new' => true,
                    'message' => 'No vendor application found. Start by uploading your Ghana Card.',
                ],
            ]);
        }

        $application->load('bespokeServices');

        // Check if application is submitted but not yet approved/rejected
        $isSubmitted = $application->submitted_at !== null;
        $isEditable = $application->isEditable();
        $canStartNew = ! $isEditable && $application->status === VendorApplication::STATUS_REJECTED;

        return response()->json([
            'success' => true,
            'data' => [
                'has_application' => true,
                'is_submitted' => $isSubmitted,
                'is_editable' => $isEditable,
                'can_start_new' => $canStartNew,
                'application' => new VendorApplicationResource($application),
                'message' => $this->getStatusMessage($application, $isEditable, $isSubmitted),
            ],
        ]);
    }

    /**
     * Get appropriate status message based on application state.
     */
    private function getStatusMessage(VendorApplication $application, bool $isEditable, bool $isSubmitted): string
    {
        if ($application->status === VendorApplication::STATUS_APPROVED) {
            return 'Your vendor application has been approved!';
        }

        if ($application->status === VendorApplication::STATUS_REJECTED) {
            return 'Your application was rejected. You can start a new application.';
        }

        if ($application->status === VendorApplication::STATUS_UNDER_REVIEW) {
            return 'Your application is under review. We will notify you once a decision is made.';
        }

        if ($isSubmitted) {
            return 'Your application has been submitted and is pending review.';
        }

        if ($isEditable) {
            $step = $application->completed_step + 1;

            return "Continue your application from Step {$step}.";
        }

        return 'Your application status is being processed.';
    }

    /**
     * Get available bespoke services.
     */
    public function getBespokeServices(): JsonResponse
    {
        $services = BespokeService::active()->ordered()->get();

        return response()->json([
            'success' => true,
            'data' => BespokeServiceResource::collection($services),
        ]);
    }

    /**
     * Step 1: Upload Ghana Card (front and back).
     */
    public function storeGhanaCard(StoreGhanaCardRequest $request): JsonResponse
    {
        $user = auth()->user();

        // Check if user already has a pending or approved application
        $existingApplication = $user->vendorApplications()
            ->whereIn('status', [VendorApplication::STATUS_PENDING, VendorApplication::STATUS_UNDER_REVIEW, VendorApplication::STATUS_APPROVED])
            ->first();

        if ($existingApplication) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an active or approved vendor application.',
                'data' => [
                    'application' => new VendorApplicationResource($existingApplication),
                ],
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Get or create pending application
            $application = $user->vendorApplications()
                ->where('status', VendorApplication::STATUS_PENDING)
                ->first();

            // Delete old files if updating
            if ($application) {
                if ($application->ghana_card_front) {
                    Storage::disk('public')->delete($application->ghana_card_front);
                }
                if ($application->ghana_card_back) {
                    Storage::disk('public')->delete($application->ghana_card_back);
                }
            } else {
                $application = new VendorApplication(['user_id' => $user->id]);
            }

            // Store new files
            $frontPath = $request->file('ghana_card_front')
                ->store('vendor-applications/ghana-cards', 'public');
            $backPath = $request->file('ghana_card_back')
                ->store('vendor-applications/ghana-cards', 'public');

            $application->fill([
                'ghana_card_front' => $frontPath,
                'ghana_card_back' => $backPath,
                'current_step' => 2,
                'completed_step' => max($application->completed_step ?? 0, 1),
            ]);

            $application->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ghana Card uploaded successfully. Proceed to step 2.',
                'data' => [
                    'application' => new VendorApplicationResource($application),
                    'next_step' => 2,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload Ghana Card. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Step 2: Business Registration flags.
     */
    public function storeBusinessRegistration(StoreBusinessRegistrationRequest $request): JsonResponse
    {
        $application = $this->getEditableApplication();

        if (! $application) {
            return $this->noApplicationResponse();
        }

        if ($application->completed_step < 1) {
            return response()->json([
                'success' => false,
                'message' => 'Please complete Step 1 (Ghana Card upload) first.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $application->fill([
                'has_business_certificate' => $request->has_business_certificate,
                'current_step' => 3,
                'completed_step' => max($application->completed_step, 2),
            ]);

            $application->save();

            DB::commit();

            $vendorType = $application->isRegisteredVendor() ? 'registered' : 'unregistered';

            return response()->json([
                'success' => true,
                'message' => 'Business registration details saved. Proceed to step 3.',
                'data' => [
                    'application' => new VendorApplicationResource($application),
                    'next_step' => 3,
                    'vendor_type' => $vendorType,
                    'required_documents' => $this->getRequiredDocumentsForStep3($application),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to save business registration details.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Step 3A: Registered Vendor Documents (has business certificate and/or TIN).
     */
    public function storeRegisteredVendorDocuments(StoreRegisteredVendorDocumentsRequest $request): JsonResponse
    {
        $application = $this->getEditableApplication();

        if (! $application) {
            return $this->noApplicationResponse();
        }

        if ($application->completed_step < 2) {
            return response()->json([
                'success' => false,
                'message' => 'Please complete Step 2 (Business Registration) first.',
            ], 422);
        }

        if (! $application->isRegisteredVendor()) {
            return response()->json([
                'success' => false,
                'message' => 'This endpoint is for registered vendors only. Please use the unregistered vendor endpoint.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $data = [];

            // Handle business certificate document
            if ($request->hasFile('business_certificate_document')) {
                if ($application->business_certificate_document) {
                    Storage::disk('public')->delete($application->business_certificate_document);
                }
                $data['business_certificate_document'] = $request->file('business_certificate_document')
                    ->store('vendor-applications/business-documents', 'public');
            }

            // Social media handles
            $data['facebook_handle'] = $request->facebook_handle;
            $data['instagram_handle'] = $request->instagram_handle;
            $data['twitter_handle'] = $request->twitter_handle;

            $data['current_step'] = 4;
            $data['completed_step'] = max($application->completed_step, 3);

            $application->fill($data);
            $application->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Business documents uploaded successfully. Proceed to step 4.',
                'data' => [
                    'application' => new VendorApplicationResource($application),
                    'next_step' => 4,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload business documents.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Step 3B: Unregistered Vendor Verification (no business documents).
     */
    public function storeUnregisteredVendorDocuments(StoreUnregisteredVendorDocumentsRequest $request): JsonResponse
    {
        $application = $this->getEditableApplication();

        if (! $application) {
            return $this->noApplicationResponse();
        }

        if ($application->completed_step < 2) {
            return response()->json([
                'success' => false,
                'message' => 'Please complete Step 2 (Business Registration) first.',
            ], 422);
        }

        if ($application->isRegisteredVendor()) {
            return response()->json([
                'success' => false,
                'message' => 'This endpoint is for unregistered vendors only. Please use the registered vendor endpoint.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Delete old files if updating
            if ($application->selfie_image) {
                Storage::disk('public')->delete($application->selfie_image);
            }
            if ($application->proof_of_business) {
                Storage::disk('public')->delete($application->proof_of_business);
            }

            // Store new files
            $selfiePath = $request->file('selfie_image')
                ->store('vendor-applications/selfies', 'public');
            $proofPath = $request->file('proof_of_business')
                ->store('vendor-applications/proof-of-business', 'public');

            $application->fill([
                'selfie_image' => $selfiePath,
                'mobile_money_number' => $request->mobile_money_number,
                'mobile_money_provider' => $request->mobile_money_provider,
                'proof_of_business' => $proofPath,
                'facebook_handle' => $request->facebook_handle,
                'instagram_handle' => $request->instagram_handle,
                'twitter_handle' => $request->twitter_handle,
                'current_step' => 4,
                'completed_step' => max($application->completed_step, 3),
            ]);

            $application->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Verification documents uploaded successfully. Proceed to step 4.',
                'data' => [
                    'application' => new VendorApplicationResource($application),
                    'next_step' => 4,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload verification documents.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Step 4: Select Bespoke Services.
     */
    public function storeBespokeServices(StoreBespokeServicesRequest $request): JsonResponse
    {
        $application = $this->getEditableApplication();

        if (! $application) {
            return $this->noApplicationResponse();
        }

        if ($application->completed_step < 3) {
            return response()->json([
                'success' => false,
                'message' => 'Please complete Step 3 (Document Upload) first.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Sync bespoke services
            $application->bespokeServices()->sync($request->service_ids);

            $application->fill([
                'current_step' => 5,
                'completed_step' => max($application->completed_step, 4),
            ]);

            $application->save();
            $application->load('bespokeServices');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bespoke services selected. Please review and submit your application.',
                'data' => [
                    'application' => new VendorApplicationResource($application),
                    'next_step' => 5,
                    'can_submit' => true,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to save bespoke services.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Step 5: Review and Submit Application.
     */
    public function submit(): JsonResponse
    {
        $application = $this->getEditableApplication();

        if (! $application) {
            return $this->noApplicationResponse();
        }

        if (! $application->canSubmit()) {
            return response()->json([
                'success' => false,
                'message' => 'Please complete all steps before submitting your application.',
                'data' => [
                    'completed_step' => $application->completed_step,
                    'required_steps' => 4,
                ],
            ], 422);
        }

        DB::beginTransaction();

        try {
            $application->fill([
                'status' => VendorApplication::STATUS_PENDING,
                'submitted_at' => now(),
            ]);

            $application->save();
            $application->load('bespokeServices');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Your vendor application has been submitted successfully. We will review it and get back to you soon.',
                'data' => [
                    'application' => new VendorApplicationResource($application),
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit application.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Debug endpoint to check application state (temporary - remove in production).
     */
    public function debug(): JsonResponse
    {
        $user = auth()->user();
        $application = $user->vendorApplications()->latest()->first();

        if (! $application) {
            return response()->json([
                'success' => true,
                'debug' => [
                    'has_application' => false,
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'role' => $user->role,
                    ],
                    'message' => 'No vendor application found for this user',
                ],
            ]);
        }

        $application->load('bespokeServices');

        return response()->json([
            'success' => true,
            'debug' => [
                'has_application' => true,
                'application_id' => $application->id,
                'status' => $application->status,
                'current_step' => $application->current_step,
                'completed_step' => $application->completed_step,
                'can_submit' => $application->canSubmit(),
                'is_editable' => $application->isEditable(),
                'step_completion' => [
                    'step_1_complete' => $application->completed_step >= 1,
                    'step_2_complete' => $application->completed_step >= 2,
                    'step_3_complete' => $application->completed_step >= 3,
                    'step_4_complete' => $application->completed_step >= 4,
                ],
                'documents' => [
                    'ghana_card_front' => (bool) $application->ghana_card_front,
                    'ghana_card_back' => (bool) $application->ghana_card_back,
                    'business_certificate' => (bool) $application->business_certificate_document,
                    'tin_document' => (bool) $application->tin_document,
                    'selfie' => (bool) $application->selfie_image,
                    'proof_of_business' => (bool) $application->proof_of_business,
                ],
                'services' => [
                    'count' => $application->bespokeServices->count(),
                    'service_ids' => $application->bespokeServices->pluck('id')->toArray(),
                    'service_names' => $application->bespokeServices->pluck('name')->toArray(),
                ],
                'timestamps' => [
                    'created_at' => $application->created_at->toIso8601String(),
                    'updated_at' => $application->updated_at->toIso8601String(),
                    'submitted_at' => $application->submitted_at?->toIso8601String(),
                ],
                'next_steps' => $this->getNextStepsAdvice($application),
            ],
        ]);
    }

    /**
     * Get full application details for review (Step 5).
     */
    public function review(): JsonResponse
    {
        $application = auth()->user()->vendorApplications()->latest()->first();

        if (! $application) {
            return $this->noApplicationResponse();
        }

        $application->load('bespokeServices');

        // Build review data with all details
        $reviewData = [
            'application' => new VendorApplicationResource($application),
            'steps_summary' => [
                'step_1' => [
                    'title' => 'Ghana Card',
                    'completed' => $application->completed_step >= 1,
                    'data' => [
                        'front_uploaded' => (bool) $application->ghana_card_front,
                        'back_uploaded' => (bool) $application->ghana_card_back,
                    ],
                ],
                'step_2' => [
                    'title' => 'Business Registration',
                    'completed' => $application->completed_step >= 2,
                    'data' => [
                        'has_business_certificate' => $application->has_business_certificate,
                        'has_tin' => $application->has_tin,
                        'vendor_type' => $application->isRegisteredVendor() ? 'Registered Business' : 'Individual Vendor',
                    ],
                ],
                'step_3' => [
                    'title' => 'Verification Documents',
                    'completed' => $application->completed_step >= 3,
                    'data' => $application->isRegisteredVendor()
                        ? [
                            'business_certificate_uploaded' => (bool) $application->business_certificate_document,
                            'tin_uploaded' => (bool) $application->tin_document,
                        ]
                        : [
                            'selfie_uploaded' => (bool) $application->selfie_image,
                            'mobile_money_number' => $application->mobile_money_number,
                            'mobile_money_provider' => $application->mobile_money_provider,
                            'proof_of_business_uploaded' => (bool) $application->proof_of_business,
                        ],
                    'social_media' => [
                        'facebook' => $application->facebook_handle,
                        'instagram' => $application->instagram_handle,
                        'twitter' => $application->twitter_handle,
                    ],
                ],
                'step_4' => [
                    'title' => 'Bespoke Services',
                    'completed' => $application->completed_step >= 4,
                    'data' => [
                        'services_count' => $application->bespokeServices->count(),
                        'services' => $application->bespokeServices->pluck('name'),
                    ],
                ],
            ],
            'can_submit' => $application->canSubmit(),
            'is_submitted' => $application->status !== VendorApplication::STATUS_PENDING,
        ];

        return response()->json([
            'success' => true,
            'data' => $reviewData,
        ]);
    }

    /**
     * Get the current editable application.
     */
    private function getEditableApplication(): ?VendorApplication
    {
        return auth()->user()->vendorApplications()
            ->where(function ($query) {
                $query->where('status', VendorApplication::STATUS_REJECTED)
                    ->orWhere(function ($q) {
                        $q->where('status', VendorApplication::STATUS_PENDING)
                            ->whereNull('submitted_at');
                    });
            })
            ->latest()
            ->first();
    }

    /**
     * Standard response for no application found or not editable.
     */
    private function noApplicationResponse(): JsonResponse
    {
        $latestApp = auth()->user()->vendorApplications()->latest()->first();

        if ($latestApp && ! $latestApp->isEditable()) {
            $status = $latestApp->status;
            $message = match ($status) {
                VendorApplication::STATUS_UNDER_REVIEW => 'Your application is under review. You cannot make changes at this time.',
                VendorApplication::STATUS_APPROVED => 'Your vendor application has been approved. You cannot make changes.',
                VendorApplication::STATUS_REJECTED => 'Your previous application was rejected. Please contact support or start a new application.',
                default => $latestApp->submitted_at
                    ? 'Your application has been submitted and is pending review. You cannot make changes at this time.'
                    : 'Your application is not editable at this time.',
            };

            return response()->json([
                'success' => false,
                'message' => $message,
                'data' => [
                    'application_status' => $status,
                    'is_submitted' => $latestApp->submitted_at !== null,
                    'can_start_new' => $status === VendorApplication::STATUS_REJECTED,
                ],
            ], 422);
        }

        return response()->json([
            'success' => false,
            'message' => 'No vendor application found. Please start from Step 1.',
        ], 404);
    }

    /**
     * Get required documents for Step 3 based on registration type.
     *
     * @return array<string, mixed>
     */
    private function getRequiredDocumentsForStep3(VendorApplication $application): array
    {
        if ($application->isRegisteredVendor()) {
            $required = [];
            if ($application->has_business_certificate) {
                $required[] = 'business_certificate_document';
            }
            if ($application->has_tin) {
                $required[] = 'tin_document';
            }

            return [
                'type' => 'registered',
                'required' => $required,
                'optional' => ['facebook_handle', 'instagram_handle', 'twitter_handle'],
            ];
        }

        return [
            'type' => 'unregistered',
            'required' => ['selfie_image', 'mobile_money_number', 'mobile_money_provider', 'proof_of_business'],
            'optional' => ['facebook_handle', 'instagram_handle', 'twitter_handle'],
        ];
    }

    /**
     * Get advice on next steps based on current application state.
     *
     * @return array<string, mixed>
     */
    private function getNextStepsAdvice(VendorApplication $application): array
    {
        if ($application->completed_step >= 4) {
            return [
                'can_submit' => true,
                'message' => 'All steps complete! You can review and submit your application.',
                'action' => 'Call POST /api/v1/vendor-registration/submit',
            ];
        }

        if ($application->completed_step === 3) {
            return [
                'can_submit' => false,
                'message' => 'Complete Step 4: Select bespoke services you can provide',
                'action' => 'Call POST /api/v1/vendor-registration/step-4/bespoke-services with service_ids',
                'example' => ['service_ids' => [1, 2]],
            ];
        }

        if ($application->completed_step === 2) {
            $vendorType = $application->isRegisteredVendor() ? 'registered' : 'unregistered';
            $endpoint = $vendorType === 'registered'
                ? '/api/v1/vendor-registration/step-3/registered-documents'
                : '/api/v1/vendor-registration/step-3/unregistered-documents';

            return [
                'can_submit' => false,
                'message' => "Complete Step 3: Upload {$vendorType} vendor documents",
                'action' => "Call POST {$endpoint}",
                'vendor_type' => $vendorType,
            ];
        }

        if ($application->completed_step === 1) {
            return [
                'can_submit' => false,
                'message' => 'Complete Step 2: Indicate if you have business registration documents',
                'action' => 'Call POST /api/v1/vendor-registration/step-2/business-registration',
            ];
        }

        return [
            'can_submit' => false,
            'message' => 'Complete Step 1: Upload Ghana Card images',
            'action' => 'Call POST /api/v1/vendor-registration/step-1/ghana-card',
        ];
    }
}
