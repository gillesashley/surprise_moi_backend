<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_addresses', function (Blueprint $table) {
            $table->string('name')->nullable()->after('user_id');
        });

        // Copy existing label values to name for backwards compatibility
        DB::table('user_addresses')
            ->whereNotNull('label')
            ->whereNull('name')
            ->update(['name' => DB::raw('label')]);

        Schema::table('user_addresses', function (Blueprint $table) {
            $table->dropColumn('address_line_2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_addresses', function (Blueprint $table) {
            $table->string('address_line_2')->nullable()->after('address_line_1');
            $table->dropColumn('name');
        });
    }
};
