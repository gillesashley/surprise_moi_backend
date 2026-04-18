# Customer Support Console — Design Spec

**Date:** 2026-04-17
**Branch:** `feat/customer-support` (+ `feat/customer-support-ui` for v1 finishing work)
**Status:** v1 scope-locked 2026-04-18 — **Tickets flow only**; Messaging + Birthdays deferred to v1.1. See §1.5.

## 1. Goal

Build a unified Customer Support console for admins. The page lets a customer-care representative (CSR) record every conversation and follow-up they have with customers and vendors — calls, chats, in-person visits — and send outbound SMS to those contacts without leaving the page. The same console surfaces follow-up reminders and an upcoming-birthdays list so CSRs can proactively reach out.

This is **CSR-initiated logging**, complementary to (and distinct from) the existing customer-initiated `Report` system.

## 1.5 v1 Scope Lock (amended 2026-04-18)

After starting implementation, the original spec was narrowed to ship an MVP faster. v1 ships the **Tickets** flow only. The deferred items below will be picked up as v1.1.

**v1 ships (Tickets flow):**
- `SupportTicket` CRUD via a single-page `customer-support/index` (list, filters, search, pagination) + `customer-support/show` (ticket detail with merged timeline of interactions + messages).
- Create new ticket from the index via a **dialog** (not a separate `create.tsx` page) — keeps the UI consistent with the modal pattern already used on `show.tsx` (Log Interaction / Send SMS / Close / Reassign).
- Log-interaction, send-SMS, close-with-note, reopen, reassign flows — all inline from `show.tsx`.
- SMS dispatch is **synchronous in-request** via `SmsProviderInterface::send()`. `SendSupportSmsJob` is deferred. The queue hop is not on the MVP critical path; Horizon will pick it up in v1.1.
- Single `SupportTicketController` holds all actions (index, show, store, storeInteraction, sendMessage, updateStatus, assign). Controller splits (`SupportInteractionController`, `SupportMessageController`, `SupportContactController`) are deferred.
- Phone normalization happens inside `KairosAfrikaSmsService::formatPhoneNumber()` at send-time (already implemented, transparent to callers). The `support_messages.to_phone` snapshot stores whatever the CSR typed / was on `users.phone` at send-time — audit trail is preserved even though the dialed number may be a normalized form.
- Sidebar entry: **"Customer Support" placed below "Reports & Conflicts"** in the Support group (no badge in v1).

**v1.1 deferred:**
- Three-tab index layout (Tickets / Messaging / Birthdays). v1 renders the Tickets view at `/dashboard/customer-support` with no tab chrome.
- **Messaging tab** — standalone SMS composer, outbound message log, filters. Requires `SupportMessageController` (with `page`, `sendStandalone`, `log`) + standalone-send route `POST /messaging/sms` + `GET /messaging` + `GET /messaging/log`.
- **Birthdays tab** — today + this-week lists, one-click birthday SMS. Requires `SupportContactController::birthdays`.
- **Contact search** endpoint (`GET /contacts/search`) — not needed until a true contact-picker appears in Messaging; v1 uses the existing ticket form with `user_id` or manual `contact_*` fields.
- **Queued SMS dispatch** via `SendSupportSmsJob` (with retry/backoff). Horizon integration deferred.
- **Sidebar badge** (open-tickets-assigned-to-me count).
- **Reusable component extraction** to `resources/js/components/admin/customer-support/`. v1 keeps all JSX inline in `index.tsx` / `show.tsx` — fine while only two pages exist.
- **Controller splits** (see above).

The data model (§4.1), ticket state machine, category/priority enums, SMS template config, and Report linkage all remain as specified — v1 uses a subset of the surface, not a different shape.

## 2. Non-Goals (v1)

- Inbound SMS / two-way conversations
- Bulk SMS blasts ("send to all vendors")
- Automated birthday cron (manual send only)
- File attachments on interactions
- Admin-managed UI for SMS templates (templates live in a config file)
- SMS opt-out / consent preferences
- A dedicated `customer_support` role (admin/super_admin handle CSR work for now)
- Per-ticket ownership ACLs (any admin can edit any ticket, mirroring `Reports`)

## 3. Scope — Existing vs. New

**Already in the codebase:**

