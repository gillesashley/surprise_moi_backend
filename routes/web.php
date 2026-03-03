<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdvertisementController;
use App\Http\Controllers\BespokeServiceController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ContentManagementController;
use App\Http\Controllers\FieldAgentDashboardController;
use App\Http\Controllers\InfluencerDashboardController;
use App\Http\Controllers\InterestController;
use App\Http\Controllers\MarketerDashboardController;
use App\Http\Controllers\MusicGenreController;
use App\Http\Controllers\PersonalityTraitController;
use App\Http\Controllers\ProductShareController;
use App\Http\Controllers\ReferralCodeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TargetController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendorApplicationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::prefix('.well-known')->group(function () {
    Route::get('/assetlinks.json', [ProductShareController::class, 'assetLinks'])
        ->name('well-known.assetlinks');
    Route::get('/apple-app-site-association', [ProductShareController::class, 'appleAppSiteAssociation'])
        ->name('well-known.apple-app-site-association');
});

Route::get('/products/{slug}', [ProductShareController::class, 'show'])
    ->where('slug', '[A-Za-z0-9]{16}')
    ->name('products.share');

Route::get('/products/{id}', [ProductShareController::class, 'showById'])
    ->whereNumber('id')
    ->name('products.share.legacy');

// Admin Dashboard routes - only for admin and super_admin users
Route::middleware(['auth', 'dashboard'])->prefix('dashboard')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Commission Statistics
    Route::get('commission-statistics', [AdminDashboardController::class, 'commissionStatistics'])->name('commission-statistics');

    // Vendor Payouts Management
    Route::get('vendor-payouts', [AdminDashboardController::class, 'vendorPayouts'])->name('vendor-payouts');

    // All Transactions
    Route::get('transactions', [AdminDashboardController::class, 'allTransactions'])->name('transactions');

    // Jobs Dashboard (super_admin only)
    Route::get('jobs', [AdminDashboardController::class, 'jobs'])->name('jobs')->middleware('role:super_admin');

    Route::resource('users', UserController::class)->names([
        'index' => 'users.index',
        'create' => 'users.create',
        'store' => 'users.store',
        'show' => 'users.show',
        'edit' => 'users.edit',
        'update' => 'users.update',
        'destroy' => 'users.destroy',
    ]);

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

    // Vendor Application Management
    Route::prefix('vendor-applications')->name('vendor-applications.')->group(function () {
        Route::get('/', [VendorApplicationController::class, 'index'])->name('index');
        Route::get('/{vendorApplication}', [VendorApplicationController::class, 'show'])->name('show');
        Route::post('/{vendorApplication}/approve', [VendorApplicationController::class, 'approve'])->name('approve');
        Route::post('/{vendorApplication}/reject', [VendorApplicationController::class, 'reject'])->name('reject');
        Route::post('/{vendorApplication}/under-review', [VendorApplicationController::class, 'markUnderReview'])->name('under-review');
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
