<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('field_verified_at')->nullable()->after('id');
            $table->timestamp('field_verified_until')->nullable()->after('field_verified_at');
            $table->index('field_verified_until');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['field_verified_until']);
            $table->dropColumn(['field_verified_at', 'field_verified_until']);
        });
    }
};