- `User` model with `phone`, `date_of_birth`, `role` fields. Roles include `admin`, `super_admin`.
- `EnsureDashboardAccess` middleware (used by all admin pages).
- SMS infrastructure: `App\Contracts\Sms\SmsProviderInterface`, `App\Services\KairosAfrikaSmsService`, `App\Channels\SmsChannel`, `App\Notifications\Messages\SmsMessage`. Currently used by Field Agent application notifications.
- Queue infrastructure: `QUEUE_CONNECTION=redis`, Laravel Horizon v5, Supervisor configs in `docker/supervisor/` running `surprisemoi-queue:*`.
- `Report` model and pages — pattern to mirror for ticket index/show layout, status enums, and form-request validation style.
- `User` picker patterns — there is no shared component yet; we'll build one in this work.
- shadcn UI primitives in `resources/js/components/ui/`.

**New in this work:**

- Three tables: `support_tickets`, `support_interactions`, `support_messages`.
- Eloquent models, factories, and seeders for each.
- Controllers under `App\Http\Controllers\Admin\` for tickets, interactions, messages, contact search, and birthdays.
- Form Requests for store/update/close/interaction/SMS validation.
- One queued job: `SendSupportSmsJob`.
- Inertia pages under `resources/js/Pages/admin/customer-support/` (`index`, `create`, `show`).
- Reusable React components under `resources/js/components/admin/customer-support/`.
- Sidebar entry "Customer Support" with an open-tickets-assigned-to-me badge.
- Config file `config/support_templates.php` with four seeded SMS templates.
- PHPUnit feature tests for every controller method and the SMS job.

**Explicitly untouched:**

- `Report` flow — independent system; CSR tickets *link to* reports but do not replace them.
- Existing SMS service or notification channel — we call into it; we do not refactor it.
- Field Agent / Vendor / Rider features — no shared schema changes.

## 4. Architecture

### 4.1 Data Model

#### `support_tickets`

| column | type | notes |
| --- | --- | --- |
| id | bigint PK | |
| ticket_number | string, unique | format `CST-YYYYMMDD-XXXX`, generated on creation (lockForUpdate, mirrors `Report::generateReportNumber`) |
| subject | string(255) | brief title |
| description | text, nullable | optional opening context |
| category | string | enum, see §4.1.1 |
| priority | string | enum: `low`, `normal`, `high` |
| status | string | enum: `open`, `in_progress`, `closed`; default `open` |
| user_id | nullable FK → users (nullOnDelete) | the registered user the ticket is about |
| contact_name | string | always captured |
| contact_phone | string, nullable | normalized `+233…` if Ghanaian |
| contact_email | string, nullable | |
| order_id | nullable FK → orders (nullOnDelete) | |
| report_id | nullable FK → reports (nullOnDelete) | |
| assigned_to | FK → users (restrictOnDelete) | admin owner; defaults to creator |
| created_by | FK → users (restrictOnDelete) | |
| closure_note | string(500), nullable | required when transitioning to `closed` |
| closed_at | datetime, nullable | |
| closed_by | nullable FK → users | |
| timestamps | | |

Indexes: `(status, assigned_to)`, `(user_id)`, `(category)`, `(created_at)`.

##### 4.1.1 Category enum

Issue-type:
- `order_issue`
- `product_problem`
- `vendor_dispute`
- `payment_issue`
- `delivery_issue`
- `account_help`

Non-issue / proactive:
- `general_inquiry`
- `follow_up`
- `check_in`
- `onboarding_assistance`
- `feedback`
- `other`

Stored as string for forward-compatibility. Constants live on the `SupportTicket` model.

#### `support_interactions`

| column | type | notes |
| --- | --- | --- |
| id | bigint PK | |
| ticket_id | FK → support_tickets (cascadeOnDelete) | |
| channel | string | enum: `phone_call`, `sms`, `whatsapp`, `email`, `in_app_chat`, `in_person`, `other` |
| direction | string | enum: `inbound`, `outbound` |
| summary | text | |
| occurred_at | datetime | defaults `now()` server-side; editable by CSR |
| follow_up_at | date, nullable | must be today or future on create |
| created_by | FK → users (restrictOnDelete) | |
| timestamps | | |

Indexes: `(ticket_id, occurred_at)`, `(follow_up_at)`.

#### `support_messages`

Single source of truth for outbound SMS sent from the CSR console.

| column | type | notes |
| --- | --- | --- |
| id | bigint PK | |
| ticket_id | nullable FK → support_tickets (nullOnDelete) | null when sent standalone |
| interaction_id | nullable FK → support_interactions (nullOnDelete) | the auto-created interaction when sent from a ticket |
| to_user_id | nullable FK → users (nullOnDelete) | |
| to_phone | string | snapshot of the actually-dispatched number, normalized `+233…` |
| body | text | post-template-substitution body that was sent |
| template_key | string, nullable | `birthday`, `welcome`, `follow_up`, `custom`, or `null` |
| status | string | enum: `queued`, `sent`, `failed` |
| failed_reason | string(500), nullable | exception message on failure |
| sent_at | datetime, nullable | populated on success |
| sent_by | FK → users (restrictOnDelete) | the CSR |
| timestamps | | |

Indexes: `(sent_by, created_at)`, `(to_user_id)`, `(status)`.

#### Templates (config, not a table)

`config/support_templates.php`:

```php
return [
    'birthday' => "Hi {{name}}, happy birthday from all of us at Surprise Moi! 🎉 Wishing you a wonderful year ahead.",
    'welcome'  => "Hi {{name}}, welcome to Surprise Moi! Reach us anytime if you need help getting started.",
    'follow_up'=> "Hi {{name}}, just following up on our last conversation. Let us know how we can help.",
    'custom'   => "",
];
```

Substitution: `{{name}}` resolves to `User::name` if `to_user_id` is set, else `support_tickets.contact_name`, else `"there"`. Substitution happens server-side at send time.

### 4.2 Backend

#### Routes (`routes/web.php`, inside the existing admin-guarded group)

Mounted inside the existing `Route::middleware(['auth', 'dashboard'])->prefix('dashboard')->group(...)` block in `routes/web.php`. URL prefix `/dashboard/customer-support`, name prefix `dashboard.customer-support.`:

```
GET    /                              → SupportTicketController@index
GET    /create                        → SupportTicketController@create
POST   /                              → SupportTicketController@store
GET    /{ticket}                      → SupportTicketController@show
PATCH  /{ticket}                      → SupportTicketController@update
POST   /{ticket}/close                → SupportTicketController@close
POST   /{ticket}/reopen               → SupportTicketController@reopen

