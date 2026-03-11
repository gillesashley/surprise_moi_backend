<?php

use App\Http\Controllers\Api\Rider\V1\AuthController;
use App\Http\Controllers\Api\Rider\V1\DashboardController;
use App\Http\Controllers\Api\Rider\V1\DeliveryController;
use App\Http\Controllers\Api\Rider\V1\EarningController;
use App\Http\Controllers\Api\Rider\V1\OnboardingController;
use App\Http\Controllers\Api\Rider\V1\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Public auth routes
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('otp/send', [AuthController::class, 'sendOtp']);
        Route::post('otp/verify', [AuthController::class, 'verifyOtp']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
    });

    // Authenticated routes (any approval status)
    Route::middleware('auth:rider')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // Onboarding (pre-approval)
        Route::prefix('onboarding')->group(function () {
            Route::post('documents', [OnboardingController::class, 'submitDocuments']);
            Route::get('status', [OnboardingController::class, 'status']);
            Route::put('documents', [OnboardingController::class, 'resubmitDocuments']);
        });

        // Approved riders only
        Route::middleware('rider.approved')->group(function () {
            // Dashboard
            Route::get('dashboard', [DashboardController::class, 'index']);
            Route::post('dashboard/toggle-online', [DashboardController::class, 'toggleOnline']);
            Route::post('dashboard/location', [DashboardController::class, 'updateLocation']);
            Route::put('dashboard/device-token', [DashboardController::class, 'updateDeviceToken']);

            // Deliveries
            Route::prefix('deliveries')->group(function () {
                Route::get('incoming', [DeliveryController::class, 'incoming']);
                Route::get('active', [DeliveryController::class, 'active']);
                Route::get('history', [DeliveryController::class, 'history']);
                Route::get('{deliveryRequest}', [DeliveryController::class, 'show']);
                Route::post('{deliveryRequest}/accept', [DeliveryController::class, 'accept']);
                Route::post('{deliveryRequest}/decline', [DeliveryController::class, 'decline']);
                Route::post('{deliveryRequest}/pickup', [DeliveryController::class, 'pickup']);
                Route::post('{deliveryRequest}/deliver', [DeliveryController::class, 'deliver']);
                Route::post('{deliveryRequest}/cancel', [DeliveryController::class, 'cancel']);
            });

            // Earnings
            Route::prefix('earnings')->group(function () {
                Route::get('/', [EarningController::class, 'index']);
                Route::get('transactions', [EarningController::class, 'transactions']);
                Route::post('withdraw', [EarningController::class, 'withdraw']);
                Route::get('withdrawals', [EarningController::class, 'withdrawals']);
            });

            // Profile
            Route::get('profile', [ProfileController::class, 'show']);
            Route::put('profile', [ProfileController::class, 'update']);
            Route::put('profile/vehicle', [ProfileController::class, 'updateVehicle']);
            Route::put('profile/password', [ProfileController::class, 'updatePassword']);
        });
    });
});
