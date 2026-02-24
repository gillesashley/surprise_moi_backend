<?php

/**
 * Investigation Script: Step 4 Vendor Registration Issue
 * This script checks why step 4 (Bespoke Services) isn't being saved properly
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== INVESTIGATING STEP 4 REGISTRATION ISSUE ===\n\n";

// 1. Check all vendor applications showing 3/4 completion
echo "1. Checking vendor applications stuck at step 3/4:\n";
echo str_repeat('-', 80)."\n";

$stuckApplications = \App\Models\VendorApplication::with(['user', 'bespokeServices'])
    ->where('completed_step', 3)
    ->orderBy('created_at', 'desc')
    ->get();

if ($stuckApplications->isEmpty()) {
    echo "✓ No applications stuck at step 3\n";
} else {
    echo "Found {$stuckApplications->count()} application(s) stuck at step 3:\n\n";

    foreach ($stuckApplications as $app) {
        echo sprintf(
            "ID: %d | User: %s (%s)\n",
            $app->id,
            $app->user->name ?? 'Unknown',
            $app->user->email ?? 'N/A'
        );
        echo sprintf(
            "  - Completed Step: %d/%d\n",
            $app->completed_step,
            4
        );
        echo sprintf(
            "  - Current Step: %d\n",
            $app->current_step
        );
        echo sprintf(
            "  - Status: %s\n",
            $app->status
        );
        echo sprintf(
            "  - Services Count: %d\n",
            $app->bespokeServices->count()
        );
        echo sprintf(
            "  - Has Services? %s\n",
            $app->bespokeServices->count() > 0 ? 'YES' : 'NO'
        );
        echo sprintf(
            "  - Submitted: %s\n",
            $app->submitted_at ? $app->submitted_at->format('Y-m-d H:i:s') : 'Not submitted'
        );
        echo sprintf(
            "  - Created: %s\n",
            $app->created_at->format('Y-m-d H:i:s')
        );
        echo sprintf(
            "  - Updated: %s\n",
            $app->updated_at->format('Y-m-d H:i:s')
        );

        if ($app->bespokeServices->count() > 0) {
            echo '  - Services: '.implode(', ', $app->bespokeServices->pluck('name')->toArray())."\n";
        }

        echo "\n";
    }
}

echo "\n";

// 2. Check the pivot table for orphaned entries
echo "2. Checking vendor_application_services pivot table:\n";
echo str_repeat('-', 80)."\n";

$pivotEntries = \DB::table('vendor_application_services')
    ->join('vendor_applications', 'vendor_applications.id', '=', 'vendor_application_services.vendor_application_id')
    ->select(
        'vendor_application_services.*',
        'vendor_applications.completed_step',
        'vendor_applications.current_step'
    )
    ->get();

echo "Total pivot entries: {$pivotEntries->count()}\n";

$step3Apps = $pivotEntries->where('completed_step', 3);
if ($step3Apps->count() > 0) {
    echo "⚠ WARNING: Found {$step3Apps->count()} service assignment(s) for applications stuck at step 3!\n";
    echo "This means services ARE being saved but completed_step is NOT being updated.\n\n";

    foreach ($step3Apps as $entry) {
        echo sprintf(
            "  - App ID: %d | Service ID: %d | Completed: %d | Current: %d\n",
            $entry->vendor_application_id,
            $entry->bespoke_service_id,
            $entry->completed_step,
            $entry->current_step
        );
    }
} else {
    echo "✓ No service assignments for stuck applications\n";
}

echo "\n";

// 3. Check available bespoke services
echo "3. Checking available bespoke services:\n";
echo str_repeat('-', 80)."\n";

$services = \App\Models\BespokeService::all();
echo "Available services: {$services->count()}\n";
foreach ($services as $service) {
    echo sprintf(
        "  - ID: %d | Name: %s | Active: %s\n",
        $service->id,
        $service->name,
        $service->is_active ? 'Yes' : 'No'
    );
}

echo "\n";

// 4. Test step 4 endpoint manually
echo "4. Simulating Step 4 API call:\n";
echo str_repeat('-', 80)."\n";

if ($stuckApplications->isNotEmpty()) {
    $testApp = $stuckApplications->first();
    echo "Testing with application ID: {$testApp->id}\n";

    try {
        // Check if application meets requirements
        echo "  - Completed step before: {$testApp->completed_step}\n";
        echo '  - Can save services? '.($testApp->completed_step >= 3 ? 'YES' : 'NO')."\n";

        if ($testApp->completed_step >= 3) {
            // Simulate what the controller does
            $serviceIds = \App\Models\BespokeService::active()->limit(2)->pluck('id')->toArray();

            if (empty($serviceIds)) {
                echo "  ⚠ WARNING: No active services found!\n";
            } else {
                echo '  - Test service IDs: '.implode(', ', $serviceIds)."\n";

                \DB::beginTransaction();

                // This is what the controller does
                $testApp->bespokeServices()->sync($serviceIds);
                $testApp->current_step = 5;
                $testApp->completed_step = max($testApp->completed_step, 4);
                $testApp->save();

                \DB::commit();

                $testApp->refresh();

                echo "  ✓ Test successful!\n";
                echo "  - Completed step after: {$testApp->completed_step}\n";
                echo "  - Current step after: {$testApp->current_step}\n";
                echo "  - Services count after: {$testApp->bespokeServices()->count()}\n";
            }
        }
    } catch (\Exception $e) {
        \DB::rollBack();
        echo "  ✗ ERROR: {$e->getMessage()}\n";
        echo "  Stack trace:\n";
        echo $e->getTraceAsString()."\n";
    }
} else {
    echo "No stuck applications to test with\n";
}

echo "\n";

// 5. Check for any database constraints or triggers
echo "5. Checking database schema:\n";
echo str_repeat('-', 80)."\n";

$columns = \DB::select('DESCRIBE vendor_applications');
echo "vendor_applications table columns:\n";
foreach ($columns as $col) {
    if (in_array($col->Field, ['current_step', 'completed_step'])) {
        echo sprintf(
            "  - %s: Type=%s, Null=%s, Default=%s\n",
            $col->Field,
            $col->Type,
            $col->Null,
            $col->Default ?? 'NULL'
        );
    }
}

echo "\n=== INVESTIGATION COMPLETE ===\n";
