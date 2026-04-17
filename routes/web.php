<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdvertisementController;
use App\Http\Controllers\BespokeServiceController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ContentManagementController;
use App\Http\Controllers\CouponManagementController;
use App\Http\Controllers\FieldAgentDashboardController;
use App\Http\Controllers\InfluencerDashboardController;
use App\Http\Controllers\InterestController;
use App\Http\Controllers\MarketerDashboardController;
use App\Http\Controllers\MusicGenreController;
use App\Http\Controllers\OrderManagementController;
use App\Http\Controllers\PaymentManagementController;
use App\Http\Controllers\PersonalityTraitController;
use App\Http\Controllers\ProductShareController;
use App\Http\Controllers\ReferralCodeController;
use App\Http\Controllers\RegionLookupController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TargetController;
use App\Http\Controllers\TreasuryController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserManagementAccessController;
use App\Http\Controllers\VendorApplicationController;
use App\Http\Controllers\WawVideoShareController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::prefix('field-agents')->name('field-agents.')->group(function () {
    Route::get('regions', [RegionLookupController::class, 'index'])
        ->middleware('throttle:30,1')
        ->name('regions');
    Route::get('register', [\App\Http\Controllers\FieldAgentRegistrationController::class, 'create'])->name('register');
    Route::post('register', [\App\Http\Controllers\FieldAgentRegistrationController::class, 'store'])
        ->middleware('throttle:20,60')
        ->name('register.store');
    Route::get('register/submitted', [\App\Http\Controllers\FieldAgentRegistrationController::class, 'submitted'])->name('register.submitted');
});

$deepLinkRoutes = function () {
    Route::prefix('.well-known')->group(function () {
        Route::get('/assetlinks.json', [ProductShareController::class, 'assetLinks'])
            ->name('well-known.assetlinks');
        Route::get('/apple-app-site-association', [ProductShareController::class, 'appleAppSiteAssociation'])
            ->name('well-known.apple-app-site-association');
    });

    Route::get('/products/{id}', [ProductShareController::class, 'showById'])
        ->whereNumber('id')
        ->name('products.share.legacy');

    Route::get('/products/{slug}', [ProductShareController::class, 'show'])
        ->where('slug', '[A-Za-z0-9][A-Za-z0-9\-]*')
        ->name('products.share');
};

$deepLinkDomain = config('deep_links.domain');
if ($deepLinkDomain) {
    Route::domain($deepLinkDomain)->group($deepLinkRoutes);
} else {
    $deepLinkRoutes();
}

Route::get('/waw/{wawVideo}', WawVideoShareController::class)->name('waw.share');

// User Management Access Code verification
Route::middleware(['auth', 'dashboard'])->group(function () {
    Route::get('user-management-access', [UserManagementAccessController::class, 'show'])
        ->name('user-management-access.show');
    Route::post('user-management-access', [UserManagementAccessController::class, 'verify'])
        ->name('user-management-access.verify');
});

