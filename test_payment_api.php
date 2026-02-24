<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Find user with token ID 11 (from create_test_vendor.php)
$token = \Laravel\Sanctum\PersonalAccessToken::find(11);

if (! $token) {
    echo "Token not found!\n";
    exit(1);
}

$user = $token->tokenable;

if (! $user) {
    echo "User not found for token!\n";
    exit(1);
}

echo "User: {$user->name} ({$user->email})\n";

// Get latest vendor application
$application = $user->vendorApplications()->latest()->first();

if (! $application) {
    echo "No vendor application found!\n";
    exit(1);
}

echo "Application ID: {$application->id}\n";
echo "Completed Step: {$application->completed_step}\n";
echo 'Payment Completed: '.($application->payment_completed ? 'Yes' : 'No')."\n";
echo "Vendor Tier: {$application->getVendorTier()}\n";
echo "Onboarding Fee: GHS {$application->getOnboardingFee()}\n\n";

// Test payment initialization
echo "Testing payment initialization...\n";

$service = new \App\Services\VendorOnboardingPaymentService;

$result = $service->initializePayment(
    $application,
    null, // no coupon
    'https://dashboard.surprisemoi.com/api/v1/vendor-onboarding-payment/callback'
);

echo "\nResult:\n";
echo 'Success: '.($result['success'] ? 'Yes' : 'No')."\n";
echo "Message: {$result['message']}\n";

if ($result['success']) {
    echo "\nPayment Data:\n";
    echo "Authorization URL: {$result['data']['authorization_url']}\n";
    echo "Access Code: {$result['data']['access_code']}\n";
    echo "Reference: {$result['data']['reference']}\n";
} else {
    echo "\nError occurred. Check logs for details.\n";
}