POST   /{ticket}/interactions         → SupportInteractionController@store

POST   /{ticket}/sms                  → SupportMessageController@sendForTicket
GET    /messaging                     → SupportMessageController@page
POST   /messaging/sms                 → SupportMessageController@storeStandalone
GET    /messaging/log                 → SupportMessageController@log

GET    /contacts/search               → SupportContactController@search
GET    /birthdays                     → SupportContactController@birthdays
```

Run `php artisan wayfinder:generate` after route changes so the React side gets typed action functions.

#### Controllers (`app/Http/Controllers/Admin/`)

- **`SupportTicketController`** — `index` (filters: status, priority, category, assigned-to-me, free-text search; eager-loads `user:id,name,email`, `assignee:id,name`, `latestInteraction`), `create`, `store`, `show` (eager-loads interactions, messages, user, order, report), `update`, `close`, `reopen`. `index` returns Inertia render of `admin/customer-support/index` with paginated tickets + follow-ups widget data + open-tickets-for-me count.
- **`SupportInteractionController`** — `store` (creates an interaction row only). When a CSR manually picks `channel=sms` here, treat it as a *post-hoc log entry* of an SMS that was sent outside the platform — no actual SMS is dispatched. Real SMS sends always go through `SupportMessageController` (which creates its own interaction row automatically).
- **`SupportMessageController`** — `sendForTicket`, `page` (Inertia render of messaging tab with composer + log), `storeStandalone`, `log` (paginated, filterable by recipient/body/status).
- **`SupportContactController`** — `search` (`?q=…`, returns `[{id, name, email, phone, role}]` across all roles, case-insensitive match on name/email/phone, limit 20), `birthdays` (returns users with `date_of_birth` whose month/day falls today or in the next 7 days; includes name, role, phone, age-turning).

#### Form Requests (`app/Http/Requests/Admin/`)

- `StoreSupportTicketRequest`
- `UpdateSupportTicketRequest`
- `CloseSupportTicketRequest` — requires `closure_note: required|string|max:500`
- `StoreSupportInteractionRequest` — `follow_up_at: nullable|date|after_or_equal:today`
- `SendSupportSmsRequest` — see §4.3 for fields

Use array-style rules (matches existing `ResolveReportRequest`). Provide `messages()` for user-friendly errors.

#### Jobs

`SendSupportSmsJob implements ShouldQueue` (queue: `notifications`, tries: 3, backoff: `[10, 60, 300]`):

```php
public function __construct(public int $messageId) {}

