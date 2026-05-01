# Vendor Application Flagging — Design

Date: 2026-05-01

## 1. Objective

Give admins a third option, alongside *approve* and *reject*, when reviewing a vendor application: **flag** the application with a written reason. Flagging puts the application into a time-boxed grace period during which the vendor can edit and resubmit. Admins retain manual control — there is no auto-rejection — but reminders go out by SMS (in addition to existing channels) so flagged applications do not rot.

Today, the only signal admins can give a vendor whose Ghana card is missing or whose proof-of-business is unreadable is a hard rejection. That conflates "we said no" with "we need more from you." Flagging splits these two states.

## 2. Out of scope

- Automatic rejection when the grace period ends. Admins act manually.
- Per-application grace overrides. v1 uses a platform-wide setting.
- A structured "missing field" picklist on the flag form. v1 uses a free-form reason.
- An "extend grace period" admin action. Possible follow-up.
- Backfilling any existing rejected applications into the new `flagged` state.
- Vendor-side UI for *contesting* a flag. The vendor either edits and resubmits or does nothing.

## 3. Status machine

`flagged` is added as a peer of `approved`/`rejected`, reachable from `pending` or `under_review`:

```
pending ──┐
          ├──→ approved
under_review ──┤
              ├──→ flagged ──→ approved
              │                ──→ rejected
              │                ──→ (vendor resubmits) → under_review
              └──→ rejected
```

Constants in `App\Models\VendorApplication`:

```php
public const STATUS_FLAGGED = 'flagged';
```

`getStatuses()` adds `STATUS_FLAGGED`. The Inertia status filter on the admin index picks this up automatically because it consumes that array.

## 4. Data model

### 4.1 New columns on `vendor_applications`

| Column | Type | Purpose |
|---|---|---|
| `flagged_at` | `timestamp NULL` | When the flag was applied |
| `flag_reason` | `text NULL` | Free-form reason from the admin |
| `flagged_by` | `foreignId NULL → users.id` | Reviewer who flagged (mirrors `reviewed_by`) |
| `grace_period_ends_at` | `timestamp NULL` | Deadline shown to vendor and used by the scheduled job |
| `flag_reminder_sent_at` | `timestamp NULL` | Idempotency stamp for the pre-deadline reminder SMS |
| `flag_expired_alert_sent_at` | `timestamp NULL` | Idempotency stamp for the post-deadline admin alert SMS |

The `flag_reason` column is intentionally separate from `rejection_reason`. If a flagged application is later rejected, both reasons should persist so the audit trail records *what we asked for* alongside *why we ultimately closed it*. Reusing `rejection_reason` would erase the cure history at rejection.

`flagged_by` is indexed by the foreign key constraint. No additional index is added — admin queries filter on `status` (already indexed) and the row count of flagged applications at any time is small (bounded by reviewer throughput).

Migration name: `2026_05_01_*_add_flagging_to_vendor_applications_table`.

### 4.2 Model changes

`App\Models\VendorApplication`:

- Add `STATUS_FLAGGED = 'flagged'` constant.
- Add the six columns to `$fillable`.
- Cast `flagged_at`, `grace_period_ends_at`, `flag_reminder_sent_at`, `flag_expired_alert_sent_at` to `datetime` in `casts()`.
- Add relationship: `flagger(): BelongsTo` returning `$this->belongsTo(User::class, 'flagged_by')`.
- Add scope: `scopeFlagged($query) => $query->where('status', self::STATUS_FLAGGED)`.
- Add `STATUS_FLAGGED` to the `getStatuses()` array.
- Extend `isEditable()` to return `true` when `status === STATUS_FLAGGED`. Vendors can edit and resubmit during the grace period.
- Extend `submit()` so a vendor resubmitting a flagged application transitions back to `STATUS_UNDER_REVIEW` (not `STATUS_PENDING`). This keeps the existing `VendorApprovalSubmitted` event firing on the same code path — no separate "resubmit" entry point.
- Add `flag(int $reviewerId, string $reason): bool` (see §5.1).

### 4.3 Settings

