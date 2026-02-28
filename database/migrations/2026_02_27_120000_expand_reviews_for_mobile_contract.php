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
        Schema::table('reviews', function (Blueprint $table) {
            $table->foreignId('order_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->nullOnDelete();

            $table->string('context_key')
                ->nullable()
                ->after('is_verified_purchase');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->decimal('rating', 2, 1)->change();
            $table->text('comment')->nullable()->change();
        });

        DB::table('reviews')->orderBy('id')->chunkById(200, function ($reviews): void {
            foreach ($reviews as $review) {
                DB::table('reviews')
                    ->where('id', $review->id)
                    ->update([
                        'context_key' => sprintf(
                            'legacy:%d',
                            $review->id
                        ),
                    ]);
            }
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->unique('context_key');
            $table->index(['reviewable_type', 'reviewable_id', 'created_at'], 'reviews_item_created_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('reviews_item_created_at_idx');
            $table->dropUnique(['context_key']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->integer('rating')->change();
            $table->text('comment')->nullable(false)->change();
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn('context_key');
        });
    }
};