public function handle(SmsProviderInterface $sms): void
{
    $message = SupportMessage::findOrFail($this->messageId);
    try {
        $sms->send($message->to_phone, $message->body);
        $message->update(['status' => 'sent', 'sent_at' => now()]);
    } catch (\Throwable $e) {
        $message->update(['status' => 'failed', 'failed_reason' => Str::limit($e->getMessage(), 500)]);
        throw $e;
    }
}
```

The actual `SmsProviderInterface` method name should be confirmed at implementation time by reading the contract; this spec assumes a `send(string $phone, string $body)` shape.

### 4.3 SMS pipeline

```
HTTP POST /dashboard/customer-support/{ticket}/sms     (or /messaging/sms)
  ↓
SendSupportSmsRequest validates:
  - to_user_id        nullable|exists:users,id
  - to_phone          required_without:to_user_id|string  (will be re-resolved server-side)
  - template_key      nullable|in:birthday,welcome,follow_up,custom
  - body              required|string|max:480
  ↓
Controller (wrap steps 3–5 in a DB transaction so the message + interaction land atomically):
  1. Resolve recipient phone:
     - if to_user_id: load user, take user.phone (must not be null → 422)
     - else: normalize the typed to_phone via the same phone-normalization
       helper KairosAfrikaSmsService relies on (confirm exact helper at
       implementation time)
  2. If template_key set, run {{name}} substitution server-side using the
     final body (in case CSR edited the template in the UI)
  3. If from-ticket route: create support_interactions row first
     (channel=sms, direction=outbound, summary=Str::limit(body, 200),
     occurred_at=now, created_by=csr) and capture its id
  4. Persist support_messages row with status='queued', to_phone snapshot,
     interaction_id set if step 3 ran
  5. Dispatch SendSupportSmsJob($message->id) via afterCommit() so the
     job never queries a row that hasn't committed yet
  6. Return Inertia redirect with flash 'SMS queued'
  ↓
Job runs on 'notifications' queue → calls SmsProviderInterface->send(...)
  ↓