Two new keys in the `settings` table, fetched via `Setting::get(...)`:

| Key | Default |
|---|---|
| `vendor_application_grace_period_days` | `7` |
| `vendor_application_flag_reminder_days_before` | `2` |

Both have safe defaults so the system works the moment the migration runs, with or without the setting rows.

## 5. Domain logic

### 5.1 `VendorApplication::flag()`

Mirrors `reject()` in shape:

```php
public function flag(int $reviewerId, string $reason): bool
{
    $graceDays = (int) Setting::get('vendor_application_grace_period_days', 7);

    $this->update([
        'status' => self::STATUS_FLAGGED,
        'flag_reason' => $reason,
        'flagged_by' => $reviewerId,
        'flagged_at' => now(),
        'grace_period_ends_at' => now()->addDays($graceDays),
        'flag_reminder_sent_at' => null,
        'flag_expired_alert_sent_at' => null,
    ]);

    event(new VendorFlagged($this));
    $this->user->notify(new VendorFlaggedNotification($this));

    return true;
}
```

The reminder/alert stamps are explicitly nulled. If the same application is flagged a second time later (e.g., admin flags → vendor resubmits → admin flags again), reminders fire again on the new deadline.

### 5.2 Approve/reject from `flagged`

`VendorApplicationController::approve` and `::reject` currently guard on `status ∈ {pending, under_review}`. Both gates extend to include `STATUS_FLAGGED`. The model methods (`approve()`, `reject()`) themselves have no such guard, so they don't need changes.

When a flagged application is rejected, `flag_reason` is preserved (it lives in its own column). `rejection_reason` is set as it is today.

### 5.3 Vendor resubmit

`canSubmit()` already requires `status === STATUS_PENDING`. We extend it to also accept `STATUS_FLAGGED`:

```php
public function canSubmit(): bool
{
    return $this->completed_step >= 4
        && $this->isStep3Complete()
        && in_array($this->status, [self::STATUS_PENDING, self::STATUS_FLAGGED], true)
        && (! $this->payment_required || $this->payment_completed);
}
```

The `is_null($this->submitted_at)` clause is removed for flagged apps because the row already has a `submitted_at`. To keep the original submit path strict (only allow submitting once for *new* applications), the existing `is_null($this->submitted_at)` becomes `($this->status === self::STATUS_FLAGGED || is_null($this->submitted_at))`.

`submit()` then preserves today's behavior for first-time submissions and only flips status when the application is being *resubmitted* from flagged:

```php
public function submit(): bool
{
    if (! $this->canSubmit()) {
        return false;
    }

    $payload = ['submitted_at' => now()];
    if ($this->status === self::STATUS_FLAGGED) {
        $payload['status'] = self::STATUS_UNDER_REVIEW;
    }

    $this->update($payload);

    event(new VendorApprovalSubmitted($this));
    $this->user->notify(new VendorApplicationSubmittedNotification($this));

    return true;
}
```

This minimises blast radius: a brand-new `pending` application still goes `pending → submitted_at=now()` exactly as today, and only the new resubmit-from-flagged path adds the status flip to `under_review`.

## 6. Controller and route

### 6.1 Form Request

`App\Http\Requests\FlagVendorApplicationRequest`:

```php
public function rules(): array
{
    return [
        'flag_reason' => 'required|string|min:10|max:1000',
    ];
}
```

The reject action currently uses inline validation in the controller. This spec adds a Form Request for the new flag action to match the project convention ("Form Request classes for validation rather than inline validation"). The reject path is left untouched — converting it is out of scope for this change.

### 6.2 Controller action

`VendorApplicationController::flag(FlagVendorApplicationRequest $request, VendorApplication $vendorApplication)`:

- Authorize via the same gate the existing approve/reject actions use.
- Refuse if `status ∉ {pending, under_review}` — return back with an error flash.
- Refuse if `! $vendorApplication->canBeReviewed()` — same condition the approve action checks today (covers unpaid onboarding fees).
- Call `$vendorApplication->flag(Auth::id(), $request->input('flag_reason'))`.
- Write an audit log entry with action key `vendor_application.flagged` and `extra: ['reason' => …, 'grace_period_ends_at' => …]`.
- Redirect back with a `success` flash.