// Admin Dashboard routes - only for admin and super_admin users
Route::middleware(['auth', 'dashboard'])->prefix('dashboard')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Commission Statistics
    Route::get('commission-statistics', [AdminDashboardController::class, 'commissionStatistics'])->name('commission-statistics');

    // Vendor Payouts Management
    Route::get('vendor-payouts', [AdminDashboardController::class, 'vendorPayouts'])->name('vendor-payouts');

    // All Transactions
    Route::get('transactions', [AdminDashboardController::class, 'allTransactions'])->name('transactions');

    // Vendor Onboarding Stats
    Route::get('vendor-onboarding-stats', [AdminDashboardController::class, 'vendorOnboardingStats'])->name('vendor-onboarding-stats');

    Route::get('users/export-pdf', [UserController::class, 'exportPdf'])
        ->name('users.export-pdf')
        ->middleware('user-management');

    Route::resource('users', UserController::class)->names([
        'index' => 'users.index',
        'create' => 'users.create',
        'store' => 'users.store',
        'show' => 'users.show',
        'edit' => 'users.edit',
        'update' => 'users.update',
        'destroy' => 'users.destroy',
    ])->middleware('user-management');

    // Targets Management
    Route::resource('targets', TargetController::class)->names([
        'index' => 'targets.index',
        'create' => 'targets.create',
        'store' => 'targets.store',
        'show' => 'targets.show',
        'edit' => 'targets.edit',
        'update' => 'targets.update',
        'destroy' => 'targets.destroy',
    ]);

    // Referral Codes Management
    // Cascading user-picker endpoint for referral code create form.
    // MUST be registered before Route::resource so Laravel doesn't
    // treat 'users-by-role' as a {referralCode} parameter.
    Route::get('referral-codes/users-by-role', [ReferralCodeController::class, 'usersByRole'])
        ->name('referral-codes.users-by-role');
    Route::post('referral-codes/bulk-generate/preview', [ReferralCodeController::class, 'bulkGeneratePreview'])
        ->name('referral-codes.bulk-generate.preview');
    Route::post('referral-codes/bulk-generate', [ReferralCodeController::class, 'bulkGenerate'])
        ->name('referral-codes.bulk-generate');
    Route::resource('referral-codes', ReferralCodeController::class)->names([
        'index' => 'referral-codes.index',
        'create' => 'referral-codes.create',
        'store' => 'referral-codes.store',
        'show' => 'referral-codes.show',
        'edit' => 'referral-codes.edit',
        'update' => 'referral-codes.update',
        'destroy' => 'referral-codes.destroy',
    ]);
    Route::post('referral-codes/{referralCode}/toggle', [ReferralCodeController::class, 'toggle'])->name('referral-codes.toggle');

    // Referral Milestone Rewards (admin fulfillment dashboard)
    Route::get('referral-milestones', [\App\Http\Controllers\ReferralMilestoneRewardController::class, 'index'])
        ->name('referral-milestones.index');
    Route::patch('referral-milestones/{reward}/fulfill', [\App\Http\Controllers\ReferralMilestoneRewardController::class, 'fulfill'])
        ->name('referral-milestones.fulfill');

    // Vendor Application Management
    Route::prefix('vendor-applications')->name('vendor-applications.')->group(function () {
        Route::get('/', [VendorApplicationController::class, 'index'])->name('index');
        Route::get('/{vendorApplication}', [VendorApplicationController::class, 'show'])->name('show');
        Route::post('/{vendorApplication}/approve', [VendorApplicationController::class, 'approve'])->name('approve');
        Route::post('/{vendorApplication}/reject', [VendorApplicationController::class, 'reject'])->name('reject');
        Route::post('/{vendorApplication}/under-review', [VendorApplicationController::class, 'markUnderReview'])->name('under-review');
        Route::delete('/{vendorApplication}', [VendorApplicationController::class, 'destroy'])->name('destroy');
    });

    // Field Agent Application Management (admin review)
    Route::prefix('field-agent-applications')->name('admin.field-agent-applications.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\FieldAgentApplicationController::class, 'index'])->name('index');
        Route::get('/{fieldAgentApplication}', [\App\Http\Controllers\Admin\FieldAgentApplicationController::class, 'show'])->name('show');
        Route::post('/{fieldAgentApplication}/approve', [\App\Http\Controllers\Admin\FieldAgentApplicationController::class, 'approve'])->name('approve');
        Route::post('/{fieldAgentApplication}/reject', [\App\Http\Controllers\Admin\FieldAgentApplicationController::class, 'reject'])->name('reject');
    });

    // Customer Support Tickets
    Route::prefix('customer-support')->name('dashboard.customer-support.')->group(function () {
        Route::post('/', [\App\Http\Controllers\Admin\SupportTicketController::class, 'store'])->name('store');
        Route::get('/{ticket}', fn () => '')->name('show'); // placeholder until Task 5
    });

    // Reports & Conflicts Management
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/{report}', [ReportController::class, 'show'])->name('show');
        Route::post('/{report}/status', [ReportController::class, 'updateStatus'])->name('update-status');
        Route::post('/{report}/resolve', [ReportController::class, 'resolve'])->name('resolve');
    });

    // Consolidated content management page
    Route::get('content-management', [ContentManagementController::class, 'index'])->name('content-management');

    // Individual resource routes (for create/edit/delete operations)
    Route::resource('categories', CategoryController::class)->names('dashboard.categories');
    Route::resource('interests', InterestController::class)->names('dashboard.interests');
    Route::resource('personality-traits', PersonalityTraitController::class)->names('dashboard.personality-traits');
    Route::resource('music-genres', MusicGenreController::class)->names('dashboard.music-genres');
    Route::resource('bespoke-services', BespokeServiceController::class)->names('dashboard.bespoke-services');
    Route::resource('advertisements', AdvertisementController::class)->names('dashboard.advertisements');
    Route::resource('coupons', CouponManagementController::class)->names('dashboard.coupons');

    // Order Management
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [OrderManagementController::class, 'index'])->name('index');
        Route::get('/{order}', [OrderManagementController::class, 'show'])->name('show');
        Route::post('/{order}/status', [OrderManagementController::class, 'updateStatus'])->name('update-status');
    });

    // Payment Management
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/', [PaymentManagementController::class, 'index'])->name('index');
        Route::get('/{type}/{id}', [PaymentManagementController::class, 'show'])->name('show')
            ->whereIn('type', ['order', 'vendor-onboarding']);
        Route::post('/{type}/{id}/verify', [PaymentManagementController::class, 'verify'])->name('verify')
            ->whereIn('type', ['order', 'vendor-onboarding']);
        Route::post('/{type}/{id}/sync', [PaymentManagementController::class, 'sync'])->name('sync')
            ->whereIn('type', ['order', 'vendor-onboarding']);
    });

    // Treasury (super_admin only - enforced in controller)
    Route::prefix('treasury')->name('treasury.')->group(function () {
        Route::get('/', [TreasuryController::class, 'index'])->name('index');
        Route::get('/transactions', [TreasuryController::class, 'transactions'])->name('transactions');
        Route::get('/settlements', [TreasuryController::class, 'settlements'])->name('settlements');
        Route::get('/transfers', [TreasuryController::class, 'transfers'])->name('transfers');

        Route::post('/refresh', [TreasuryController::class, 'refresh'])->name('refresh');

        Route::get('/bank-account', [TreasuryController::class, 'bankAccount'])->name('bank-account');
        Route::post('/bank-account', [TreasuryController::class, 'saveBankAccount'])->name('bank-account.save');
        Route::post('/bank-account/verify', [TreasuryController::class, 'verifyBankAccount'])->name('bank-account.verify');

        Route::post('/transfer', [TreasuryController::class, 'initiateTransfer'])
            ->middleware('throttle:treasury-transfer')
            ->name('transfer.initiate');
        Route::post('/transfer/finalize', [TreasuryController::class, 'finalizeTransfer'])->name('transfer.finalize');
        Route::post('/transfer/resend-otp', [TreasuryController::class, 'resendTransferOtp'])->name('transfer.resend-otp');
    });

    // SPA catch-all - must be LAST in the group
    Route::get('/{any?}', [AdminDashboardController::class, 'index'])
        ->where('any', '.*')
        ->name('dashboard.spa');
});

