<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Propaganistas\LaravelPhone\PhoneNumber;

/**
 * One-shot data migration: rewrite every users.phone into canonical E.164.
 *
 * Background: client used to construct '+<dialCode><raw input>' without
 * stripping leading zeros, so existing rows hold a mix of formats
 * (+233247648200, +2330247648200, 0247648200, etc.). Once the validation
 * + normalization changes ship, lookups by phone (resendOtp, register
 * uniqueness, verifyOtp) will be done against the canonical E.164 form
 * — so existing rows must be migrated to match.
 *
 * Reversible: original values are written to a temporary backup column
 * `phone_legacy_backup` so this migration can be rolled back exactly.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'phone_legacy_backup')) {
            DB::statement('ALTER TABLE users ADD COLUMN phone_legacy_backup VARCHAR(20) NULL');
        }

        $unparseable = [];
        $collisions = [];

        DB::table('users')
            ->whereNotNull('phone')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$unparseable, &$collisions) {
                foreach ($rows as $row) {
                    $original = (string) $row->phone;

                    try {
                        $normalized = (new PhoneNumber($original, ['GH']))->formatE164();
                    } catch (\Throwable) {
                        $unparseable[] = ['id' => $row->id, 'phone' => $original];
                        continue;
                    }

                    if ($normalized === $original && $row->phone_legacy_backup !== null) {
                        continue;
                    }

                    // Skip rows where another user already owns the canonical
                    // form. Without this, the unique index on users.phone aborts
                    // the whole migration and leaves the table half-normalized.
                    // Operators reconcile the surfaced collisions manually.
                    $collidingId = DB::table('users')
                        ->where('phone', $normalized)
                        ->where('id', '!=', $row->id)
                        ->value('id');

                    if ($collidingId !== null) {
                        $collisions[] = [
                            'id' => $row->id,
                            'phone' => $original,
                            'normalized' => $normalized,
                            'collides_with_id' => $collidingId,
                        ];
                        continue;
                    }

                    DB::table('users')
                        ->where('id', $row->id)
                        ->update([
                            'phone' => $normalized,
                            'phone_legacy_backup' => $original,
                        ]);
                }
            });

        if (! empty($unparseable)) {
            Log::warning('Phone normalization migration: unparseable rows skipped', [
                'count' => count($unparseable),
                'ids' => array_column($unparseable, 'id'),
            ]);
        }

        if (! empty($collisions)) {
            Log::warning('Phone normalization migration: duplicate canonical numbers skipped', [
                'count' => count($collisions),
                'collisions' => $collisions,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'phone_legacy_backup')) {
            return;
        }

        DB::table('users')
            ->whereNotNull('phone_legacy_backup')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('users')
                        ->where('id', $row->id)
                        ->update([
                            'phone' => $row->phone_legacy_backup,
                            'phone_legacy_backup' => null,
                        ]);
                }
            });

        DB::statement('ALTER TABLE users DROP COLUMN phone_legacy_backup');
    }
};