### 6.3 Route

`routes/web.php`, next to the existing approve/reject routes:

```php
Route::post('/vendor-applications/{vendorApplication}/flag', [VendorApplicationController::class, 'flag'])
    ->name('vendor-applications.flag');
```

## 7. Notifications

Three new notification classes, all in `App\Notifications`. Each uses the `HasSmsChannel` trait pattern established by `FieldAgentApprovedNotification` and friends.

### 7.1 `VendorFlaggedNotification`

Fired immediately by `flag()`. Recipient: the vendor.

Channels: `database`, `mail`, `BroadcastChannel`, `FcmChannel` (when device token present), `SmsChannel` (when phone present).

| Channel | Content |
|---|---|
| Mail | Subject: "Action Required: Update Your Vendor Application". Body: greet vendor, state reason, give deadline, CTA "Open the App", support contact line copied from `VendorApprovalNotification`. |
| Database / Reverb | `type: vendor_flagged`, `title: "Action Required on Your Vendor Application"`, `message: "We need more details. Please respond by {deadline}."`, `action_url: /dashboard/vendor-applications/{id}`, `subject.status: 'flagged'`. |
| FCM | Title and body match the database payload. `data.action_url` and `data.type` for client routing. |
| SMS | `Surprise moi: Your vendor application needs more details. Reason: {reason truncated to ~90 chars}. Please update by {date}.` Total stays under 160 characters in normal use. |

### 7.2 `VendorFlagReminderNotification`

Fired once by the scheduled command, ~`vendor_application_flag_reminder_days_before` days before the deadline. Recipient: the vendor.

Same channel set as 7.1. Distinct notification class so the bell-icon / inbox shows the chronology cleanly (flagged → reminder → resolution).

| Channel | Content |
|---|---|
| Mail | Subject: "Reminder: Your Vendor Application Is Due Soon". |
| Database / Reverb | `type: vendor_flag_reminder`, includes deadline. |
| FCM | Same. |
| SMS | `Surprise moi: Reminder — your vendor application is due {date}. Please respond to avoid rejection.` |

### 7.3 `VendorFlagExpiredNotification`

Fired once per application, the first time the scheduled command runs after the deadline has passed. Recipients: every user with role `admin` or `superadmin`.

Channels: `database`, `mail`, `BroadcastChannel`, `SmsChannel`. (No FCM — admin notifications follow the existing convention of skipping push for admin staff.)

| Channel | Content |
|---|---|
| Mail | Subject: "Vendor Application Grace Period Expired". Body: vendor name, deadline that passed, reason that was originally given, link to the show page. |
| Database / Reverb | `type: vendor_flag_expired`, `action_url: /vendor-applications/{id}`. |
| SMS | `Surprise moi: Vendor application #{id} grace period expired. Please review.` |

## 8. Scheduled command

`App\Console\Commands\ProcessVendorApplicationFlagDeadlines` registered as `vendor-applications:process-flag-deadlines` and scheduled daily in `routes/console.php`:

```php
Schedule::command('vendor-applications:process-flag-deadlines')->daily();
```

Daily cadence is sufficient for a 7-day grace period. If we later shorten the grace window, this can move to `everyFourHours()` without code changes elsewhere.

The command runs two queries:

### 8.1 Pre-deadline reminder pass

```php
$reminderDays = (int) Setting::get('vendor_application_flag_reminder_days_before', 2);

VendorApplication::query()
    ->flagged()
    ->whereNull('flag_reminder_sent_at')
    ->where('grace_period_ends_at', '>', now())
    ->where('grace_period_ends_at', '<=', now()->addDays($reminderDays))
    ->with('user')
    ->chunkById(50, function ($apps) {
        foreach ($apps as $app) {
            $app->user->notify(new VendorFlagReminderNotification($app));
            $app->update(['flag_reminder_sent_at' => now()]);
        }
    });
```

