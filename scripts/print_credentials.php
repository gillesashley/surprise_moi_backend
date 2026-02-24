<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== LOGIN CREDENTIALS ===\n";
echo "Default password for all users: password\n\n";

echo "--- SUPER ADMIN ---\n";
$admin = \App\Models\User::where('role', 'super_admin')->first();
echo "Email: {$admin->email}\n\n";

echo "--- INFLUENCERS (showing 3) ---\n";
\App\Models\User::where('role', 'influencer')->take(3)->get()->each(function ($u) {
    echo "Email: {$u->email}\n";
});

echo "\n--- FIELD AGENTS (showing 3) ---\n";
\App\Models\User::where('role', 'field_agent')->take(3)->get()->each(function ($u) {
    echo "Email: {$u->email}\n";
});

echo "\n--- MARKETERS ---\n";
\App\Models\User::where('role', 'marketer')->get()->each(function ($u) {
    echo "Email: {$u->email}\n";
});
