<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MigrateStorageToR2Command extends Command
{
    protected $signature = 'storage:migrate-to-r2
        {--dry-run : Show what would be migrated without actually doing it}
        {--fix-paths : Strip storage/ prefix from database records}';

    protected $description = 'Migrate files from local public disk to Cloudflare R2 and fix database paths';

    private int $uploaded = 0;

    private int $skipped = 0;

    private int $failed = 0;

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $fixPaths = $this->option('fix-paths');

        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE — no files will be transferred');
        }

        if ($fixPaths) {
            $this->fixDatabasePaths($isDryRun);
        }

        $this->migrateFiles($isDryRun);

        $this->newLine();
        $this->info('📊 Migration Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Uploaded', $this->uploaded],
                ['Skipped (already exists)', $this->skipped],
                ['Failed', $this->failed],
            ]
        );

        if ($this->failed > 0) {
            $this->error('⚠️  Some files failed to migrate. Check the output above.');

            return self::FAILURE;
        }

        $this->info('✅ Migration complete!');

        return self::SUCCESS;
    }

    /**
     * Migrate all files from local public disk to R2.
     */
    private function migrateFiles(bool $isDryRun): void
    {
        $localDisk = Storage::disk('public');
        $r2Disk = Storage::disk('r2');

        $files = $localDisk->allFiles();
        $total = count($files);

        $this->info("📁 Found {$total} files on local public disk");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($files as $file) {
            try {
                // Skip .gitignore and hidden files
                if (str_starts_with(basename($file), '.')) {
                    $bar->advance();

                    continue;
                }

                // Check if already exists on R2
                if ($r2Disk->exists($file)) {
                    $this->skipped++;
                    $bar->advance();

                    continue;
                }

                if ($isDryRun) {
                    $this->uploaded++;
                    $bar->advance();

                    continue;
                }

                // Copy file to R2
                $contents = $localDisk->get($file);
                $r2Disk->put($file, $contents, 'public');

                // Verify upload
                if ($r2Disk->exists($file)) {
                    $this->uploaded++;
                } else {
                    $this->failed++;
                    $this->newLine();
                    $this->error("  ✗ Failed to verify: {$file}");
                }
            } catch (\Exception $e) {
                $this->failed++;
                $this->newLine();
                $this->error("  ✗ Error uploading {$file}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Strip 'storage/' prefix from database file path columns.
     *
     * Some controllers historically saved paths as 'storage/avatars/file.jpg'
     * instead of 'avatars/file.jpg'. This normalizes them.
     */
    private function fixDatabasePaths(bool $isDryRun): void
    {
        $this->info('🔧 Fixing database paths (stripping storage/ prefix)...');

        $tables = [
            ['table' => 'users', 'columns' => ['avatar']],
            ['table' => 'products', 'columns' => ['thumbnail']],
            ['table' => 'product_images', 'columns' => ['image_path']],
            ['table' => 'shops', 'columns' => ['logo']],
            ['table' => 'services', 'columns' => ['thumbnail']],
        ];

        foreach ($tables as $config) {
            $table = $config['table'];
            foreach ($config['columns'] as $column) {
                $count = DB::table($table)
                    ->where($column, 'LIKE', 'storage/%')
                    ->count();

                if ($count > 0) {
                    $this->line("  {$table}.{$column}: {$count} records to fix");

                    if (! $isDryRun) {
                        DB::table($table)
                            ->where($column, 'LIKE', 'storage/%')
                            ->update([
                                $column => DB::raw("REPLACE({$column}, 'storage/', '')"),
                            ]);
                        $this->info("    ✓ Fixed {$count} records");
                    }
                } else {
                    $this->line("  {$table}.{$column}: no records need fixing");
                }
            }
        }

        $this->newLine();
    }
}