### 8.2 Post-deadline admin alert pass

```php
VendorApplication::query()
    ->flagged()
    ->whereNull('flag_expired_alert_sent_at')
    ->where('grace_period_ends_at', '<', now())
    ->chunkById(50, function ($apps) {
        $admins = User::admins()->get();

        foreach ($apps as $app) {
            Notification::send($admins, new VendorFlagExpiredNotification($app));
            $app->update(['flag_expired_alert_sent_at' => now()]);
        }
    });
```

`User::admins()` is a new scope: `where('role', 'admin')->orWhere('role', 'superadmin')`. If a similar scope already exists under another name, that one is reused instead.

Idempotency stamps live on the row itself rather than on a "command last run" timestamp. SMS is metered, so a row-level stamp guarantees once-per-application delivery even if the job retries or runs twice in the same window.

## 9. Frontend changes (Inertia + React)

### 9.1 Admin show page — `resources/js/pages/vendor-applications/show.tsx`

When `application.status ∈ {pending, under_review}`:

- Existing Approve and Reject buttons stay.
- Add a new **Flag for missing details** button (orange/warning palette) that opens a modal.
- Modal contains a `<textarea>` for `flag_reason` (label: "Tell the vendor what's missing or unclear"), a small caption underneath: "The vendor will have **{grace_period_days} days** to respond." The day count is passed from the controller as a shared prop on the show page so the UI never hard-codes it.
- Submit posts to `vendor-applications.flag` via `useForm` (matching the reject modal pattern that already exists on this page).

When `application.status === 'flagged'`:

- The buttons section shows: a yellow banner with the flag reason, the deadline (formatted with `dayjs` or whatever the project uses), and three buttons: **Approve**, **Reject**, **Re-flag** (re-flag opens the same modal so admins can update the reason or restart the clock).
- A small read-only timeline below the banner: "Flagged on {flagged_at} by {flagger.name} · Reminder sent {flag_reminder_sent_at or '—'} · Deadline {grace_period_ends_at}".

### 9.2 Admin index page — `resources/js/pages/vendor-applications/index.tsx`

- Status filter dropdown automatically gains `flagged` as an option (it iterates over `getStatuses()`, which now includes the new constant).
- Each row in the flagged status renders a **Deadline {date}** chip; if the deadline has passed, the chip becomes red and reads **Deadline passed**. This is informational only — it doesn't change behavior.

### 9.3 Vendor-facing flow

When the vendor's application is `flagged`:

- A banner on the relevant Inertia page (the existing vendor-application step pages) shows: "Your application needs attention" + the admin's reason + the deadline.
- The form is editable (because `isEditable()` returns `true` for flagged).
- The "Submit for Review" button posts to the existing submit endpoint, which `submit()` now handles for flagged status.
- After resubmit, the vendor sees the existing "Application submitted" success state.

## 10. Audit log

Three new action keys (existing `Auditable` trait + explicit controller-level `AuditLog::log(...)` calls):

| Action key | When | `extra` payload |
|---|---|---|
| `vendor_application.flagged` | Admin action | `{ reason, grace_period_ends_at }` |
| `vendor_application.flag_reminder_sent` | Reminder SMS fires | `{ days_before_deadline }` |
| `vendor_application.flag_expired_alert_sent` | Admin alert SMS fires | `{ deadline, admins_notified_count }` |

The two scheduled-command audit entries are written from inside the command after each notification batch, before stamping the idempotency column.

## 11. Events

`App\Events\VendorFlagged` — broadcasts on the same admin channel that `VendorApprovalSubmitted` uses (so the admin dashboard updates live when a new application becomes flagged through any path, including a colleague flagging it). Payload mirrors `VendorApprovalSubmitted`: the application id, vendor name/email, and timestamp.

The two scheduled events (reminder sent, expired-alert sent) do not need broadcast events. The notifications themselves carry the user-facing signal; broadcast on top of that would be noise.

## 12. Tests

PHPUnit feature tests, matching project convention. Files under `tests/Feature/`.