// Influencer Dashboard routes
Route::middleware(['auth', 'dashboard'])->prefix('influencer')->name('influencer.')->group(function () {
    Route::get('dashboard', [InfluencerDashboardController::class, 'index'])->name('dashboard');
    Route::get('referrals', [InfluencerDashboardController::class, 'referrals'])->name('referrals');
    Route::get('earnings', [InfluencerDashboardController::class, 'earnings'])->name('earnings');
    Route::get('payouts', [InfluencerDashboardController::class, 'payouts'])->name('payouts');

    // SPA catch-all - must be LAST in the group
    Route::get('/{any?}', [InfluencerDashboardController::class, 'index'])
        ->where('any', '.*')
        ->name('influencer.spa');
});

// Field Agent Dashboard routes
Route::middleware(['auth', 'dashboard'])->prefix('field-agent')->name('field-agent.')->group(function () {
    Route::get('dashboard', [FieldAgentDashboardController::class, 'index'])->name('dashboard');
    Route::get('targets', [FieldAgentDashboardController::class, 'targets'])->name('targets');
    Route::get('earnings', [FieldAgentDashboardController::class, 'earnings'])->name('earnings');
    Route::get('payouts', [FieldAgentDashboardController::class, 'payouts'])->name('payouts');
    Route::get('verification', [\App\Http\Controllers\FieldAgentVerificationController::class, 'show'])->name('verification');

    // SPA catch-all - must be LAST in the group
    Route::get('/{any?}', [FieldAgentDashboardController::class, 'index'])
        ->where('any', '.*')
        ->name('field-agent.spa');
});

// Marketer Dashboard routes
Route::middleware(['auth', 'dashboard'])->prefix('marketer')->name('marketer.')->group(function () {
    Route::get('dashboard', [MarketerDashboardController::class, 'index'])->name('dashboard');
    Route::get('targets', [MarketerDashboardController::class, 'targets'])->name('targets');
    Route::get('earnings', [MarketerDashboardController::class, 'earnings'])->name('earnings');
    Route::get('payouts', [MarketerDashboardController::class, 'payouts'])->name('payouts');

    // SPA catch-all - must be LAST in the group
    Route::get('/{any?}', [MarketerDashboardController::class, 'index'])
        ->where('any', '.*')
        ->name('marketer.spa');
});

// Delivery Confirmation Page (Public - for delivery personnel)
Route::get('/delivery-confirm', function () {
    return view('delivery-confirm');
})->name('delivery.confirm');

// Account Deletion Request Page (Public - required by Google Play)
Route::get('/account-deletion', [\App\Http\Controllers\AccountDeletionController::class, 'show'])
    ->name('account-deletion.show');
Route::post('/account-deletion', [\App\Http\Controllers\AccountDeletionController::class, 'submit'])
    ->name('account-deletion.submit');

require __DIR__.'/settings.php';