Success → support_messages.status='sent', sent_at=now()
Failure → status='failed', failed_reason=Str::limit($e->getMessage(),500); retries via Horizon backoff
```

Phone snapshot rule: at send-time, derive the phone (from current `users.phone` if `to_user_id` is set, else the typed number). Persist that exact value into `support_messages.to_phone`. If the user later changes their phone, the log remains truthful about what was actually sent.

### 4.4 Frontend

#### Pages (`resources/js/Pages/admin/customer-support/`)

- **`index.tsx`** — three-tab layout (Tickets / Messaging / Birthdays):
  - *Tickets* (default): table with filters (status, priority, category, assigned-to-me, search), columns (ticket #, subject, contact, category, priority, status, assignee, last activity), "New Ticket" CTA, right-rail "Follow-ups due / overdue" widget.
  - *Messaging*: top half is `SmsComposer`; bottom half is paginated outbound message log (search by recipient/body, status badges).
  - *Birthdays*: two sections (Today, This week), each row has `BirthdayCard` with one-click "Send birthday SMS" that opens the composer pre-filled with the birthday template and recipient.
- **`create.tsx`** — new ticket form: contact picker → subject → category → priority → assignee → optional order/report links → optional opening description.
- **`show.tsx`** — ticket detail:
  - Header row: ticket number, subject, status pill, priority pill, assignee badge, contact card (with one-click Call/SMS), related order/report chips
  - Action bar: *Add Interaction*, *Send SMS*, *Edit*, *Close* (or *Reopen* when closed)
  - Interactions timeline (newest first): channel icon, direction arrow, summary, follow-up date if set, who logged it, when
  - Side panel: closure note (if closed), audit footer (created_by, created_at)

#### Components (`resources/js/components/admin/customer-support/`)

`ContactPicker`, `InteractionForm`, `InteractionTimeline`, `SmsComposer` (with character counter + segment estimate at 160/320/480), `BirthdayCard`, `FollowUpList`, `TicketStatusBadge`, `PriorityBadge`. Build on existing shadcn primitives.

#### Sidebar

Add "Customer Support" entry under the admin group (between Reports and Users). Badge: count of `open` tickets where `assigned_to = me`. The count comes from a shared Inertia prop populated in a middleware or layout controller — implementation detail to confirm during planning by checking how the existing Reports/Users sidebar badges work (or whether they exist).

## 5. Permissions

- `EnsureDashboardAccess` middleware guards all routes.
- Inside controllers, the middleware-allowed `admin` and `super_admin` roles can access; any other role gets 403 (handled by middleware).
- No per-ticket ownership checks in v1: any admin can view, edit, close, reopen, send SMS for any ticket. This mirrors the existing `Report` flow and reflects that the CSR team is a small, trusted group.

## 6. Edge cases & decisions

- **Recipient has no phone** → `SendSupportSmsRequest` returns 422 with "This contact has no phone number on file." Composer disables the Send button when picker selects a phone-less user.
- **Phone changes after ticket created** → SMS uses *current* `users.phone`. Message log snapshots the actually-sent number.
- **SMS body length** → soft cap at 160 in composer (single segment); hard cap 480 (3 segments). Backend rejects > 480.
- **Closing a ticket with open follow-ups** → allowed. Closed-ticket interactions stop appearing in the dashboard follow-ups widget.
- **Ticket with no `user_id`** (free-form contact only) → fully usable; SMS sends to typed phone.
- **Birthday widget when user has no `date_of_birth`** → user simply doesn't appear in the list. No error.
- **Birthday widget when user has no phone** → user still appears, but the "Send birthday SMS" button is disabled with a tooltip.
- **Reopen of closed ticket** → status returns to `open`. `closed_at` and `closed_by` are nulled; `closure_note` is preserved as historical record (consider rendering it in a "Previously closed" banner).
- **Concurrent close** → first writer wins; second sees a redirect with flash "Ticket already closed."
- **Template substitution with no name available** → `{{name}}` resolves to `"there"` as a graceful fallback.

## 7. Testing

PHPUnit feature tests, one file per controller plus the job. All under `tests/Feature/CustomerSupport/`:

- **`SupportTicketControllerTest`**
  - `index` filters (status, priority, category, assigned-to-me, search)
  - `store` happy path + validation failures (missing subject/category/contact)
  - `show` returns ticket with interactions and messages eager-loaded
  - `update` happy path + cannot reassign to non-admin
  - `close` requires `closure_note`; sets `closed_at`/`closed_by`
  - `reopen` clears `closed_at`/`closed_by`, preserves `closure_note`
  - Auth boundary: `vendor`, `customer`, `field_agent`, `marketer`, `influencer` get 403
- **`SupportInteractionControllerTest`**
  - `store` happy path
  - `follow_up_at` must be today or future
  - Auth boundary
- **`SupportMessageControllerTest`**
  - `sendForTicket` creates `support_messages` + `support_interactions` rows; dispatches `SendSupportSmsJob` (`Queue::fake`)
  - `storeStandalone` creates only `support_messages`
  - Recipient with no phone → 422
  - Body > 480 → 422
  - Template `{{name}}` substitution renders correctly
  - Phone snapshot: change `users.phone` between message creation and assertion → snapshot equals the value at send-time
  - `log` pagination + filters
  - Auth boundary
- **`SupportContactControllerTest`**
  - `search` returns matches across all roles, capped at 20
  - `birthdays` returns users with birthday today + within next 7 days; users with no DOB excluded
  - Auth boundary
- **`SendSupportSmsJobTest`**
  - Happy path: provider called with normalized phone + body; status → `sent`, `sent_at` populated
  - Failure path: provider throws → status → `failed`, `failed_reason` populated, exception re-thrown so Horizon retries

Add factories: `SupportTicketFactory`, `SupportInteractionFactory`, `SupportMessageFactory`. Each with a few states (`open`, `closed`, `withInteractions`, etc.) following existing factory conventions.

Run with `php artisan test --compact --filter=CustomerSupport` after each implementation step.

## 8. Open questions / future work

- Promote `config/support_templates.php` to a DB-backed `support_templates` table with admin CRUD UI when the team asks to edit templates without a deploy.
- Inbound SMS (replies) — requires confirming Kairos Afrika webhook support and a separate routing design.
- Bulk SMS (e.g., "all vendors in Ashanti region") — needs rate limiting, opt-out handling, and scheduling. Out of v1.
- Auto-birthday cron — scheduled task that sends the birthday template automatically every morning to users with birthdays that day. Defer until the manual flow proves the template works.
- A dedicated `customer_support` role with `RestrictCustomerSupportAccess` middleware (mirroring `RestrictFieldAgentAccess`), if dedicated CSR staff are hired.
- Per-ticket ownership ACLs if the team grows large enough that "any admin can edit any ticket" causes friction.
- File attachments on interactions (e.g., screenshots of WhatsApp threads) — needs storage decisions and review against existing media patterns.