### 12.1 `VendorApplicationFlagTest`

- `admin can flag a pending application with reason` — asserts status becomes `flagged`, all six columns set correctly, `VendorFlagged` event fired, `VendorFlaggedNotification` queued for the vendor.
- `admin can flag an under-review application`.
- `admin cannot flag an already-approved application` — redirects back with an error flash.
- `admin cannot flag an already-rejected application`.
- `admin cannot flag an application that needs payment` — `canBeReviewed()` is false.
- `flag_reason is required` (form request validation).
- `flag_reason must be at least 10 characters`.
- `flag_reason must be at most 1000 characters`.
- `flagged application can be approved by admin` — both `flag_reason` and the user role transition persist.
- `flagged application can be rejected by admin` — asserts both `flag_reason` and `rejection_reason` are present in the row.
- `flagged application is editable by the vendor` — `isEditable()` returns true.
- `vendor resubmitting a flagged application transitions it to under_review` — asserts status flip and `VendorApprovalSubmitted` event.
- `non-admin cannot flag an application` (403).
- `flagging a previously flagged application clears reminder/alert stamps` — covers the re-flag path.

### 12.2 `ProcessVendorApplicationFlagDeadlinesCommandTest`

- `sends reminder when within reminder window and stamps flag_reminder_sent_at`.
- `does not send reminder twice (idempotency)`.
- `does not send reminder if deadline already passed`.
- `does not send reminder if deadline is further away than reminder window`.
- `sends expired-alert to all admins and superadmins after deadline`.
- `does not send expired-alert twice (idempotency)`.
- `dispatches notifications on the notifications queue` (matches existing convention).
- `skips reminder for an application whose status was changed to approved/rejected/under_review before the job ran`.

### 12.3 `VendorFlaggedNotificationTest`

- `routes to SMS only when phone present` — uses the `HasSmsChannel::shouldSend` path.
- `routes to FCM only when device token exists`.
- `database, mail, and broadcast channels always fire`.
- `mail body contains the flag reason and deadline`.
- `SMS body stays under 160 characters for typical reason length`.

Tests use `Notification::fake()` and `Event::fake()` in the same style as existing vendor tests. Factories are reused; a `flagged()` factory state is added to `VendorApplicationFactory` to set `status`, `flagged_at`, `flag_reason`, `flagged_by`, and `grace_period_ends_at` together.

## 13. Edge cases

- **Vendor edits but doesn't resubmit before deadline.** Status stays `flagged`. Expired-alert SMS still fires when the deadline passes. Admin sees the partial edits when reviewing and decides — correct given manual rejection was chosen over auto-rejection.
- **Admin flags an application with unpaid onboarding fees.** `canBeReviewed()` returns false; controller refuses. No vendor without a payable application reaches the flag path.
- **Vendor resubmits between reminder being sent and deadline.** Status flips to `under_review`. The expired-alert query filters on `status = flagged`, so it won't fire spuriously. Idempotency stamps stay set.
- **Vendor's phone is missing.** `HasSmsChannel::shouldSend` returns false; SMS skipped silently; other channels still fire.
- **Multiple admins / superadmins exist.** Each gets the expired-alert via `Notification::send($admins, …)` inside a chunked loop.
- **Settings rows missing.** Defaults of 7 (grace days) and 2 (reminder days before) carry the system.
- **Race on the scheduled command.** Idempotency stamps make double-runs safe; cron + queue retries can't double-fire.
- **Re-flagging.** A flagged application can be flagged again via the show-page "Re-flag" button. `flag()` re-runs and clears the two stamp columns, restarting the reminder/alert clocks against the new deadline.
- **Admin role string drift.** If the project's admin role names ever change, `User::admins()` is the single place to update.

## 14. Operational notes

- Migration is purely additive (new columns, all nullable, no defaults that require a backfill). Safe to run live with no downtime.
- No new queues or workers — uses the existing `notifications` queue.
- No new third-party services — SMS goes through `KairosAfrikaSmsService` already in production.
- No PII added beyond what `vendor_applications` already holds.
