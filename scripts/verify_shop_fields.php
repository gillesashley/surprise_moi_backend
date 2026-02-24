<?php

/**
 * This script verifies that the shops table has all required fields
 * Run: php scripts/verify_shop_fields.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "🔍 Verifying Shops Table Structure...\n\n";

try {
    // Check if shops table exists
    if (! Schema::hasTable('shops')) {
        echo "❌ ERROR: shops table does not exist!\n";
        echo "   Run: php artisan migrate:fresh --seed\n\n";
        exit(1);
    }

    echo "✅ Shops table exists\n\n";

    // Get all columns
    $columns = Schema::getColumnListing('shops');

    echo "📋 Current columns in shops table:\n";
    foreach ($columns as $column) {
        echo "   • $column\n";
    }
    echo "\n";

    // Check required fields
    $requiredFields = [
        'id',
        'vendor_id',
        'category_id',
        'name',
        'owner_name',
        'slug',
        'description',
        'logo',
        'is_active',
        'location',
        'phone',
        'email',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    $missingFields = [];
    foreach ($requiredFields as $field) {
        if (! in_array($field, $columns)) {
            $missingFields[] = $field;
        }
    }

    if (empty($missingFields)) {
        echo "✅ All required fields are present!\n\n";
    } else {
        echo "❌ Missing fields:\n";
        foreach ($missingFields as $field) {
            echo "   • $field\n";
        }
        echo "\n";
        echo "   Run: php artisan migrate:fresh --seed\n\n";
        exit(1);
    }

    // Test a sample shop record
    $shop = DB::table('shops')->first();

    if ($shop) {
        echo "📊 Sample shop record:\n";
        echo "   ID: {$shop->id}\n";
        echo "   Name: {$shop->name}\n";
        echo '   Owner Name: '.($shop->owner_name ?? 'NULL')."\n";
        echo '   Category ID: '.($shop->category_id ?? 'NULL')."\n";
        echo '   Location: '.($shop->location ?? 'NULL')."\n";
        echo '   Phone: '.($shop->phone ?? 'NULL')."\n";
        echo '   Email: '.($shop->email ?? 'NULL')."\n\n";
    } else {
        echo "ℹ️  No shops in database yet\n\n";
    }

    echo "✅ Database structure is correct!\n";
    echo "   You can now create shops with owner_name and logo fields.\n\n";
} catch (\Exception $e) {
    echo '❌ ERROR: '.$e->getMessage()."\n\n";
    exit(1);
}
