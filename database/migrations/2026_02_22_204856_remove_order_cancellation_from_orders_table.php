<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update any orders with 'cancelled' status to 'refunded' before removing the status
        DB::statement("UPDATE orders SET status = 'refunded' WHERE status = 'cancelled'");

        if (DB::getDriverName() === 'pgsql') {
            // Drop the old constraint and add new one without 'cancelled' status
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK ((status)::text = ANY ((ARRAY['pending'::character varying, 'confirmed'::character varying, 'processing'::character varying, 'fulfilled'::character varying, 'shipped'::character varying, 'delivered'::character varying, 'refunded'::character varying])::text[]))");
        }

        // Remove cancellation-related columns
        Schema::table('orders', function ($table) {
            $table->dropColumn(['cancellation_reason', 'cancelled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back cancellation-related columns
        Schema::table('orders', function ($table) {
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            // Restore the constraint with 'cancelled' status
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK ((status)::text = ANY ((ARRAY['pending'::character varying, 'confirmed'::character varying, 'processing'::character varying, 'fulfilled'::character varying, 'shipped'::character varying, 'delivered'::character varying, 'cancelled'::character varying, 'refunded'::character varying])::text[]))");
        }
    }
};
