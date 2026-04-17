# Audit Log — Design Spec

**Date:** 2026-04-17
**Branch:** `feat/audit-log`
**Status:** Approved, pending implementation plan

## 1. Goal

Every state-changing action taken on the dashboard — by any user, on any tracked model — leaves a permanent, tamper-resistant record answering the questions: *who, what, when, on what, from where, and what changed*. Super admins read the log; no one (including super admins) can edit or delete entries.

## 2. Non-Goals

- Logging every page view or list filter (approach A — state changes only).
- Storing images, videos, or binary attachments — text JSON only.
- Cryptographic signing / blockchain-style hashing — defense-in-depth via DB grants is strong enough for v1.
- Multi-tenant scoping — single-tenant app.
- Real-time SIEM push / webhook export.
- CSV/PDF export — deferred.
- Self-service undo from the audit UI.

## 3. Scope — Existing vs. New

**Already in the codebase:**

- 11 model observers in `app/Observers/` (OrderObserver, ProductObserver, VendorApplicationObserver, etc.) registered via `Model::observe()` in `AppServiceProvider::boot`.
- `EnsureUserManagementAccess` middleware (session-gated, currently on user-management routes).
- PostgreSQL (pgvector:pg16) via Docker.
- MUI-based Inertia admin pages with paginated tables (`resources/js/pages/users/index.tsx`, `orders/index.tsx`) — style reference.
- Reference implementation in `C:\dev\clockin` (`drizzle/schema/audit.ts`, `app/(dashboard)/audit-log/page.tsx`) — UI conventions to mirror.

**New in this work:**

