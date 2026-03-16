<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill orders that have NULL user_id using the payments table.
     *
     * Payments always have the correct user_id, so we can join against them
     * to recover the customer association for orphaned orders.
     */
    public function up(): void
    {
        // Backfill user_id from payments for orders with NULL user_id
        DB::statement('
            UPDATE orders
            SET user_id = payments.user_id
            FROM payments
            WHERE orders.id = payments.order_id
              AND orders.user_id IS NULL
              AND payments.user_id IS NOT NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not reversible — we don't know which orders had NULL user_id
    }
};
