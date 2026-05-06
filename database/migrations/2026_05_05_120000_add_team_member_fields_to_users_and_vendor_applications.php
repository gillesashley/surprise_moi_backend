<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('parent_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('must_change_password')->default(false);
            $table->string('location', 255)->nullable();
            $table->index('parent_user_id', 'users_parent_user_id_index');
        });

        Schema::table('vendor_applications', function (Blueprint $table) {
            $table->foreignId('onboarded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->index('onboarded_by_user_id', 'vendor_applications_onboarded_by_user_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_applications', function (Blueprint $table) {
            $table->dropForeign(['onboarded_by_user_id']);
            $table->dropIndex('vendor_applications_onboarded_by_user_id_index');
            $table->dropColumn('onboarded_by_user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['parent_user_id']);
            $table->dropIndex('users_parent_user_id_index');
            $table->dropColumn(['parent_user_id', 'is_active', 'must_change_password', 'location']);
        });
    }
};