- `spatie/laravel-activitylog` v4 (composer dep; requires approval — already given).
- `activity_log` table (Spatie's default schema, extended — see §4).
- `Auditable` trait wrapping Spatie's `LogsActivity` with retention tagging + redaction.
- `AuditService` for manual domain/auth events.
- `ActivityLog` model override enforcing append-only at the PHP layer.
- `AuditLogController` (index + show) — super-admin only.
- `AuditLogMiddleware` — stamps IP + user agent into a request-scoped context.
- Inertia page `resources/js/pages/audit-log/index.tsx` mirroring clockin's layout.
- Sidebar entry for super admin only.
- `PruneAuditLogs` artisan command + daily scheduler entry.
- Separate DB role (`laravel_audit_pruner`) + setup SQL at `docs/setup/audit-log-db-setup.sql`.

**Deliberately untouched:**

- Existing observers keep their current bodies. `Auditable` adds logging *alongside* their domain behavior rather than replacing anything.
- No retroactive backfill of historical actions.

## 4. Architecture

### 4.1 Data model

Extending Spatie's default `activity_log` table (published via `php artisan vendor:publish --tag=activitylog-migrations`).

| column | type | notes |
| --- | --- | --- |
| id | bigint PK | |
| log_name | string, nullable | always `"default"` |
| description | string | human sentence (`"updated"`, `"login"`, `"vendor application approved"`) |
| subject_type | string, nullable | polymorphic target (e.g. `App\Models\User`) |
| subject_id | bigint, nullable | |
| event | string, nullable | `created`/`updated`/`deleted`/`login`/`logout`/`login_failed`/`password_reset`/`approved`/`rejected`/`paid`/`fulfilled`/`role_changed`/`settings.updated` |
| causer_type | string, nullable | polymorphic actor (usually `App\Models\User`) |
| causer_id | bigint, nullable | null for system actions (scheduler, seeders, queue workers without context) |
| properties | jsonb | `{old: {...}, attributes: {...}, ip, user_agent, retention_class, extra?: {...}}` |
| batch_uuid | uuid, nullable | groups one user action across multiple subjects |
| created_at | timestamp, nullable | |
| updated_at | timestamp, nullable | present for Spatie compat; writes are blocked |

**Indexes (added via a follow-up migration to Spatie's default):**

- `(causer_type, causer_id, created_at)` — "what did this user do?"
- `(subject_type, subject_id, created_at)` — "what happened to this record?"
- `(event, created_at)` — "all deletes this week?"
- `(created_at)` — timeline scroll.

**Retention tag location:** inside `properties->>'retention_class'` (`'critical'` | `'standard'`). Avoids an extra column; keeps Spatie's migration unchanged.

### 4.2 Units

**Logging units** (where events originate):

- `Auditable` trait (`app/Traits/Auditable.php`) — applied to each logged model. Wraps Spatie's `LogsActivity` and adds per-model `retentionClass()`. Uses Spatie's `logExcept()` for redaction.
- `AuditService` (`app/Services/AuditService.php`) — `record(string $event, ?Model $subject, ?User $causer, array $properties = [], string $retention = 'standard'): Activity`. Single entry point for manual events. Automatically pulls IP + user agent from the request-scoped context set by `AuditLogMiddleware`.
- `AuditLogMiddleware` (`app/Http/Middleware/AuditLogMiddleware.php`) — on every authenticated request, stashes `request()->ip()` and `request()->userAgent()` in `App\Support\AuditContext` (a tiny singleton). Observer and service read from it.
- Fortify auth listeners (`app/Listeners/LogLoginAttempt.php`, `LogLogin.php`, `LogLogout.php`, `LogPasswordReset.php`) — registered on Laravel's `Login`, `Failed`, `Logout`, `PasswordReset` events.

**Enforcement units** (append-only):

- `ActivityLog` model (`app/Models/ActivityLog.php`) — extends `Spatie\Activitylog\Models\Activity`. Uses `PreventModification` trait.
- `PreventModification` trait (`app/Traits/PreventModification.php`) — overrides `save()` (throws if `$this->exists`) and `delete()` (always throws).
- Migration `revoke_write_on_activity_log.php` — raw SQL that revokes `UPDATE, DELETE` from the app role.

**Read units** (UI):

- `AuditLogController` (`app/Http/Controllers/AuditLogController.php`) — `index` (filtered paginated list) and `show` (single entry detail) only. Routes registered with `role:super_admin` middleware.
- Inertia page `resources/js/pages/audit-log/index.tsx` — MUI table, 4 filters (Entity, Action, User, Date range), paginated, expandable rows showing old→new diff (mirror of clockin).

**Maintenance unit** (retention):

- `PruneAuditLogs` artisan command (`app/Console/Commands/PruneAuditLogs.php`) — runs daily via `routes/console.php`. Uses the `audit_pruner` DB connection to delete `retention_class = 'standard'` rows older than 90 days. No-ops if `AUDIT_PRUNER_DB_USERNAME` env is missing (fail-safe).

### 4.3 Data flow

```
Model update → Spatie observer (via Auditable trait)
  → reads IP/UA from AuditContext singleton
  → inserts activity_log row (causer = auth()->user(), properties contains old + attributes + retention_class)

Fortify Login event → LogLogin listener
  → AuditService::record('login', $user, $user, [...], retention: 'standard')
  → inserts activity_log row

Admin approves vendor → VendorApplication::approve() method
  → calls AuditService::record('approved', $application, $admin, [...], retention: 'critical')
  → inserts activity_log row

Nightly scheduler
  → runs PruneAuditLogs
  → DB::connection('audit_pruner')->table('activity_log')
     ->whereJsonContains('properties->retention_class', 'standard')
     ->where('created_at', '<', now()->subDays(90))
     ->delete()

Super admin opens /dashboard/audit-log
  → AuditLogController@index (auth + role:super_admin)
  → renders Inertia page with filtered paginated list
  → clicking a row expands to show old→new diff from properties
```

## 5. Append-Only Enforcement (defense in depth)

**Layer 1 — No UI/routes.** `AuditLogController` has only `index` and `show`. No `DELETE` / `PUT` routes registered.

**Layer 2 — Model override.** `PreventModification` trait on `ActivityLog`:

```php
public function save(array $options = []): bool
{
    if ($this->exists) {
        throw new \RuntimeException('Audit log entries are append-only.');
    }
    return parent::save($options);
}

public function delete(): bool
{
    throw new \RuntimeException('Audit log entries cannot be deleted.');
}
```

**Layer 3 — DB grants.** Migration runs:

```sql
REVOKE UPDATE, DELETE ON activity_log FROM laraveluser;
GRANT INSERT, SELECT ON activity_log TO laraveluser;
```

After this migration, the app DB user cannot alter or delete audit rows — even raw SQL via `DB::statement()` is rejected by Postgres.

**Layer 4 — Separate prune role.** The daily prune uses a dedicated Postgres role with `DELETE` on exactly `activity_log`. Credentials live in a separate Laravel connection (`audit_pruner`). If env vars for that connection are missing (e.g. on a staging server where no one bothered to create the role), the prune command no-ops and logs a warning — preferring to skip a prune over using the wrong role.

**One-time DB setup** (`docs/setup/audit-log-db-setup.sql`):

```sql
-- Run as DB superuser, once per environment.

-- 1. Create the pruner role
CREATE ROLE laravel_audit_pruner WITH LOGIN PASSWORD '<fill-in>';

-- 2. Pruner gets DELETE on exactly the audit table
GRANT CONNECT ON DATABASE surprise_moi TO laravel_audit_pruner;
GRANT USAGE ON SCHEMA public TO laravel_audit_pruner;
GRANT SELECT, DELETE ON activity_log TO laravel_audit_pruner;

-- 3. App role loses UPDATE/DELETE on activity_log
REVOKE UPDATE, DELETE ON activity_log FROM laraveluser;
```

Environment variables required:

```
AUDIT_PRUNER_DB_USERNAME=laravel_audit_pruner
AUDIT_PRUNER_DB_PASSWORD=<generated>
```

## 6. Scope — What Gets Logged

### 6.1 Model events (via `Auditable` trait)

Models with the `Auditable` trait, by retention class:

**Critical retention (kept forever):**

- `User`
- `VendorApplication`, `FieldAgentApplication`
- `PayoutRequest`, `TreasuryTransfer`
- `ReferralCode`, `Referral`, `ReferralMilestoneReward`
- `Setting`

**Standard retention (pruned after 90 days):**

- `Product`, `Category`, `Interest`, `PersonalityTrait`, `MusicGenre`
- `Advertisement`, `Coupon`
- `BespokeService`
- `Order` — create NOT logged (noisy), update/delete logged (critical on delete, standard on update)

**Per-model adjustments** (set via trait methods):

- `Order::createdAt` creates are NOT logged — too much volume; orders are a direct product of customer checkout, not a human action worth auditing.
- `Setting::created` and `Setting::deleted` are NOT logged — only `updated`, because config rows are seeded once and only change via admin edits.
- `ReferralMilestoneReward::deleted` is NOT logged — soft-delete not used; a hard delete means something is genuinely wrong, which will surface via cron alerts.

**Deliberately NOT audited** (high volume, zero investigative value):

- `Message`, `AiMessage`, `AiConversation`
- `Cart`, `CartItem`
- `ProductShare`, `WawVideoLike`, `WawVideoView`
- `Notification`, `DatabaseNotification`
- `Session`, any `*_embedding` table

### 6.2 Auth events (via Fortify listeners)

| event | trigger | retention |
| --- | --- | --- |
| `login` | `Illuminate\Auth\Events\Login` | standard |
| `logout` | `Illuminate\Auth\Events\Logout` | standard |
| `login_failed` | `Illuminate\Auth\Events\Failed` | standard |
| `password_reset` | `Illuminate\Auth\Events\PasswordReset` | critical |

Properties include `email_attempted`, `ip`, `user_agent`.

### 6.3 Domain events (manual via `AuditService::record`)

- `vendor_application.approved` / `.rejected` (critical) — added inside `VendorApplicationController::approve` / `::reject`.
- `field_agent_application.approved` / `.rejected` (critical) — inside `Admin\FieldAgentApplicationController`.
- `payout_request.approved` / `.rejected` / `.paid` (critical) — inside the relevant payout controller actions.
- `referral_milestone.fulfilled` (critical) — inside `ReferralMilestoneRewardController::fulfill`.
- `user.role_changed` (critical) — inside `UserController::update` when `role` changes.
- `settings.updated` (critical) — inside `VendorOnboardingController::update` and any other settings controllers.

These are explicit calls because their *meaning* is richer than "the row was updated" — the description column should read like a human action log, not a diff.

### 6.4 Sensitive-field redaction

The following fields are always stored as `"[redacted]"` in `properties`, regardless of whether they changed:

- `password`, `remember_token`
- `two_factor_secret`, `two_factor_recovery_codes`
- `paystack_recipient_code`, any `*_secret`, `*_token`, `*_key` fields on Setting rows
- `otp`, `otp_expires_at` on transient auth rows

Implemented via Spatie's `logExcept` trait config in the `Auditable` trait default + per-model overrides for anything extra.

## 7. UI — `/dashboard/audit-log`

**Access:** super_admin only. Sidebar item (icon: `ShieldCheck`) visible only to super_admin; route guarded by `role:super_admin` middleware.

**Page layout** (mirrors `C:\dev\clockin\app\(dashboard)\audit-log\page.tsx`):

```
Header
  Title: "Audit Log"
  Subtitle: "Every create, update, delete, and key action across the dashboard."

Filters card (flex row, wrap)
  - Entity select (dropdown, options built from the list of audited models)
  - Action select (All / Created / Updated / Deleted / Login / Logout / Approved / Rejected / Paid / …)
  - User filter (text input; matches causer `id` exactly OR does a `LIKE` on `users.name`/`users.email`)
  - Date range (From / To pickers, defaults to last 7 days)

Table (MUI)
  columns: [chevron, Timestamp, User, Action, Entity, Summary]
  row click → expands to show CHANGES DETAIL panel:
    Entity ID · IP · User Agent
    Per-field old → new rendering (red chip → green chip; monospace JSON for complex values)
  empty state: "No audit logs found." (when filters match nothing)

Pagination
  server-side, 10 / 20 / 50 / 100 rows per page, default 20.
```

**Action chip colors** (consistent with clockin + your existing status badge style):

- `created` / `approved` / `paid` / `fulfilled` → green (success)
- `updated` / `login` / `logout` / `password_reset` → blue (info)
- `deleted` / `rejected` / `login_failed` / `role_changed` → red (error)

**URL shape:**

```
/dashboard/audit-log?entity=App\Models\User&event=deleted&user_id=12&from=2026-04-01&to=2026-04-17&page=2
```

All filters participate in paginated URL state (`->withQueryString()`).

**No "export" or "download" button in v1.** Easy to add later; leaving it out keeps the first cut tight.

## 8. Testing

PHPUnit, feature tests by default, factories for model setup, `RefreshDatabase`.

**Unit tests** (`tests/Unit/`):

1. `ActivityLogTest.php`
   - `save()` on an existing row throws.
   - `delete()` throws.
   - `save()` on a new row succeeds (inserts).

2. `AuditServiceTest.php`
   - `record('login', $user, $user)` persists a row with correct causer and retention.
   - Missing causer → row saved with `causer_id = null`.
   - IP / user agent pulled from `AuditContext`.

**Feature tests** (`tests/Feature/AuditLog/`):

3. `ModelAuditTest.php` — for each trait-using model:
   - Create / update / delete triggers an `activity_log` row with expected `event`, `subject_type`, `subject_id`, and populated `old` / `attributes` in `properties`.
   - Redacted fields never appear in `properties` (explicit check for `password`, `two_factor_secret`).
   - Retention class stamped per model's declared value.

4. `AuthAuditTest.php`
   - Successful login creates a `login` event with the user as causer.
   - Failed login creates a `login_failed` event with `causer_id = null` and the attempted email in `properties.extra.email_attempted`.
   - Logout creates a `logout` event.
   - Password reset creates a `password_reset` event with critical retention.

5. `DomainEventAuditTest.php`
   - Approving a vendor application logs `vendor_application.approved` with the admin as causer and the application as subject.
   - Payout paid event logs correctly.
   - User role change logs `role_changed` with `old_role` / `new_role` in properties.

6. `AuditLogViewerTest.php`
   - Non-super-admin (customer, vendor, admin, field_agent) requesting `/dashboard/audit-log` is redirected away (not 200).
   - Super admin GET returns 200.
   - Entity filter narrows results.
   - Event filter narrows results.
   - User filter narrows results.
   - Date range filter narrows results.
   - Pagination works (creates 25 rows, 2nd page returns 5).
   - Detail expansion returns the diff JSON intact.
   - No `PUT` / `DELETE` route to the audit log exists (assert 405 / 404 on manual POST/DELETE attempts).

7. `PruneAuditLogsTest.php`
   - Running the command with no `AUDIT_PRUNER_DB_USERNAME` env logs a warning and deletes nothing.
   - With env set (in-test stub), rows `retention_class='standard'` older than 90 days are deleted.
   - Rows `retention_class='critical'` are never deleted, even at 10 years old.
   - Rows `retention_class='standard'` younger than 90 days survive.

**Not tested at the DB-grants layer** — that's a Postgres privilege, verified by the setup SQL itself. If someone misconfigures the role, the application tests would still pass; that's a deployment smoke-test concern, not a unit-test concern. `docs/setup/audit-log-db-setup.sql` includes a verification query at the bottom.

## 9. Observability

- Every audit-write failure (Spatie observer exception, listener exception, DB write rejected) is logged to Laravel's default channel at `error` level with enough context to diagnose (model class, event, subject id).
- Audit writes are NOT wrapped in a try/catch that swallows errors silently — if the audit layer is broken we want to know.
- `PruneAuditLogs` reports row count pruned to the scheduler log + via `$this->command->info()`.

## 10. Risks & Open Questions

- **Existing observer interactions.** The `Auditable` trait adds Spatie's observer alongside whatever observer is already registered on a model (e.g. `OrderObserver`). Both run. If the existing observer throws, it could prevent the audit row from being written (and vice versa). Mitigation: Spatie's observer runs in an event hook that's independent of pre-existing `boot` logic; if a conflict surfaces in testing we'll pull the trait out of that one model and log manually via `AuditService`.
- **High-volume update paths.** Paystack webhooks update `Payment` and `Order` rows rapidly. `Order` update logging is marked 'standard' and pruned at 90 days. `Payment` is not in the audited list — add it only if we discover a forensic need later.
- **Queue workers without auth context.** Jobs that update auditable models from the queue have no `auth()->user()`; `causer_id` is null. The UI labels these as "System". Acceptable for v1.
- **Pruner-role deployment coordination.** Production deploy needs a DBA to run the setup SQL once. If deploy happens before the role exists, the `REVOKE` migration still runs (the app user isn't needed for that — Laravel runs migrations as the app user, who has DDL privileges on its own table). However, the pruner connection will fail until the role is created. Scheduler warnings will fire until then; no data loss or blockage.
