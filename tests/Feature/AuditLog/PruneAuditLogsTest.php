<?php

namespace Tests\Feature\AuditLog;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PruneAuditLogsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        // Reuse the main connection as the "pruner" for tests — we're not testing
        // DB grants here, only the age + retention filter logic.
        config(['database.connections.audit_pruner' => config('database.connections.pgsql')]);
        config(['database.connections.audit_pruner.username' => config('database.connections.pgsql.username')]);

        $this->user = User::factory()->create();
        DB::statement('TRUNCATE TABLE activity_log RESTART IDENTITY CASCADE');
    }

    /**
     * Insert a row directly via the query builder — bypasses Eloquent timestamps
     * and observers, so we can seed rows with arbitrary `created_at`. INSERT is
     * not blocked by our trigger, only UPDATE/DELETE are.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function insertRow(string $retentionClass, Carbon $createdAt, array $overrides = []): int
    {
        return DB::table('activity_log')->insertGetId(array_merge([
            'log_name' => 'default',
            'description' => 'test',
            'event' => 'test',
            'subject_type' => User::class,
            'subject_id' => $this->user->id,
            'causer_type' => User::class,
            'causer_id' => $this->user->id,
            'properties' => json_encode(['retention_class' => $retentionClass]),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ], $overrides));
    }

    public function test_prune_deletes_old_standard_rows(): void
    {
        $this->insertRow('standard', Carbon::now()->subDays(120));

        $this->artisan('audit:prune')->assertSuccessful();

        $this->assertSame(0, ActivityLog::count());
    }

    public function test_prune_keeps_critical_rows_even_when_old(): void
    {
        $this->insertRow('critical', Carbon::now()->subDays(3650));

        $this->artisan('audit:prune')->assertSuccessful();

        $this->assertSame(1, ActivityLog::count());
    }

    public function test_prune_keeps_recent_standard_rows(): void
    {
        $this->insertRow('standard', Carbon::now()->subDays(30));

        $this->artisan('audit:prune')->assertSuccessful();

        $this->assertSame(1, ActivityLog::count());
    }

    public function test_prune_no_ops_when_env_not_set(): void
    {
        config(['database.connections.audit_pruner.username' => null]);
        $this->insertRow('standard', Carbon::now()->subDays(120));

        $this->artisan('audit:prune')->assertSuccessful();

        $this->assertSame(1, ActivityLog::count());
    }
}
