<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class BackfillProductSlugs extends Command
{
    /**
     * @var string
     */
    protected $signature = 'products:backfill-slugs';

    /**
     * @var string
     */
    protected $description = 'Generate unique slugs for products that have a null slug';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $products = Product::whereNull('slug')->get();

        if ($products->isEmpty()) {
            $this->info('All products already have slugs.');

            return self::SUCCESS;
        }

        $this->info("Backfilling slugs for {$products->count()} product(s)...");

        foreach ($products as $product) {
            $product->slug = Product::generateUniqueSlug();
            $product->save();
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
