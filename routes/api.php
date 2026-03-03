<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AddressController;
use App\Http\Controllers\Api\V1\Admin\JobMonitorController;
use App\Http\Controllers\Api\V1\AdvertisementController;
use App\Http\Controllers\Api\V1\AiChatController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\CouponController;
use App\Http\Controllers\Api\V1\FieldAgentDashboardController;
use App\Http\Controllers\Api\V1\FilterController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InfluencerDashboardController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\MarketerDashboardController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PartnerProfileController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PayoutRequestController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ReferralCodeController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\ReviewHelpfulController;
use App\Http\Controllers\Api\V1\ReviewReplyController;
use App\Http\Controllers\Api\V1\TargetController;
use App\Http\Controllers\Api\V1\VendorAnalyticsController;
use App\Http\Controllers\Api\V1\VendorOnboardingPaymentController;
use App\Http\Controllers\Api\V1\VendorRegistrationController;
use App\Http\Controllers\Api\V1\VendorReviewController;
use App\Http\Controllers\Api\V1\WishlistController;
use Illuminate\Support\Facades\Route;

// API V1 Routes
Route::prefix('v1')->group(function () {
    // Health check endpoint
    Route::get('/health', [HealthController::class, 'index']);

    // Paystack webhook (public - no auth required, validated via signature)
    Route::post('/payments/webhook', [PaymentController::class, 'webhook'])
        ->name('payments.webhook');

    // Paystack callback (public - redirect from Paystack after payment)
    Route::match(['get', 'post'], '/payments/callback', [PaymentController::class, 'callback'])
        ->name('payments.callback');

    // Auth routes (public)
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);

        // Phone OTP verification routes
        Route::post('verify-phone', [AuthController::class, 'verifyPhone']);
        Route::post('resend-otp', [AuthController::class, 'resendOtp']);

        // Legacy email verification routes (kept for backward compatibility)
        Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
            ->middleware('signed')
            ->name('api.verification.verify');
        Route::post('resend-verification', [AuthController::class, 'resendVerification']);

        // Authenticated auth routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('logout-all', [AuthController::class, 'revokeAllTokens']);
            Route::get('user', [AuthController::class, 'user']);
            Route::get('tokens', [AuthController::class, 'tokens']);
        });
    });

    // Profile routes (authenticated)
    Route::middleware('auth:sanctum')->prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::post('avatar', [ProfileController::class, 'updateAvatar']);
        Route::delete('avatar', [ProfileController::class, 'deleteAvatar']);
        Route::put('password', [ProfileController::class, 'updatePassword']);
    });

    // Account deletion (authenticated)
    Route::middleware('auth:sanctum')->delete('/account', [AccountController::class, 'destroy']);

    // Profile options routes (public - for dropdowns)
    Route::prefix('profile-options')->group(function () {
        Route::get('/interests', [ProfileController::class, 'interests']);
        Route::get('/personality-traits', [ProfileController::class, 'personalityTraits']);
    });

    // Filter options routes (public)
    Route::prefix('filters')->group(function () {
        Route::get('/', [FilterController::class, 'index']);
        Route::get('/categories', [FilterController::class, 'categories']);
        Route::get('/price-range', [FilterController::class, 'priceRange']);
        Route::get('/colors', [FilterController::class, 'colors']);
        Route::get('/occasions', [FilterController::class, 'occasions']);
        Route::get('/ratings', [FilterController::class, 'ratings']);
    });

    // Location services routes (public - Google Maps API integration)
    Route::prefix('locations')->group(function () {
        Route::get('/autocomplete', [LocationController::class, 'autocomplete']);
        Route::get('/place-details', [LocationController::class, 'placeDetails']);
        Route::get('/geocode', [LocationController::class, 'geocode']);
    });

    // Products & Services routes (public and authenticated)
    Route::get('/categories', [\App\Http\Controllers\Api\V1\CategoryController::class, 'index']);

    // Advertisements routes (public)
    Route::prefix('advertisements')->group(function () {
        Route::get('/', [AdvertisementController::class, 'index']);
        Route::get('/{advertisement}', [AdvertisementController::class, 'show']);
        Route::post('/{advertisement}/click', [AdvertisementController::class, 'trackClick']);
    });

    // Public shop routes
    Route::get('/shops', [\App\Http\Controllers\Api\V1\ShopController::class, 'index']);
    Route::get('/shops/{shop}', [\App\Http\Controllers\Api\V1\ShopController::class, 'show']);
    Route::get('/shops/{shop}/products', [\App\Http\Controllers\Api\V1\ShopController::class, 'products']);
    Route::get('/shops/{shop}/services', [\App\Http\Controllers\Api\V1\ShopController::class, 'services']);

    // Public product routes
    Route::get('/products', [\App\Http\Controllers\Api\V1\ProductController::class, 'index']);
    Route::get('/products/{product}', [\App\Http\Controllers\Api\V1\ProductController::class, 'show']);
    Route::get('/products/{product}/reviews', [ReviewController::class, 'productReviews']);

    // Public service routes
    Route::get('/services', [\App\Http\Controllers\Api\V1\ServiceController::class, 'index']);
    Route::get('/services/{service}', [\App\Http\Controllers\Api\V1\ServiceController::class, 'show']);
    Route::get('/services/{service}/reviews', [ReviewController::class, 'serviceReviews']);

    // Public review routes
    Route::get('/reviews', [ReviewController::class, 'index']);
    Route::get('/reviews/{review}', [ReviewController::class, 'show']);
    Route::get('/reviews/{review}/replies', [ReviewReplyController::class, 'index']);

    // Public vendor routes (for browsing vendor products/services before ordering)
    Route::get('/vendors', [\App\Http\Controllers\Api\V1\VendorController::class, 'index']);
    Route::get('/vendors/{vendor}', [\App\Http\Controllers\Api\V1\VendorController::class, 'show']);
    Route::get('/vendors/{vendor}/products', [\App\Http\Controllers\Api\V1\VendorController::class, 'products']);
    Route::get('/vendors/{vendor}/services', [\App\Http\Controllers\Api\V1\VendorController::class, 'services']);
    Route::get('/vendors/{vendor}/reviews', [ReviewController::class, 'vendorReviews']);

    // Cart routes (supports both authenticated users and guests with cart token)
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);
        Route::post('/items', [CartController::class, 'store']);
        Route::patch('/items/{cartItem}', [CartController::class, 'update']);
        Route::delete('/items/{cartItem}', [CartController::class, 'destroy']);
        Route::post('/clear', [CartController::class, 'clear']);
        Route::post('/merge', [CartController::class, 'merge'])->middleware('auth:sanctum');
    });

    // Vendor-only routes for products and services
    Route::middleware('auth:sanctum')->group(function () {
        // Shop management (vendors only)
        Route::get('/my-shops', [\App\Http\Controllers\Api\V1\ShopController::class, 'myShops']);
        Route::post('/shops', [\App\Http\Controllers\Api\V1\ShopController::class, 'store']);
        Route::put('/shops/{shop}', [\App\Http\Controllers\Api\V1\ShopController::class, 'update']);
        Route::delete('/shops/{shop}', [\App\Http\Controllers\Api\V1\ShopController::class, 'destroy']);

        // Product management (vendors only)
        Route::post('/products', [\App\Http\Controllers\Api\V1\ProductController::class, 'store']);
        Route::put('/products/{product}', [\App\Http\Controllers\Api\V1\ProductController::class, 'update']);
        Route::delete('/products/{product}', [\App\Http\Controllers\Api\V1\ProductController::class, 'destroy']);

        // Service management (vendors only)
        Route::post('/services', [\App\Http\Controllers\Api\V1\ServiceController::class, 'store']);
        Route::put('/services/{service}', [\App\Http\Controllers\Api\V1\ServiceController::class, 'update']);
        Route::delete('/services/{service}', [\App\Http\Controllers\Api\V1\ServiceController::class, 'destroy']);

        // Address management
        Route::get('/addresses', [AddressController::class, 'index']);
        Route::post('/addresses', [AddressController::class, 'store']);
        Route::get('/addresses/{address}', [AddressController::class, 'show']);
        Route::put('/addresses/{address}', [AddressController::class, 'update']);
        Route::delete('/addresses/{address}', [AddressController::class, 'destroy']);
        Route::post('/addresses/{address}/set-default', [AddressController::class, 'setDefault']);

        // Review management
        Route::post('/reviews', [ReviewController::class, 'store']);
        Route::put('/reviews/{review}', [ReviewController::class, 'update']);
        Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);
        Route::post('/reviews/{review}/helpful', [ReviewHelpfulController::class, 'toggle']);

        Route::middleware('role:vendor')->group(function () {
            Route::get('/vendor/reviews', [VendorReviewController::class, 'index']);
            Route::post('/reviews/{review}/replies', [ReviewReplyController::class, 'store']);
            Route::put('/review-replies/{reviewReply}', [ReviewReplyController::class, 'update']);
            Route::delete('/review-replies/{reviewReply}', [ReviewReplyController::class, 'destroy']);
        });

        // Wishlist routes
        Route::get('/wishlist', [WishlistController::class, 'index']);
        Route::post('/wishlist/toggle', [WishlistController::class, 'toggle']);
        Route::get('/wishlist/check', [WishlistController::class, 'check']);
        Route::delete('/wishlist/clear', [WishlistController::class, 'clear']);

        // Notification routes
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread', [NotificationController::class, 'unread']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::patch('/notifications/{notification}/unread', [NotificationController::class, 'markAsUnread']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);

        // Coupon routes
        Route::get('/coupons/available', [CouponController::class, 'available']);
        Route::post('/coupons/apply', [CouponController::class, 'apply']);
        Route::apiResource('coupons', CouponController::class);

        // Report routes (authenticated users)
        Route::get('/report-categories', [ReportController::class, 'categories']);
        Route::get('/reports', [ReportController::class, 'index']);
        Route::post('/reports', [ReportController::class, 'store']);
        Route::get('/reports/{report}', [ReportController::class, 'show']);
        Route::post('/reports/{report}/cancel', [ReportController::class, 'cancel']);

        // Order routes
        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders/statistics', [OrderController::class, 'statistics']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::post('/orders/{order}/status', [OrderController::class, 'updateStatus']);
        Route::get('/orders/{order}/track', [OrderController::class, 'track']);

        // Payment routes (Paystack integration)
        Route::prefix('payments')->group(function () {
            Route::get('/', [PaymentController::class, 'index']);
            Route::get('/{payment}', [PaymentController::class, 'show'])->whereNumber('payment');
            Route::post('/initiate', [PaymentController::class, 'initiate']);
            Route::post('/verify', [PaymentController::class, 'verify']);
            Route::get('/order/{order}', [PaymentController::class, 'orderPaymentStatus']);
            Route::post('/{payment}/retry', [PaymentController::class, 'retry']);
        });

        // Vendor Analytics routes (vendors and admins only)
        Route::prefix('vendor/analytics')->group(function () {
            Route::get('/', [VendorAnalyticsController::class, 'index']);
            Route::get('/overview', [VendorAnalyticsController::class, 'overview']);
            Route::get('/revenue-by-category', [VendorAnalyticsController::class, 'revenueByCategory']);
            Route::get('/top-products', [VendorAnalyticsController::class, 'topProducts']);
            Route::get('/trends', [VendorAnalyticsController::class, 'trends']);
        });

        // Vendor Balance routes (vendors only)
        Route::prefix('vendor')->group(function () {
            Route::get('/balance', [\App\Http\Controllers\Api\V1\VendorBalanceController::class, 'index']);
            Route::get('/transactions', [\App\Http\Controllers\Api\V1\VendorBalanceController::class, 'transactions']);
        });

        // Chat routes (real-time messaging with vendors)
        Route::prefix('chat')->group(function () {
            // Conversations
            Route::get('/conversations', [ChatController::class, 'conversations']);
            Route::post('/conversations', [ChatController::class, 'startConversation']);
            Route::get('/conversations/search', [ChatController::class, 'searchConversations']);
            Route::get('/conversations/{conversation}', [ChatController::class, 'showConversation']);
            // Delete disabled for database integrity
            // Route::delete('/conversations/{conversation}', [ChatController::class, 'deleteConversation']);

            // Messages within a conversation
            Route::get('/conversations/{conversation}/messages', [ChatController::class, 'messages']);
            Route::post('/conversations/{conversation}/messages', [ChatController::class, 'sendMessage']);
            Route::post('/conversations/{conversation}/read', [ChatController::class, 'markAsRead']);
            Route::post('/conversations/{conversation}/typing', [ChatController::class, 'typing']);

            // Unread count
            Route::get('/unread-count', [ChatController::class, 'unreadCount']);
        });

        // AI Gift Assistant chat routes
        Route::prefix('ai-chat')->group(function () {
            Route::get('/conversations', [AiChatController::class, 'index']);
            Route::post('/conversations', [AiChatController::class, 'store']);
            Route::get('/conversations/{aiConversation}', [AiChatController::class, 'show']);
            Route::post('/conversations/{aiConversation}/messages', [AiChatController::class, 'sendMessage']);
            Route::delete('/conversations/{aiConversation}', [AiChatController::class, 'destroy']);
        });

        // Partner profile management
        Route::apiResource('partner-profiles', PartnerProfileController::class);

        // Vendor Registration routes (multi-step wizard)
        Route::prefix('vendor-registration')->group(function () {
            // Get application status and available services
            Route::get('/status', [VendorRegistrationController::class, 'status']);
            Route::get('/bespoke-services', [VendorRegistrationController::class, 'getBespokeServices']);
            Route::get('/review', [VendorRegistrationController::class, 'review']);

            // Debug endpoint (TODO: Remove in production or add admin middleware)
            Route::get('/debug', [VendorRegistrationController::class, 'debug']);

            // Step 1: Ghana Card upload
            Route::post('/step-1/ghana-card', [VendorRegistrationController::class, 'storeGhanaCard']);

            // Step 2: Business Registration flags
            Route::post('/step-2/business-registration', [VendorRegistrationController::class, 'storeBusinessRegistration']);

            // Step 3A: Registered vendor documents (has business certificate/TIN)
            Route::post('/step-3/registered-documents', [VendorRegistrationController::class, 'storeRegisteredVendorDocuments']);

            // Step 3B: Unregistered vendor verification (no business documents)
            Route::post('/step-3/unregistered-documents', [VendorRegistrationController::class, 'storeUnregisteredVendorDocuments']);

            // Step 4: Bespoke Services selection
            Route::post('/step-4/bespoke-services', [VendorRegistrationController::class, 'storeBespokeServices']);

            // Step 5: Payment (new - required before submission)
            Route::prefix('payment')->group(function () {
                Route::get('/summary', [VendorOnboardingPaymentController::class, 'getPaymentSummary']);
                Route::get('/status', [VendorOnboardingPaymentController::class, 'status']);
                Route::post('/validate-coupon', [VendorOnboardingPaymentController::class, 'validateCoupon']);
                Route::post('/initiate', [VendorOnboardingPaymentController::class, 'initiate']);
                Route::post('/verify', [VendorOnboardingPaymentController::class, 'verify']);
            });

            // Step 6: Submit application (after payment)
            Route::post('/submit', [VendorRegistrationController::class, 'submit']);
        });

        // Vendor onboarding payment webhook (no auth required)
        Route::post('/vendor-onboarding-payment/webhook', [VendorOnboardingPaymentController::class, 'webhook'])
            ->name('api.v1.vendor-onboarding-payment.webhook')
            ->withoutMiddleware(['auth:sanctum']);

        // Vendor onboarding payment callback (no auth required initially, validates inside)
        Route::get('/vendor-onboarding-payment/callback', [VendorOnboardingPaymentController::class, 'callback'])
            ->name('api.v1.vendor-onboarding-payment.callback')
            ->withoutMiddleware(['auth:sanctum']);

        // Admin routes (admin only)
        Route::prefix('admin')->middleware('admin')->group(function () {
            // Vendor management
            Route::put('/vendors/{vendor}', [\App\Http\Controllers\Api\V1\VendorController::class, 'update'])->name('api.vendors.update');

            // Category management
            Route::apiResource('categories', \App\Http\Controllers\Api\V1\Admin\AdminCategoryController::class)->names([
                'index' => 'api.categories.index',
                'store' => 'api.categories.store',
                'show' => 'api.categories.show',
                'update' => 'api.categories.update',
                'destroy' => 'api.categories.destroy',
            ]);

            // Interest management
            Route::apiResource('interests', \App\Http\Controllers\Api\V1\Admin\AdminInterestController::class)->names([
                'index' => 'api.interests.index',
                'store' => 'api.interests.store',
                'show' => 'api.interests.show',
                'update' => 'api.interests.update',
                'destroy' => 'api.interests.destroy',
            ]);

            // Personality trait management
            Route::apiResource('personality-traits', \App\Http\Controllers\Api\V1\Admin\AdminPersonalityTraitController::class)->names([
                'index' => 'api.personality-traits.index',
                'store' => 'api.personality-traits.store',
                'show' => 'api.personality-traits.show',
                'update' => 'api.personality-traits.update',
                'destroy' => 'api.personality-traits.destroy',
            ]);

            // Payout request management
            Route::get('payout-requests', [PayoutRequestController::class, 'index']);
            Route::post('payout-requests/{payoutRequest}/approve', [PayoutRequestController::class, 'approve']);
            Route::post('payout-requests/{payoutRequest}/reject', [PayoutRequestController::class, 'reject']);

            // Vendor Balance management (admin only)
            Route::prefix('vendor-balances')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\V1\Admin\AdminVendorBalanceController::class, 'index']);
                Route::get('/{vendor}', [\App\Http\Controllers\Api\V1\Admin\AdminVendorBalanceController::class, 'show']);
                Route::post('/{vendor}/payout', [\App\Http\Controllers\Api\V1\Admin\AdminVendorBalanceController::class, 'payout']);
            });

            // Target management
            Route::apiResource('targets', TargetController::class)->names([
                'index' => 'api.targets.index',
                'store' => 'api.targets.store',
                'show' => 'api.targets.show',
                'update' => 'api.targets.update',
                'destroy' => 'api.targets.destroy',
            ]);

            // Job monitoring routes
            Route::prefix('jobs')->group(function () {
                Route::get('/stats', [JobMonitorController::class, 'stats']);
                Route::get('/failed', [JobMonitorController::class, 'index']);
                Route::get('/failed/{id}', [JobMonitorController::class, 'show']);
                Route::post('/failed/{id}/retry', [JobMonitorController::class, 'retry']);
                Route::post('/retry-all', [JobMonitorController::class, 'retryAll']);
                Route::delete('/clear', [JobMonitorController::class, 'clear']);
            });
        });

        // Influencer dashboard and referral code routes
        Route::middleware('role:influencer')->group(function () {
            Route::get('influencer/dashboard', [InfluencerDashboardController::class, 'index']);
            Route::get('influencer/referrals', [InfluencerDashboardController::class, 'referrals']);
            Route::get('influencer/earnings', [InfluencerDashboardController::class, 'earnings']);
            Route::get('influencer/payouts', [InfluencerDashboardController::class, 'payouts']);

            // Referral code management
            Route::apiResource('referral-codes', ReferralCodeController::class)->names([
                'index' => 'api.referral-codes.index',
                'store' => 'api.referral-codes.store',
                'show' => 'api.referral-codes.show',
                'update' => 'api.referral-codes.update',
                'destroy' => 'api.referral-codes.destroy',
            ]);
        });

        // Field agent dashboard routes
        Route::middleware('role:field_agent')->group(function () {
            Route::get('field-agent/dashboard', [FieldAgentDashboardController::class, 'index']);
            Route::get('field-agent/targets', [FieldAgentDashboardController::class, 'targets']);
            Route::get('field-agent/earnings', [FieldAgentDashboardController::class, 'earnings']);
        });

        // Marketer dashboard routes
        Route::middleware('role:marketer')->group(function () {
            Route::get('marketer/dashboard', [MarketerDashboardController::class, 'index']);
            Route::get('marketer/targets', [MarketerDashboardController::class, 'targets']);
            Route::get('marketer/quarterly-earnings', [MarketerDashboardController::class, 'quarterlyEarnings']);
        });

        // Payout request routes (for influencers, field agents, and marketers)
        Route::middleware('role:influencer,field_agent,marketer')->group(function () {
            Route::get('payout-requests', [PayoutRequestController::class, 'index']);
            Route::post('payout-requests', [PayoutRequestController::class, 'store']);
            Route::get('payout-requests/{payoutRequest}', [PayoutRequestController::class, 'show']);
            Route::post('payout-requests/{payoutRequest}/cancel', [PayoutRequestController::class, 'cancel']);
        });

        // Admin platform commission tracking
        Route::middleware('role:admin,super_admin')->prefix('admin')->group(function () {
            Route::get('commission/statistics', [\App\Http\Controllers\Api\V1\PlatformCommissionController::class, 'index']);
            Route::get('commission/monthly-trend', [\App\Http\Controllers\Api\V1\PlatformCommissionController::class, 'monthlyTrend']);

            // Admin payout management
            Route::get('payouts', [\App\Http\Controllers\Api\V1\AdminPayoutController::class, 'index']);
            Route::get('payouts/{payoutRequest}', [\App\Http\Controllers\Api\V1\AdminPayoutController::class, 'show']);
            Route::post('payouts/{payoutRequest}/approve', [\App\Http\Controllers\Api\V1\AdminPayoutController::class, 'approve']);
            Route::post('payouts/{payoutRequest}/reject', [\App\Http\Controllers\Api\V1\AdminPayoutController::class, 'reject']);
            Route::post('payouts/{payoutRequest}/mark-paid', [\App\Http\Controllers\Api\V1\AdminPayoutController::class, 'markAsPaid']);
        });

        // Vendor payout requests
        Route::middleware('role:vendor')->prefix('vendor')->group(function () {
            Route::get('payouts', [\App\Http\Controllers\Api\V1\VendorPayoutController::class, 'index']);
            Route::post('payouts/request', [\App\Http\Controllers\Api\V1\VendorPayoutController::class, 'store']);
            Route::get('payouts/{payoutRequest}', [\App\Http\Controllers\Api\V1\VendorPayoutController::class, 'show']);
            Route::get('balance', [\App\Http\Controllers\Api\V1\VendorPayoutController::class, 'balance']);
        });
    });

    // Delivery confirmation (public - no auth required)
    Route::post('delivery/confirm', [\App\Http\Controllers\Api\V1\DeliveryConfirmationController::class, 'confirm']);
    Route::post('delivery/verify', [\App\Http\Controllers\Api\V1\DeliveryConfirmationController::class, 'verify']);
});
