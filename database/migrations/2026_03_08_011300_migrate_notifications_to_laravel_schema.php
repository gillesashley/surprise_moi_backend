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
        // Step 1: Add notifiable_type and notifiable_id columns after id
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('notifiable_type')->after('id')->default('');
            $table->unsignedBigInteger('notifiable_id')->after('notifiable_type')->default(0);
        });

        // Step 2: Populate notifiable columns from user_id
        DB::table('notifications')->update([
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => DB::raw('"user_id"'),
        ]);

        // Step 3: Merge title and message into the data JSON column
        DB::table('notifications')->orderBy('id')->chunk(500, function ($notifications) {
            foreach ($notifications as $notification) {
                $existingData = json_decode($notification->data, true) ?? [];
                $existingData['title'] = $notification->title;
                $existingData['message'] = $notification->message;

                DB::table('notifications')
                    ->where('id', $notification->id)
                    ->update(['data' => json_encode($existingData)]);
            }
        });

        // Step 4: Drop foreign key, old indexes, and old columns
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign('notifications_user_id_foreign');
            $table->dropIndex('notifications_user_id_read_at_index');
            $table->dropIndex('notifications_user_id_created_at_index');
            $table->dropColumn(['user_id', 'title', 'message']);
        });

        // Step 5: Remove defaults from notifiable columns, make data non-nullable, add composite index
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('notifiable_type')->default(null)->change();
            $table->unsignedBigInteger('notifiable_id')->default(null)->change();
            $table->json('data')->nullable(false)->change();
            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Drop the new composite index
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['notifiable_type', 'notifiable_id']);
        });

        // Step 2: Re-add the old columns
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('title')->after('type')->default('');
            $table->text('message')->after('title')->default('');
            $table->unsignedBigInteger('user_id')->after('data')->default(0);
        });

        // Step 3: Restore user_id from notifiable_id, and title/message from data JSON
        DB::table('notifications')->update([
            'user_id' => DB::raw('"notifiable_id"'),
        ]);

        DB::table('notifications')->orderBy('id')->chunk(500, function ($notifications) {
            foreach ($notifications as $notification) {
                $data = json_decode($notification->data, true) ?? [];
                $title = $data['title'] ?? '';
                $message = $data['message'] ?? '';

                unset($data['title'], $data['message']);

                DB::table('notifications')
                    ->where('id', $notification->id)
                    ->update([
                        'title' => $title,
                        'message' => $message,
                        'data' => empty($data) ? null : json_encode($data),
                    ]);
            }
        });

        // Step 4: Remove defaults, restore foreign key and old indexes, make data nullable again
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('title')->default(null)->change();
            $table->text('message')->default(null)->change();
            $table->unsignedBigInteger('user_id')->default(null)->change();
            $table->json('data')->nullable()->change();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
        });

        // Step 5: Drop the notifiable columns
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn(['notifiable_type', 'notifiable_id']);
        });
    }
};
