-- Audit log DB setup.
-- Run ONCE per environment as a Postgres superuser (typically `postgres`).
-- Safe to re-run — all creates/grants are idempotent.
--
-- What this does:
--   1. Creates a `laravel_audit_pruner` role with LOGIN.
--   2. Grants it CONNECT + USAGE + SELECT + DELETE on exactly the activity_log
--      table — nothing else in the schema.
--   3. Grants it the `session_replication_role` permission needed to set
--      replication mode = replica, which is how the prune command bypasses
--      the audit_log_prevent_modification() trigger.
--
-- After running:
--   - Set AUDIT_PRUNER_DB_USERNAME=laravel_audit_pruner in .env
--   - Set AUDIT_PRUNER_DB_PASSWORD=<the password you chose below> in .env
--   - The scheduled prune (audit:prune) will start working.
--
-- Replace DATABASE_NAME with the actual Postgres database name
-- (for local: `surprise_moi_local`; for production: `surprise_moi_db`).
-- Replace PASSWORD_HERE with a secure generated secret.

-- 1. Create the pruner role (no-op if already exists).
DO $$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'laravel_audit_pruner') THEN
        CREATE ROLE laravel_audit_pruner WITH LOGIN PASSWORD 'PASSWORD_HERE';
    END IF;
END
$$;

-- 2. Minimal privileges: connect to the DB, use the public schema,
--    select + delete on the audit table only.
GRANT CONNECT ON DATABASE DATABASE_NAME TO laravel_audit_pruner;
GRANT USAGE ON SCHEMA public TO laravel_audit_pruner;
GRANT SELECT, DELETE ON activity_log TO laravel_audit_pruner;

-- 3. Allow this role to flip session_replication_role = replica so the
--    BEFORE DELETE trigger is bypassed when running the prune command.
--    This is only meaningful when combined with the pruner's limited
--    grants (step 2) — the app user doesn't have DELETE, so even if it
--    could flip replication role it still can't delete.
ALTER ROLE laravel_audit_pruner SET session_replication_role = 'replica';

-- 4. Verification query — confirms the grants.
-- Expected output: pruner has SELECT + DELETE on activity_log; no UPDATE.
SELECT grantee, privilege_type
  FROM information_schema.role_table_grants
  WHERE table_name = 'activity_log' AND grantee = 'laravel_audit_pruner'
  ORDER BY privilege_type;
