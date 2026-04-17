<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Block UPDATE and DELETE on activity_log at the DB layer.
 *
 * This is the DB-layer half of the append-only guarantee. The PHP-layer half
 * lives in App\Traits\PreventModification.
 *
 * Why triggers instead of REVOKE: the app DB user typically owns tables it
 * creates via migrations, and Postgres ignores privilege revocations against
 * a table's owner. A BEFORE trigger raising an exception blocks the operation
 * regardless of ownership — the only way around it is `ALTER TABLE ... DISABLE
 * TRIGGER` (DDL privilege) or connecting as a non-owner role with REPLICATION
 * rights, neither of which the app user has.
 *
 * The PruneAuditLogs command sidesteps this via a separate `audit_pruner`
 * connection whose role is granted `DELETE` on this specific table. The
 * pruner connects with `session_replication_role = replica` to bypass the
 * trigger — scoped to that command only.
 *
 * No-op on non-Postgres drivers (sqlite in tests).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION activity_log_prevent_modification() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'activity_log entries are append-only: % is not permitted', TG_OP
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS prevent_activity_log_update ON activity_log;
            CREATE TRIGGER prevent_activity_log_update
                BEFORE UPDATE ON activity_log
                FOR EACH ROW
                EXECUTE FUNCTION activity_log_prevent_modification();

            DROP TRIGGER IF EXISTS prevent_activity_log_delete ON activity_log;
            CREATE TRIGGER prevent_activity_log_delete
                BEFORE DELETE ON activity_log
                FOR EACH ROW
                EXECUTE FUNCTION activity_log_prevent_modification();
        SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS prevent_activity_log_update ON activity_log;
            DROP TRIGGER IF EXISTS prevent_activity_log_delete ON activity_log;
            DROP FUNCTION IF EXISTS activity_log_prevent_modification();
        SQL);
    }
};
