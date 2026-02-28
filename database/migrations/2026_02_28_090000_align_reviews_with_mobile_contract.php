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
        Schema::table('reviews', function (Blueprint $table): void {
            if (! Schema::hasColumn('reviews', 'item_type')) {
                $table->string('item_type', 20)->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('reviews', 'item_id')) {
                $table->unsignedBigInteger('item_id')->nullable()->after('item_type');
            }

            if (! Schema::hasColumn('reviews', 'helpful_count')) {
                $table->unsignedInteger('helpful_count')->default(0)->after('is_verified_purchase');
            }

            if (! Schema::hasColumn('reviews', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        DB::table('reviews')
            ->orderBy('id')
            ->chunkById(200, function ($reviews): void {
                foreach ($reviews as $review) {
                    $itemType = match ($review->reviewable_type) {
                        'product', \App\Models\Product::class => 'product',
                        'service', \App\Models\Service::class => 'service',
                        default => null,
                    };

                    DB::table('reviews')
                        ->where('id', $review->id)
                        ->update([
                            'item_type' => $itemType,
                            'item_id' => $review->reviewable_id,
                        ]);
                }
            });

        DB::table('reviews')
            ->select('review_id', DB::raw('COUNT(*) as count'))
            ->from('review_helpfuls')
            ->groupBy('review_id')
            ->orderBy('review_id')
            ->chunk(200, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('reviews')
                        ->where('id', $row->review_id)
                        ->update([
                            'helpful_count' => (int) $row->count,
                        ]);
                }
            });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->index(['item_type', 'item_id'], 'reviews_item_type_item_id_idx');
            $table->index('created_at', 'reviews_created_at_idx');
        });

        Schema::create('review_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->string('storage_path');
            $table->unsignedInteger('sort_order')->nullable();
            $table->timestamps();
        });

        DB::table('reviews')
            ->whereNotNull('images')
            ->orderBy('id')
            ->chunkById(100, function ($reviews): void {
                foreach ($reviews as $review) {
                    $decoded = json_decode($review->images ?? '[]', true);
                    if (! is_array($decoded)) {
                        continue;
                    }

                    foreach (array_values($decoded) as $index => $path) {
                        if (! is_string($path) || trim($path) === '') {
                            continue;
                        }

                        DB::table('review_images')->insert([
                            'review_id' => $review->id,
                            'storage_path' => $path,
                            'sort_order' => $index + 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });

        Schema::table('review_replies', function (Blueprint $table): void {
            if (! Schema::hasColumn('review_replies', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('review_replies', function (Blueprint $table): void {
            if (Schema::hasColumn('review_replies', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::dropIfExists('review_images');

        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropIndex('reviews_item_type_item_id_idx');
            $table->dropIndex('reviews_created_at_idx');

            if (Schema::hasColumn('reviews', 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            if (Schema::hasColumn('reviews', 'helpful_count')) {
                $table->dropColumn('helpful_count');
            }

            if (Schema::hasColumn('reviews', 'item_type')) {
                $table->dropColumn('item_type');
            }

            if (Schema::hasColumn('reviews', 'item_id')) {
                $table->dropColumn('item_id');
            }
        });
    }
};
