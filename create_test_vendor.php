<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\VendorApplication;

// Create or find a test vendor user
$user = User::firstOrCreate(
    ['email' => 'testvendor@example.com'],
    [
        'name' => 'Test Vendor',
        'password' => bcrypt('password123'),
        'phone' => '0241234567',
        'role' => 'vendor',
        'email_verified_at' => now(),
    ]
);

echo "User created/found: {$user->name} ({$user->email})\n";

// Create a vendor application with completed steps
$application = VendorApplication::firstOrCreate(
    ['user_id' => $user->id],
    [
        'ghana_card_front' => 'test/ghana-card-front.jpg',
        'ghana_card_back' => 'test/ghana-card-back.jpg',
        'has_business_certificate' => false,
        'has_tin' => false,
        'selfie_image' => 'test/selfie.jpg',
        'mobile_money_number' => '0241234567',
        'mobile_money_provider' => 'mtn',
        'proof_of_business' => 'test/proof.pdf',
        'current_step' => 5,
        'completed_step' => 4,
        'status' => 'pending',
        'payment_completed' => false,
    ]
);

echo "Application created/found: ID {$application->id}\n";
echo "Completed Step: {$application->completed_step}\n";
echo "Vendor Tier: {$application->getVendorTier()} (2 = Individual)\n";
echo "Onboarding Fee: GHS {$application->getOnboardingFee()}\n\n";

// Create a personal access token
$token = $user->createToken('test-token');

echo "Bearer Token:\n";
echo $token->plainTextToken."\n\n";

echo "You can now test the API with this token!\n";
echo "Endpoint: POST http://localhost:8000/api/v1/vendor-registration/payment/initiate\n";
echo "Header: Authorization: Bearer {$token->plainTextToken}\n";
echo "Body: {\"callback_url\": \"http://localhost:8000/api/v1/vendor-onboarding-payment/callback\"}\n";
