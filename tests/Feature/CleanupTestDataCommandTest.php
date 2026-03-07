<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CleanupTestDataCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Hard-delete users while bypassing PostgreSQL foreign key cascades.
     *
     * Without this, deleting a user would cascade-delete their orders (user_id FK)
     * or set vendor_id to null (vendor_id FK), preventing orphan detection testing.
     *
     * @param  array<int>  $userIds
     */
    private function hardDeleteUsersWithoutCascade(array $userIds): void
    {
        DB::statement('SET session_replication_role = \'replica\'');
        DB::table('users')->whereIn('id', $userIds)->delete();
        DB::statement('SET session_replication_role = \'origin\'');
    }

    public function test_dry_run_reports_orphaned_orders(): void
    {
        // Create a real user + order (should be preserved)
        $realCustomer = User::factory()->create(['role' => 'customer']);
        $realVendor = User::factory()->create(['role' => 'vendor']);
        Order::factory()->create([
            'user_id' => $realCustomer->id,
            'vendor_id' => $realVendor->id,
        ]);

        // Create an orphaned order (user hard-deleted)
        $dummyCustomer = User::factory()->create(['role' => 'customer']);
        $dummyVendor = User::factory()->create(['role' => 'vendor']);
        Order::factory()->create([
            'user_id' => $dummyCustomer->id,
            'vendor_id' => $dummyVendor->id,
        ]);

        // Hard-delete the dummy users, bypassing FK cascades
        $this->hardDeleteUsersWithoutCascade([$dummyCustomer->id, $dummyVendor->id]);

        $this->artisan('app:cleanup-test-data')
            ->expectsTable(
                ['Table', 'Orphaned Records'],
                [
                    ['coupon_usages', 0],
                    ['order_items', 0],
                    ['payments', 0],
                    ['vendor_transactions', 0],
                    ['earnings', 0],
                    ['payout_requests', 0],
                    ['vendor_balances', 0],
                    ['orders', 1],
                ]
            )
            ->assertSuccessful();

        // Verify no records were deleted (dry run)
        $this->assertDatabaseCount('orders', 2);
    }

    public function test_execute_deletes_orphaned_orders_preserves_real(): void
    {
        // Create a real order
        $realCustomer = User::factory()->create(['role' => 'customer']);
        $realVendor = User::factory()->create(['role' => 'vendor']);
        $realOrder = Order::factory()->create([
            'user_id' => $realCustomer->id,
            'vendor_id' => $realVendor->id,
        ]);

        // Create an orphaned order
        $dummyCustomer = User::factory()->create(['role' => 'customer']);
        $dummyVendor = User::factory()->create(['role' => 'vendor']);
        $orphanedOrder = Order::factory()->create([
            'user_id' => $dummyCustomer->id,
            'vendor_id' => $dummyVendor->id,
        ]);

        $this->hardDeleteUsersWithoutCascade([$dummyCustomer->id, $dummyVendor->id]);

        $this->artisan('app:cleanup-test-data', ['--execute' => true])
            ->expectsConfirmation('This will permanently delete the records above. Continue?', 'yes')
            ->assertSuccessful();

        // Orphaned order hard-deleted (not in DB at all, even with trashed)
        $this->assertDatabaseMissing('orders', ['id' => $orphanedOrder->id]);
        // Real order preserved
        $this->assertDatabaseHas('orders', ['id' => $realOrder->id]);
    }

    public function test_execute_aborts_on_no_confirmation(): void
    {
        $dummyCustomer = User::factory()->create(['role' => 'customer']);
        $dummyVendor = User::factory()->create(['role' => 'vendor']);
        Order::factory()->create([
            'user_id' => $dummyCustomer->id,
            'vendor_id' => $dummyVendor->id,
        ]);

        $this->hardDeleteUsersWithoutCascade([$dummyCustomer->id, $dummyVendor->id]);

        $this->artisan('app:cleanup-test-data', ['--execute' => true])
            ->expectsConfirmation('This will permanently delete the records above. Continue?', 'no')
            ->assertSuccessful();

        // Nothing deleted
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_no_orphans_reports_clean_database(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $vendor = User::factory()->create(['role' => 'vendor']);
        Order::factory()->create([
            'user_id' => $customer->id,
            'vendor_id' => $vendor->id,
        ]);

        $this->artisan('app:cleanup-test-data')
            ->expectsOutputToContain('No orphaned records found')
            ->assertSuccessful();
    }

    public function test_execute_cascades_to_related_tables(): void
    {
        $dummyCustomer = User::factory()->create(['role' => 'customer']);
        $dummyVendor = User::factory()->create(['role' => 'vendor']);
        $order = Order::factory()->create([
            'user_id' => $dummyCustomer->id,
            'vendor_id' => $dummyVendor->id,
        ]);

        // Create related records
        DB::table('order_items')->insert([
            'order_id' => $order->id,
            'orderable_type' => 'App\Models\Product',
            'orderable_id' => 1,
            'quantity' => 1,
            'unit_price' => 10.00,
            'subtotal' => 10.00,
            'currency' => 'GHS',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('payments')->insert([
            'order_id' => $order->id,
            'user_id' => $dummyCustomer->id,
            'reference' => 'PAY-TEST123',
            'amount' => 10.00,
            'amount_in_kobo' => 1000,
            'currency' => 'GHS',
            'status' => 'success',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Hard-delete users bypassing FK cascades
        $this->hardDeleteUsersWithoutCascade([$dummyCustomer->id, $dummyVendor->id]);

        $this->artisan('app:cleanup-test-data', ['--execute' => true])
            ->expectsConfirmation('This will permanently delete the records above. Continue?', 'yes')
            ->assertSuccessful();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('payments', 0);
    }
}
