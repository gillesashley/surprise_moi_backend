<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Only drop if the column exists
            if (Schema::hasColumn('orders', 'payment_method_id')) {
                $table->dropForeignIfExists(['payment_method_id']);
                $table->dropColumn('payment_method_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->onDelete('set null');
        });
    }
};
