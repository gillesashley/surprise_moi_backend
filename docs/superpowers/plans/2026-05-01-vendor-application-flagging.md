# Vendor Application Flagging Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `flagged` status alongside `approved`/`rejected` so admins can put incomplete vendor applications into a time-boxed grace period with a written reason, sending pre-deadline reminders to vendors and post-deadline alerts to admins via SMS plus the existing notification stack.

**Architecture:** A new status constant on `VendorApplication`, six additive nullable columns on the `vendor_applications` table, one new model method (`flag()`), one new event, three new notification classes, one form request, one new controller action, one scheduled console command, and small Inertia/React additions to the admin show/index pages. Mobile vendor experience flows through the existing API status endpoint, which already exposes `is_editable` and `status` and only needs awareness of the new value.

**Tech Stack:** PHP 8.2 / Laravel 12 / Inertia + React 19 / PHPUnit 11 / `KairosAfrikaSmsService` (existing) / `SmsChannel` (existing) / `HasSmsChannel` trait (existing) / Laravel `Schedule` facade.

**Spec:** `docs/superpowers/specs/2026-05-01-vendor-application-flagging-design.md`

**Conventions used in every task:**
- Run all PHP commands inside the `app` Docker service (`docker compose exec app …`) per project convention. Outside docker, the equivalent local command is shown in parentheses where useful.
- Run Pint after each task: `vendor/bin/pint --dirty --format agent`.
- Test filter pattern: `php artisan test --compact --filter=<TestClass>` or `--filter=<test_method>`.
- Branch is already `feat/vendor-application-flagging` cut from `main`. The spec is committed at `6516d14`.

---

## File Structure (created or modified)

**Created**
- `database/migrations/2026_05_01_000000_add_flagging_to_vendor_applications_table.php`
- `app/Events/VendorFlagged.php`
- `app/Notifications/VendorFlaggedNotification.php`
- `app/Notifications/VendorFlagReminderNotification.php`
- `app/Notifications/VendorFlagExpiredNotification.php`
- `app/Http/Requests/FlagVendorApplicationRequest.php`
- `app/Console/Commands/ProcessVendorApplicationFlagDeadlines.php`
- `tests/Feature/VendorApplicationFlagTest.php`
- `tests/Feature/Notifications/VendorFlaggedNotificationTest.php`
- `tests/Feature/Console/ProcessVendorApplicationFlagDeadlinesTest.php`

**Modified**
- `app/Models/VendorApplication.php`
- `app/Models/User.php`
- `database/factories/VendorApplicationFactory.php`
- `app/Http/Controllers/VendorApplicationController.php`
- `app/Http/Controllers/Api/V1/VendorRegistrationController.php`
- `routes/web.php`
- `routes/console.php`
- `resources/js/pages/vendor-applications/show.tsx`
- `resources/js/pages/vendor-applications/index.tsx`

---

## Task 1: Migration — add flagging columns to `vendor_applications`

**Files:**
- Create: `database/migrations/2026_05_01_000000_add_flagging_to_vendor_applications_table.php`

- [ ] **Step 1: Generate the migration**

```bash
docker compose exec app php artisan make:migration add_flagging_to_vendor_applications_table --table=vendor_applications --no-interaction
```

Rename the generated file's prefix to `2026_05_01_000000_` so it sorts immediately after the most recent vendor_applications migration.

- [ ] **Step 2: Fill in the migration body**

Replace the file contents with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_applications', function (Blueprint $table) {
            $table->timestamp('flagged_at')->nullable()->after('reviewed_at');
            $table->text('flag_reason')->nullable()->after('flagged_at');
            $table->foreignId('flagged_by')->nullable()->after('flag_reason')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('grace_period_ends_at')->nullable()->after('flagged_by');
            $table->timestamp('flag_reminder_sent_at')->nullable()->after('grace_period_ends_at');
            $table->timestamp('flag_expired_alert_sent_at')->nullable()->after('flag_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('flagged_by');
            $table->dropColumn([
                'flagged_at',
                'flag_reason',
                'grace_period_ends_at',
                'flag_reminder_sent_at',
                'flag_expired_alert_sent_at',
            ]);
        });
    }
};
```

- [ ] **Step 3: Run the migration**

```bash
docker compose exec app php artisan migrate --no-interaction
```

Expected output: `Migrated: 2026_05_01_000000_add_flagging_to_vendor_applications_table`.

- [ ] **Step 4: Verify columns exist**

```bash
docker compose exec app php artisan tinker --execute="dump(\Schema::getColumnListing('vendor_applications'));"
```

Expected: the array contains `flagged_at`, `flag_reason`, `flagged_by`, `grace_period_ends_at`, `flag_reminder_sent_at`, `flag_expired_alert_sent_at`.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_05_01_000000_add_flagging_to_vendor_applications_table.php
git commit -m "feat(vendor-application): add flagging columns to vendor_applications"
```

---

## Task 2: Model — status constant, fillable, casts, scope, relationship, getStatuses

**Files:**
- Modify: `app/Models/VendorApplication.php`

- [ ] **Step 1: Write a failing test for the new constant and scope**

Create `tests/Feature/VendorApplicationFlagTest.php` with the first two tests:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorApplicationFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_flagged_constant_is_defined(): void
    {
        $this->assertSame('flagged', VendorApplication::STATUS_FLAGGED);
    }

    public function test_get_statuses_includes_flagged(): void
    {
        $this->assertContains(VendorApplication::STATUS_FLAGGED, VendorApplication::getStatuses());
    }

    public function test_flagged_scope_returns_only_flagged_applications(): void
    {
        $flagged = VendorApplication::factory()->create(['status' => VendorApplication::STATUS_FLAGGED]);
        VendorApplication::factory()->create(['status' => VendorApplication::STATUS_PENDING]);

        $results = VendorApplication::query()->flagged()->get();

        $this->assertCount(1, $results);
        $this->assertSame($flagged->id, $results->first()->id);
    }

    public function test_flagger_relationship_returns_user(): void
    {
        $reviewer = User::factory()->create();
        $app = VendorApplication::factory()->create(['flagged_by' => $reviewer->id]);

        $this->assertSame($reviewer->id, $app->flagger->id);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker compose exec app php artisan test --compact --filter=VendorApplicationFlagTest
```

Expected: FAIL with "Undefined constant STATUS_FLAGGED" / "Call to undefined method ::flagged()" / "Call to undefined method ::flagger()".

- [ ] **Step 3: Add the constant, fillable entries, casts, scope, relationship, and getStatuses entry**

In `app/Models/VendorApplication.php`:

After the existing `STATUS_REJECTED` constant, add:

```php
    public const STATUS_FLAGGED = 'flagged';
```

Add to the `$fillable` array (after the existing `'submitted_at',` entry, in any sensible spot):

```php
        // Flagging fields
        'flagged_at',
        'flag_reason',
        'flagged_by',
        'grace_period_ends_at',
        'flag_reminder_sent_at',
        'flag_expired_alert_sent_at',
```

Add to the `casts()` method's returned array:

```php
            'flagged_at' => 'datetime',
            'grace_period_ends_at' => 'datetime',
            'flag_reminder_sent_at' => 'datetime',
            'flag_expired_alert_sent_at' => 'datetime',
```

Add a relationship method near the existing `reviewer()` method:

```php
    public function flagger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'flagged_by');
    }
```

Add a query scope near `scopePending`:

```php
    public function scopeFlagged($query)
    {
        return $query->where('status', self::STATUS_FLAGGED);
    }
```

Update `getStatuses()` to include the new constant:

```php
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_FLAGGED,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
        ];
    }
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
docker compose exec app php artisan test --compact --filter=VendorApplicationFlagTest
```

Expected: PASS for the four tests above.

- [ ] **Step 5: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/VendorApplication.php tests/Feature/VendorApplicationFlagTest.php
git commit -m "feat(vendor-application): add flagged status, scope, and flagger relationship"
```

---

## Task 3: Factory — `flagged()` state

**Files:**
- Modify: `database/factories/VendorApplicationFactory.php`

- [ ] **Step 1: Write a failing test for the factory state**

Append to `tests/Feature/VendorApplicationFlagTest.php`:

```php
    public function test_factory_flagged_state_creates_flagged_application(): void
    {
        $app = VendorApplication::factory()->flagged()->create();

        $this->assertSame(VendorApplication::STATUS_FLAGGED, $app->status);
        $this->assertNotNull($app->flagged_at);
        $this->assertNotNull($app->flag_reason);
        $this->assertNotNull($app->flagged_by);
        $this->assertNotNull($app->grace_period_ends_at);
        $this->assertTrue($app->grace_period_ends_at->isFuture());
    }
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker compose exec app php artisan test --compact --filter=test_factory_flagged_state_creates_flagged_application
```

Expected: FAIL with "Call to undefined method ::flagged()".

- [ ] **Step 3: Add the state to the factory**

Append to `database/factories/VendorApplicationFactory.php` after the `rejected()` state:

```php
    /**
     * Flagged for missing details with an active grace period.
     */
    public function flagged(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VendorApplication::STATUS_FLAGGED,
            'submitted_at' => now()->subDays(2),
            'flagged_at' => now()->subDay(),
            'flag_reason' => $this->faker->sentence(),
            'flagged_by' => User::factory(),
            'grace_period_ends_at' => now()->addDays(6),
        ]);
    }
```

- [ ] **Step 4: Run test to verify it passes**

```bash
docker compose exec app php artisan test --compact --filter=test_factory_flagged_state_creates_flagged_application
```

Expected: PASS.

- [ ] **Step 5: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/factories/VendorApplicationFactory.php tests/Feature/VendorApplicationFlagTest.php
git commit -m "feat(vendor-application): add flagged() factory state"
```

---

## Task 4: Model — extend `isEditable()` to allow editing while flagged

**Files:**
- Modify: `app/Models/VendorApplication.php`

- [ ] **Step 1: Write failing tests**

Append to `tests/Feature/VendorApplicationFlagTest.php`:

```php
    public function test_flagged_application_is_editable(): void
    {
        $app = VendorApplication::factory()->flagged()->create();

        $this->assertTrue($app->isEditable());
    }

    public function test_pending_unsubmitted_application_is_still_editable(): void
    {
        $app = VendorApplication::factory()->create([
            'status' => VendorApplication::STATUS_PENDING,
            'submitted_at' => null,
        ]);

        $this->assertTrue($app->isEditable());
    }

    public function test_pending_submitted_application_is_not_editable(): void
    {
        $app = VendorApplication::factory()->create([
            'status' => VendorApplication::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        $this->assertFalse($app->isEditable());
    }
```

- [ ] **Step 2: Run tests to verify the first one fails**

```bash
docker compose exec app php artisan test --compact --filter=test_flagged_application_is_editable
```

Expected: FAIL — `isEditable()` returns `false` for flagged.

- [ ] **Step 3: Update `isEditable()`**

Replace the existing method body in `app/Models/VendorApplication.php` with:

```php
    public function isEditable(): bool
    {
        // Rejected and flagged applications can always be edited
        if (in_array($this->status, [self::STATUS_REJECTED, self::STATUS_FLAGGED], true)) {
            return true;
        }

        // Pending applications can be edited only if not yet submitted
        return $this->status === self::STATUS_PENDING && is_null($this->submitted_at);
    }
```

- [ ] **Step 4: Run all three tests**

```bash
docker compose exec app php artisan test --compact --filter=VendorApplicationFlagTest
```

Expected: PASS for all three editability tests plus all earlier tests.

- [ ] **Step 5: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/VendorApplication.php tests/Feature/VendorApplicationFlagTest.php
git commit -m "feat(vendor-application): allow editing while flagged"
```

---

## Task 5: Model — extend `canSubmit()` and `submit()` for resubmit-from-flagged

**Files:**
- Modify: `app/Models/VendorApplication.php`

- [ ] **Step 1: Write failing tests**

Append to `tests/Feature/VendorApplicationFlagTest.php`:

```php
    public function test_flagged_application_can_be_resubmitted(): void
    {
        \Notification::fake();
        \Event::fake();

        $user = User::factory()->create();
        $app = VendorApplication::factory()
            ->for($user)
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->readyToSubmit()
            ->withPaymentCompleted()
            ->flagged()
            ->create();

        $result = $app->submit();

        $this->assertTrue($result);
        $this->assertSame(VendorApplication::STATUS_UNDER_REVIEW, $app->fresh()->status);
        $this->assertNotNull($app->fresh()->submitted_at);
    }

    public function test_pending_first_submission_does_not_change_status(): void
    {
        \Notification::fake();
        \Event::fake();

        $user = User::factory()->create();
        $app = VendorApplication::factory()
            ->for($user)
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->readyToSubmit()
            ->withPaymentCompleted()
            ->create([
                'status' => VendorApplication::STATUS_PENDING,
                'submitted_at' => null,
            ]);

        $result = $app->submit();

        $this->assertTrue($result);
        $this->assertSame(VendorApplication::STATUS_PENDING, $app->fresh()->status);
        $this->assertNotNull($app->fresh()->submitted_at);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker compose exec app php artisan test --compact --filter=test_flagged_application_can_be_resubmitted
```

Expected: FAIL — `canSubmit()` returns `false` for flagged because `submitted_at` is already set, so `submit()` returns `false`.

- [ ] **Step 3: Update `canSubmit()` and `submit()`**

Replace `canSubmit()` body in `app/Models/VendorApplication.php`:

```php
    public function canSubmit(): bool
    {
        $statusOk = in_array($this->status, [self::STATUS_PENDING, self::STATUS_FLAGGED], true);
        $submittedAtOk = $this->status === self::STATUS_FLAGGED || is_null($this->submitted_at);

        return $this->completed_step >= 4
            && $this->isStep3Complete()
            && $statusOk
            && $submittedAtOk
            && (! $this->payment_required || $this->payment_completed);
    }
```

Replace `submit()` body:

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

        // Fire submission event to notify admins
        event(new VendorApprovalSubmitted($this));

        // Send confirmation notification to the vendor
        $this->user->notify(new VendorApplicationSubmittedNotification($this));

        return true;
    }
```

- [ ] **Step 4: Run all tests in the class**

```bash
docker compose exec app php artisan test --compact --filter=VendorApplicationFlagTest
```

Expected: PASS for the new resubmit tests, and the existing `VendorApplicationSubmittedNotificationTest::test_submit_dispatches_notification_to_vendor` test must also still pass — verify:

```bash
docker compose exec app php artisan test --compact --filter=VendorApplicationSubmittedNotificationTest
```

Expected: PASS.

- [ ] **Step 5: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/VendorApplication.php tests/Feature/VendorApplicationFlagTest.php
git commit -m "feat(vendor-application): allow resubmit from flagged status"
```

---

## Task 6: Event — `VendorFlagged`

**Files:**
- Create: `app/Events/VendorFlagged.php`

- [ ] **Step 1: Generate the event class**

```bash
docker compose exec app php artisan make:event VendorFlagged --no-interaction
```

- [ ] **Step 2: Implement the event**

Replace `app/Events/VendorFlagged.php` contents with:

```php
<?php

namespace App\Events;

use App\Models\VendorApplication;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VendorFlagged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public VendorApplication $vendorApplication) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin.vendor-applications'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'vendor.flagged';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'application_id' => $this->vendorApplication->id,
            'user_id' => $this->vendorApplication->user_id,
            'flagged_at' => $this->vendorApplication->flagged_at?->toIso8601String(),
            'grace_period_ends_at' => $this->vendorApplication->grace_period_ends_at?->toIso8601String(),
        ];
    }
}
```

The channel name `admin.vendor-applications` mirrors what `VendorApprovalSubmitted` uses — verify by reading `app/Events/VendorApprovalSubmitted.php`. If that event uses a different channel name, copy it exactly so admin frontend listeners aggregate cleanly.

- [ ] **Step 3: Verify it loads**

```bash
docker compose exec app php artisan tinker --execute="dump(class_exists(\App\Events\VendorFlagged::class));"
```

Expected: `true`.

- [ ] **Step 4: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Events/VendorFlagged.php
git commit -m "feat(vendor-application): add VendorFlagged event"
```

---

## Task 7: Notification — `VendorFlaggedNotification`

**Files:**
- Create: `app/Notifications/VendorFlaggedNotification.php`
- Create: `tests/Feature/Notifications/VendorFlaggedNotificationTest.php`

- [ ] **Step 1: Write failing tests**

Create `tests/Feature/Notifications/VendorFlaggedNotificationTest.php`:

```php
<?php

namespace Tests\Feature\Notifications;

use App\Channels\SmsChannel;
use App\Models\User;
use App\Models\VendorApplication;
use App\Notifications\VendorFlaggedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Channels\BroadcastChannel;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\Fcm\FcmChannel;
use Tests\TestCase;

class VendorFlaggedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_includes_database_mail_broadcast_and_sms_when_phone_present(): void
    {
        $user = User::factory()->create(['phone' => '+233244000000']);
        $app = VendorApplication::factory()->for($user)->flagged()->create();

        $notification = new VendorFlaggedNotification($app);
        $channels = $notification->via($user);

        $this->assertContains('database', $channels);
        $this->assertContains('mail', $channels);
        $this->assertContains(BroadcastChannel::class, $channels);
        $this->assertContains(SmsChannel::class, $channels);
    }

    public function test_via_excludes_sms_when_phone_missing(): void
    {
        $user = User::factory()->create(['phone' => null]);
        $app = VendorApplication::factory()->for($user)->flagged()->create();

        $notification = new VendorFlaggedNotification($app);
        $channels = $notification->via($user);

        $this->assertNotContains(SmsChannel::class, $channels);
    }

    public function test_via_includes_fcm_when_device_token_present(): void
    {
        $user = User::factory()->create();
        $user->deviceTokens()->create(['token' => 'fake-token', 'platform' => 'android']);
        $app = VendorApplication::factory()->for($user)->flagged()->create();

        $notification = new VendorFlaggedNotification($app);
        $channels = $notification->via($user);

        $this->assertContains(FcmChannel::class, $channels);
    }

    public function test_to_database_returns_correct_shape(): void
    {
        $user = User::factory()->create();
        $app = VendorApplication::factory()->for($user)->flagged()->create();

        $data = (new VendorFlaggedNotification($app))->toDatabase($user);

        $this->assertSame('vendor_flagged', $data['type']);
        $this->assertSame('Action Required on Your Vendor Application', $data['title']);
        $this->assertStringContainsString('more details', $data['message']);
        $this->assertSame('/dashboard/vendor-applications/'.$app->id, $data['action_url']);
        $this->assertSame('flagged', $data['subject']['status']);
    }

    public function test_to_mail_includes_flag_reason_and_deadline(): void
    {
        $user = User::factory()->create();
        $app = VendorApplication::factory()->for($user)->flagged()->create([
            'flag_reason' => 'Ghana card back image is unreadable',
            'grace_period_ends_at' => now()->addDays(7),
        ]);

        $mail = (new VendorFlaggedNotification($app))->toMail($user);

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('Action Required: Update Your Vendor Application', $mail->subject);
        $allLines = implode("\n", $mail->introLines);
        $this->assertStringContainsString('Ghana card back image is unreadable', $allLines);
    }

    public function test_to_sms_returns_short_content(): void
    {
        $user = User::factory()->create();
        $app = VendorApplication::factory()->for($user)->flagged()->create([
            'flag_reason' => 'Ghana card back image is unreadable',
        ]);

        $sms = (new VendorFlaggedNotification($app))->toSms($user);

        $this->assertNotNull($sms->getContent());
        $this->assertStringContainsString('Surprise moi', $sms->getContent());
    }

    public function test_notification_is_queued_on_notifications_queue(): void
    {
        $app = VendorApplication::factory()->flagged()->create();

        $notification = new VendorFlaggedNotification($app);

        $this->assertSame('notifications', $notification->queue);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker compose exec app php artisan test --compact --filter=VendorFlaggedNotificationTest
```

Expected: FAIL with "Class VendorFlaggedNotification not found".

- [ ] **Step 3: Implement the notification**

Create `app/Notifications/VendorFlaggedNotification.php`:

```php
<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\VendorApplication;
use App\Notifications\Concerns\HasSmsChannel;
use App\Notifications\Messages\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Channels\BroadcastChannel;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

class VendorFlaggedNotification extends Notification implements ShouldQueue
{
    use HasSmsChannel, Queueable;

    public function __construct(public VendorApplication $vendorApplication)
    {
        $this->queue = 'notifications';
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'mail', BroadcastChannel::class];

        if ($notifiable->deviceTokens()->exists()) {
            $channels[] = FcmChannel::class;
        }

        if (! empty($notifiable->phone)) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $deadline = $this->vendorApplication->grace_period_ends_at?->toFormattedDateString() ?? 'soon';

        return (new MailMessage)
            ->subject('Action Required: Update Your Vendor Application')
            ->greeting("Hello {$notifiable->name},")
            ->line('We need a few more details before we can finish reviewing your vendor application on Surprise moi.')
            ->line('**Reason:** '.$this->vendorApplication->flag_reason)
            ->line("Please update your application by {$deadline} to continue.")
            ->action('Open the App', config('deep_links.share_base_url'))
            ->line('If you have questions, please contact our support team at operations@teczaleel.com or WhatsApp us on +233 245 261 266.')
            ->salutation('Best regards, The Surprise moi Team');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $deadline = $this->vendorApplication->grace_period_ends_at?->toFormattedDateString() ?? 'soon';

        return [
            'type' => 'vendor_flagged',
            'title' => 'Action Required on Your Vendor Application',
            'message' => "We need more details on your application. Please respond by {$deadline}.",
            'action_url' => '/dashboard/vendor-applications/'.$this->vendorApplication->id,
            'actor' => null,
            'subject' => [
                'id' => $this->vendorApplication->id,
                'type' => 'vendor_application',
                'status' => 'flagged',
            ],
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $data = $this->toDatabase($notifiable);

        return new BroadcastMessage([
            'title' => $data['title'],
            'body' => $data['message'],
            'status' => 'flagged',
            'vendor_application_id' => $this->vendorApplication->id,
            'flag_reason' => $this->vendorApplication->flag_reason,
            'grace_period_ends_at' => $this->vendorApplication->grace_period_ends_at?->toIso8601String(),
            'action_url' => $data['action_url'],
        ]);
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        $data = $this->toDatabase($notifiable);

        return FcmMessage::create()
            ->notification(
                FcmNotification::create()
                    ->title($data['title'])
                    ->body($data['message'])
            )
            ->data([
                'type' => $data['type'],
                'action_url' => $data['action_url'],
            ]);
    }

    public function toSms(mixed $notifiable): SmsMessage
    {
        $reason = mb_substr((string) $this->vendorApplication->flag_reason, 0, 80);
        $deadline = $this->vendorApplication->grace_period_ends_at?->toFormattedDateString() ?? 'soon';

        return (new SmsMessage)->content(
            "Surprise moi: Your vendor application needs more details. Reason: {$reason}. Please update by {$deadline}."
        );
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
docker compose exec app php artisan test --compact --filter=VendorFlaggedNotificationTest
```

Expected: PASS for all seven tests.

- [ ] **Step 5: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Notifications/VendorFlaggedNotification.php tests/Feature/Notifications/VendorFlaggedNotificationTest.php
git commit -m "feat(vendor-application): add VendorFlaggedNotification (mail/db/broadcast/fcm/sms)"
```

---

## Task 8: Notification — `VendorFlagReminderNotification`

**Files:**
- Create: `app/Notifications/VendorFlagReminderNotification.php`

- [ ] **Step 1: Implement the notification**

Create `app/Notifications/VendorFlagReminderNotification.php`. The structure mirrors Task 7 with different copy. Replace the file contents with:

```php
<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\VendorApplication;
use App\Notifications\Concerns\HasSmsChannel;
use App\Notifications\Messages\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Channels\BroadcastChannel;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

class VendorFlagReminderNotification extends Notification implements ShouldQueue
{
    use HasSmsChannel, Queueable;

    public function __construct(public VendorApplication $vendorApplication)
    {
        $this->queue = 'notifications';
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'mail', BroadcastChannel::class];

        if ($notifiable->deviceTokens()->exists()) {
            $channels[] = FcmChannel::class;
        }

        if (! empty($notifiable->phone)) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $deadline = $this->vendorApplication->grace_period_ends_at?->toFormattedDateString() ?? 'soon';

        return (new MailMessage)
            ->subject('Reminder: Your Vendor Application Is Due Soon')
            ->greeting("Hello {$notifiable->name},")
            ->line('This is a reminder that your vendor application still needs the details we requested.')
            ->line("Your deadline is **{$deadline}**.")
            ->line('Please update and resubmit your application to keep it active.')
            ->action('Open the App', config('deep_links.share_base_url'))
            ->salutation('Best regards, The Surprise moi Team');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $deadline = $this->vendorApplication->grace_period_ends_at?->toFormattedDateString() ?? 'soon';

        return [
            'type' => 'vendor_flag_reminder',
            'title' => 'Reminder: Your Vendor Application Is Due Soon',
            'message' => "Your vendor application is due {$deadline}. Please respond to avoid rejection.",
            'action_url' => '/dashboard/vendor-applications/'.$this->vendorApplication->id,
            'actor' => null,
            'subject' => [
                'id' => $this->vendorApplication->id,
                'type' => 'vendor_application',
                'status' => 'flagged',
            ],
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $data = $this->toDatabase($notifiable);

        return new BroadcastMessage([
            'title' => $data['title'],
            'body' => $data['message'],
            'vendor_application_id' => $this->vendorApplication->id,
            'grace_period_ends_at' => $this->vendorApplication->grace_period_ends_at?->toIso8601String(),
            'action_url' => $data['action_url'],
        ]);
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        $data = $this->toDatabase($notifiable);

        return FcmMessage::create()
            ->notification(
                FcmNotification::create()
                    ->title($data['title'])
                    ->body($data['message'])
            )
            ->data([
                'type' => $data['type'],
                'action_url' => $data['action_url'],
            ]);
    }

    public function toSms(mixed $notifiable): SmsMessage
    {
        $deadline = $this->vendorApplication->grace_period_ends_at?->toFormattedDateString() ?? 'soon';

        return (new SmsMessage)->content(
            "Surprise moi: Reminder — your vendor application is due {$deadline}. Please respond to avoid rejection."
        );
    }
}
```

- [ ] **Step 2: Smoke check the class loads**

```bash
docker compose exec app php artisan tinker --execute="dump(class_exists(\App\Notifications\VendorFlagReminderNotification::class));"
```

Expected: `true`.

- [ ] **Step 3: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Notifications/VendorFlagReminderNotification.php
git commit -m "feat(vendor-application): add VendorFlagReminderNotification"
```

---

## Task 9: Notification — `VendorFlagExpiredNotification` (admin alert)

**Files:**
- Create: `app/Notifications/VendorFlagExpiredNotification.php`

- [ ] **Step 1: Implement the notification**

Create `app/Notifications/VendorFlagExpiredNotification.php`:

```php
<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\VendorApplication;
use App\Notifications\Concerns\HasSmsChannel;
use App\Notifications\Messages\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Channels\BroadcastChannel;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorFlagExpiredNotification extends Notification implements ShouldQueue
{
    use HasSmsChannel, Queueable;

    public function __construct(public VendorApplication $vendorApplication)
    {
        $this->queue = 'notifications';
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'mail', BroadcastChannel::class];

        if (! empty($notifiable->phone)) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $vendorName = $this->vendorApplication->user?->name ?? 'a vendor';
        $deadline = $this->vendorApplication->grace_period_ends_at?->toFormattedDateString() ?? 'unknown';

        return (new MailMessage)
            ->subject('Vendor Application Grace Period Expired')
            ->greeting("Hello {$notifiable->name},")
            ->line("The grace period for {$vendorName}'s vendor application has expired without a response.")
            ->line('**Original reason:** '.$this->vendorApplication->flag_reason)
            ->line("**Deadline that passed:** {$deadline}")
            ->action('Review Application', url('/dashboard/vendor-applications/'.$this->vendorApplication->id))
            ->salutation('Surprise moi admin alerts');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'vendor_flag_expired',
            'title' => 'Vendor Application Grace Period Expired',
            'message' => "Application #{$this->vendorApplication->id} grace period expired. Please review.",
            'action_url' => '/dashboard/vendor-applications/'.$this->vendorApplication->id,
            'actor' => null,
            'subject' => [
                'id' => $this->vendorApplication->id,
                'type' => 'vendor_application',
                'status' => 'flagged',
            ],
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $data = $this->toDatabase($notifiable);

        return new BroadcastMessage([
            'title' => $data['title'],
            'body' => $data['message'],
            'vendor_application_id' => $this->vendorApplication->id,
            'action_url' => $data['action_url'],
        ]);
    }

    public function toSms(mixed $notifiable): SmsMessage
    {
        return (new SmsMessage)->content(
            "Surprise moi: Vendor application #{$this->vendorApplication->id} grace period expired. Please review."
        );
    }
}
```

- [ ] **Step 2: Smoke check**

```bash
docker compose exec app php artisan tinker --execute="dump(class_exists(\App\Notifications\VendorFlagExpiredNotification::class));"
```

Expected: `true`.

- [ ] **Step 3: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Notifications/VendorFlagExpiredNotification.php
git commit -m "feat(vendor-application): add VendorFlagExpiredNotification (admin alert)"
```

---

## Task 10: Model — `flag()` method (wires event + notification)

**Files:**
- Modify: `app/Models/VendorApplication.php`

- [ ] **Step 1: Write failing tests**

Append to `tests/Feature/VendorApplicationFlagTest.php`:

```php
    public function test_flag_method_sets_status_and_columns(): void
    {
        \Notification::fake();
        \Event::fake();

        \App\Models\Setting::set('vendor_application_grace_period_days', '7', 'number');

        $reviewer = User::factory()->create();
        $app = VendorApplication::factory()->underReview()->create();

        $result = $app->flag($reviewer->id, 'Ghana card back is unreadable');

        $this->assertTrue($result);
        $fresh = $app->fresh();
        $this->assertSame(VendorApplication::STATUS_FLAGGED, $fresh->status);
        $this->assertSame('Ghana card back is unreadable', $fresh->flag_reason);
        $this->assertSame($reviewer->id, $fresh->flagged_by);
        $this->assertNotNull($fresh->flagged_at);
        $this->assertTrue($fresh->grace_period_ends_at->isAfter(now()->addDays(6)));
        $this->assertNull($fresh->flag_reminder_sent_at);
        $this->assertNull($fresh->flag_expired_alert_sent_at);
    }

    public function test_flag_method_dispatches_event_and_notification(): void
    {
        \Notification::fake();
        \Event::fake();

        $reviewer = User::factory()->create();
        $app = VendorApplication::factory()->underReview()->create();

        $app->flag($reviewer->id, 'Ghana card back is unreadable');

        \Event::assertDispatched(\App\Events\VendorFlagged::class);
        \Notification::assertSentTo(
            $app->user,
            \App\Notifications\VendorFlaggedNotification::class
        );
    }

    public function test_flag_method_clears_previous_reminder_stamps_on_re_flag(): void
    {
        // The model method has no status guard — it can be invoked on an
        // already-flagged application. This test proves the stamps reset so the
        // scheduled command treats the row as a fresh flag against the new deadline.
        \Notification::fake();
        \Event::fake();

        $reviewer = User::factory()->create();
        $app = VendorApplication::factory()->flagged()->create([
            'flag_reminder_sent_at' => now()->subDay(),
            'flag_expired_alert_sent_at' => now()->subDay(),
        ]);

        $app->flag($reviewer->id, 'Still missing TIN document');

        $this->assertNull($app->fresh()->flag_reminder_sent_at);
        $this->assertNull($app->fresh()->flag_expired_alert_sent_at);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker compose exec app php artisan test --compact --filter=test_flag_method
```

Expected: FAIL with "Call to undefined method ::flag()".

- [ ] **Step 3: Implement `flag()` and add the use import**

In `app/Models/VendorApplication.php`, add to the `use` block at the top:

```php
use App\Events\VendorFlagged;
use App\Notifications\VendorFlaggedNotification;
```

(They sit alphabetically — match the existing ordering.)

Add the method just before `markUnderReview()`:

```php
    /**
     * Flag the vendor application for missing or unclear details.
     *
     * Puts the application into a time-boxed grace period so the vendor can
     * edit and resubmit. Admins retain manual control — there is no
     * auto-rejection.
     */
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

- [ ] **Step 4: Run all tests in the class**

```bash
docker compose exec app php artisan test --compact --filter=VendorApplicationFlagTest
```

Expected: PASS for all tests so far.

- [ ] **Step 5: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/VendorApplication.php tests/Feature/VendorApplicationFlagTest.php
git commit -m "feat(vendor-application): add flag() model method with event and notification"
```

---

## Task 11: User scope — `admins()` (admin or super_admin)

**Files:**
- Modify: `app/Models/User.php`

- [ ] **Step 1: Write a failing test**

Append to `tests/Feature/VendorApplicationFlagTest.php`:

```php
    public function test_user_admins_scope_returns_admin_and_super_admin(): void
    {
        User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'super_admin']);
        User::factory()->create(['role' => 'vendor']);
        User::factory()->create(['role' => 'customer']);

        $admins = User::admins()->get();

        $this->assertCount(2, $admins);
        $this->assertContains('admin', $admins->pluck('role')->all());
        $this->assertContains('super_admin', $admins->pluck('role')->all());
    }
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker compose exec app php artisan test --compact --filter=test_user_admins_scope
```

Expected: FAIL with "Call to undefined method ::admins()".

- [ ] **Step 3: Add the scope**

In `app/Models/User.php`, add the scope method (near the other scopes if any exist; otherwise near the bottom of the class above the closing brace):

```php
    /**
     * Scope to admin and super_admin users.
     */
    public function scopeAdmins($query)
    {
        return $query->whereIn('role', ['admin', 'super_admin']);
    }
```

- [ ] **Step 4: Run test to verify it passes**

```bash
docker compose exec app php artisan test --compact --filter=test_user_admins_scope
```

Expected: PASS.

- [ ] **Step 5: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/User.php tests/Feature/VendorApplicationFlagTest.php
git commit -m "feat(user): add admins scope (admin or super_admin)"
```

---

## Task 12: Form Request — `FlagVendorApplicationRequest`

**Files:**
- Create: `app/Http/Requests/FlagVendorApplicationRequest.php`

- [ ] **Step 1: Generate the form request**

```bash
docker compose exec app php artisan make:request FlagVendorApplicationRequest --no-interaction
```

- [ ] **Step 2: Implement the rules**

Replace `app/Http/Requests/FlagVendorApplicationRequest.php` contents with:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FlagVendorApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && in_array($this->user()->role, ['admin', 'super_admin'], true);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'flag_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'flag_reason.required' => 'Please tell the vendor what is missing or unclear.',
            'flag_reason.min' => 'Please provide at least 10 characters of detail.',
            'flag_reason.max' => 'The reason cannot exceed 1000 characters.',
        ];
    }
}
```

- [ ] **Step 3: Smoke check it loads**

```bash
docker compose exec app php artisan tinker --execute="dump(class_exists(\App\Http\Requests\FlagVendorApplicationRequest::class));"
```

Expected: `true`.

- [ ] **Step 4: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/FlagVendorApplicationRequest.php
git commit -m "feat(vendor-application): add FlagVendorApplicationRequest"
```

---

## Task 13: Controller — `flag()` action and extend approve/reject guards

**Files:**
- Modify: `app/Http/Controllers/VendorApplicationController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Write failing tests for the controller**

Append to `tests/Feature/VendorApplicationFlagTest.php`:

```php
    public function test_admin_can_flag_a_pending_application(): void
    {
        \Notification::fake();
        \Event::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $app = VendorApplication::factory()
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->readyToSubmit()
            ->withPaymentCompleted()
            ->pending()
            ->create();

        $response = $this->actingAs($admin)
            ->post("/dashboard/vendor-applications/{$app->id}/flag", [
                'flag_reason' => 'Ghana card back image is unreadable, please re-upload.',
            ]);

        $response->assertRedirect();
        $this->assertSame(VendorApplication::STATUS_FLAGGED, $app->fresh()->status);
        $this->assertSame($admin->id, $app->fresh()->flagged_by);
    }

    public function test_admin_can_flag_an_under_review_application(): void
    {
        \Notification::fake();
        \Event::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $app = VendorApplication::factory()
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->readyToSubmit()
            ->withPaymentCompleted()
            ->underReview()
            ->create();

        $response = $this->actingAs($admin)
            ->post("/dashboard/vendor-applications/{$app->id}/flag", [
                'flag_reason' => 'Need a clearer selfie photo, current one is blurry.',
            ]);

        $response->assertRedirect();
        $this->assertSame(VendorApplication::STATUS_FLAGGED, $app->fresh()->status);
    }

    public function test_admin_cannot_flag_already_approved_application(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $app = VendorApplication::factory()
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->readyToSubmit()
            ->withPaymentCompleted()
            ->approved()
            ->create();

        $response = $this->actingAs($admin)
            ->from("/dashboard/vendor-applications/{$app->id}")
            ->post("/dashboard/vendor-applications/{$app->id}/flag", [
                'flag_reason' => 'Ghana card back is unreadable, please re-upload it.',
            ]);

        $response->assertRedirect("/dashboard/vendor-applications/{$app->id}");
        $response->assertSessionHas('error');
        $this->assertSame(VendorApplication::STATUS_APPROVED, $app->fresh()->status);
    }

    public function test_flag_reason_is_required(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $app = VendorApplication::factory()
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->readyToSubmit()
            ->withPaymentCompleted()
            ->pending()
            ->create();

        $response = $this->actingAs($admin)
            ->post("/dashboard/vendor-applications/{$app->id}/flag", []);

        $response->assertSessionHasErrors('flag_reason');
    }

    public function test_flag_reason_must_be_at_least_10_characters(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $app = VendorApplication::factory()
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->readyToSubmit()
            ->withPaymentCompleted()
            ->pending()
            ->create();

        $response = $this->actingAs($admin)
            ->post("/dashboard/vendor-applications/{$app->id}/flag", [
                'flag_reason' => 'too short',
            ]);

        $response->assertSessionHasErrors('flag_reason');
    }

    public function test_non_admin_cannot_flag(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $app = VendorApplication::factory()
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->readyToSubmit()
            ->withPaymentCompleted()
            ->pending()
            ->create();

        $response = $this->actingAs($vendor)
            ->post("/dashboard/vendor-applications/{$app->id}/flag", [
                'flag_reason' => 'attempting to flag from a vendor account',
            ]);

        $response->assertForbidden();
    }

    public function test_flagged_application_can_be_approved_by_admin(): void
    {
        \Notification::fake();
        \Event::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $app = VendorApplication::factory()
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->readyToSubmit()
            ->withPaymentCompleted()
            ->flagged()
            ->create();

        $response = $this->actingAs($admin)
            ->post("/dashboard/vendor-applications/{$app->id}/approve");

        $response->assertRedirect();
        $this->assertSame(VendorApplication::STATUS_APPROVED, $app->fresh()->status);
    }

    public function test_flagged_application_can_be_rejected_by_admin(): void
    {
        \Notification::fake();
        \Event::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $app = VendorApplication::factory()
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->readyToSubmit()
            ->withPaymentCompleted()
            ->flagged()
            ->create();

        $response = $this->actingAs($admin)
            ->post("/dashboard/vendor-applications/{$app->id}/reject", [
                'rejection_reason' => 'No response within grace period and details still missing.',
            ]);

        $response->assertRedirect();
        $fresh = $app->fresh();
        $this->assertSame(VendorApplication::STATUS_REJECTED, $fresh->status);
        $this->assertNotNull($fresh->flag_reason); // preserved
        $this->assertNotNull($fresh->rejection_reason);
    }

    public function test_admin_can_re_flag_an_already_flagged_application(): void
    {
        \Notification::fake();
        \Event::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $app = VendorApplication::factory()
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->readyToSubmit()
            ->withPaymentCompleted()
            ->flagged()
            ->create([
                'flag_reminder_sent_at' => now()->subDay(),
            ]);

        $response = $this->actingAs($admin)
            ->post("/dashboard/vendor-applications/{$app->id}/flag", [
                'flag_reason' => 'Updated reason — still need a clearer TIN document.',
            ]);

        $response->assertRedirect();
        $fresh = $app->fresh();
        $this->assertSame(VendorApplication::STATUS_FLAGGED, $fresh->status);
        $this->assertSame('Updated reason — still need a clearer TIN document.', $fresh->flag_reason);
        $this->assertNull($fresh->flag_reminder_sent_at); // stamp cleared so reminder fires again
    }
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker compose exec app php artisan test --compact --filter=VendorApplicationFlagTest
```

Expected: FAIL on the controller tests (route not found, or wrong status). The earlier model tests should still pass.

- [ ] **Step 3: Add the route**

In `routes/web.php`, inside the existing `vendor-applications` group around line 156-162, add the flag route after the reject route:

```php
        Route::post('/{vendorApplication}/flag', [VendorApplicationController::class, 'flag'])->name('flag');
```

- [ ] **Step 4: Add the controller action**

In `app/Http/Controllers/VendorApplicationController.php`, add a `use` statement at the top:

```php
use App\Http\Requests\FlagVendorApplicationRequest;
```

Add the method after the existing `reject()` method:

```php
    /**
     * Flag a vendor application for missing or unclear details.
     */
    public function flag(FlagVendorApplicationRequest $request, VendorApplication $vendorApplication)
    {
        if (! $vendorApplication->canBeReviewed()) {
            return back()->with('error', 'This application cannot be reviewed. Ensure all steps are completed, payment is made, and the application has been submitted.');
        }

        if (! in_array($vendorApplication->status, [
            VendorApplication::STATUS_PENDING,
            VendorApplication::STATUS_UNDER_REVIEW,
            VendorApplication::STATUS_FLAGGED,
        ], true)) {
            return back()->with('error', 'This application cannot be flagged in its current state.');
        }

        $vendorApplication->flag(Auth::id(), $request->input('flag_reason'));

        app(\App\Services\AuditService::class)->record(
            'vendor_application.flagged',
            $vendorApplication,
            Auth::user(),
            extra: [
                'reason' => $request->input('flag_reason'),
                'grace_period_ends_at' => $vendorApplication->grace_period_ends_at?->toIso8601String(),
            ],
            retentionClass: 'critical'
        );

        return redirect()->route('vendor-applications.show', $vendorApplication)
            ->with('success', 'Vendor application flagged. The vendor has been notified.');
    }
```

- [ ] **Step 5: Update `approve()` and `reject()` status guards**

In the same file, update both guards to include `STATUS_FLAGGED`:

In `approve()`, change:

```php
        if (! in_array($vendorApplication->status, [VendorApplication::STATUS_PENDING, VendorApplication::STATUS_UNDER_REVIEW])) {
```

to:

```php
        if (! in_array($vendorApplication->status, [VendorApplication::STATUS_PENDING, VendorApplication::STATUS_UNDER_REVIEW, VendorApplication::STATUS_FLAGGED])) {
```

In `reject()`, apply the same change to the equivalent line.

Also remove the leftover debugging line in `approve()` if it is still present (`dump($vendorApplication->completed_step, …)` at around line 256). The current production code has a `dump()` call from prior debugging; remove it as part of this task to keep the change minimal.

- [ ] **Step 6: Run all tests**

```bash
docker compose exec app php artisan test --compact --filter=VendorApplicationFlagTest
```

Expected: PASS for all tests in the class.

- [ ] **Step 7: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/VendorApplicationController.php routes/web.php tests/Feature/VendorApplicationFlagTest.php
git commit -m "feat(vendor-application): add flag controller action, route, and extend approve/reject guards"
```

---

## Task 14: Scheduled command — `ProcessVendorApplicationFlagDeadlines`

**Files:**
- Create: `app/Console/Commands/ProcessVendorApplicationFlagDeadlines.php`
- Create: `tests/Feature/Console/ProcessVendorApplicationFlagDeadlinesTest.php`

- [ ] **Step 1: Write failing tests**

Create `tests/Feature/Console/ProcessVendorApplicationFlagDeadlinesTest.php`:

```php
<?php

namespace Tests\Feature\Console;

use App\Models\Setting;
use App\Models\User;
use App\Models\VendorApplication;
use App\Notifications\VendorFlagExpiredNotification;
use App\Notifications\VendorFlagReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProcessVendorApplicationFlagDeadlinesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_reminder_when_deadline_within_window(): void
    {
        Notification::fake();
        Setting::set('vendor_application_flag_reminder_days_before', '2', 'number');

        $app = VendorApplication::factory()->flagged()->create([
            'grace_period_ends_at' => now()->addDay(),
            'flag_reminder_sent_at' => null,
        ]);

        $this->artisan('vendor-applications:process-flag-deadlines')->assertSuccessful();

        Notification::assertSentTo($app->user, VendorFlagReminderNotification::class);
        $this->assertNotNull($app->fresh()->flag_reminder_sent_at);
    }

    public function test_does_not_send_reminder_twice(): void
    {
        Notification::fake();
        Setting::set('vendor_application_flag_reminder_days_before', '2', 'number');

        $app = VendorApplication::factory()->flagged()->create([
            'grace_period_ends_at' => now()->addDay(),
            'flag_reminder_sent_at' => now()->subHour(),
        ]);

        $this->artisan('vendor-applications:process-flag-deadlines')->assertSuccessful();

        Notification::assertNotSentTo($app->user, VendorFlagReminderNotification::class);
    }

    public function test_does_not_send_reminder_if_deadline_passed(): void
    {
        Notification::fake();
        Setting::set('vendor_application_flag_reminder_days_before', '2', 'number');

        $app = VendorApplication::factory()->flagged()->create([
            'grace_period_ends_at' => now()->subHour(),
            'flag_reminder_sent_at' => null,
        ]);

        $this->artisan('vendor-applications:process-flag-deadlines')->assertSuccessful();

        Notification::assertNotSentTo($app->user, VendorFlagReminderNotification::class);
    }

    public function test_does_not_send_reminder_if_outside_window(): void
    {
        Notification::fake();
        Setting::set('vendor_application_flag_reminder_days_before', '2', 'number');

        $app = VendorApplication::factory()->flagged()->create([
            'grace_period_ends_at' => now()->addDays(5),
            'flag_reminder_sent_at' => null,
        ]);

        $this->artisan('vendor-applications:process-flag-deadlines')->assertSuccessful();

        Notification::assertNotSentTo($app->user, VendorFlagReminderNotification::class);
    }

    public function test_sends_expired_alert_to_admins_after_deadline(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $vendor = User::factory()->create(['role' => 'vendor']); // should NOT receive

        $app = VendorApplication::factory()->flagged()->create([
            'grace_period_ends_at' => now()->subHour(),
            'flag_expired_alert_sent_at' => null,
        ]);

        $this->artisan('vendor-applications:process-flag-deadlines')->assertSuccessful();

        Notification::assertSentTo($admin, VendorFlagExpiredNotification::class);
        Notification::assertSentTo($superAdmin, VendorFlagExpiredNotification::class);
        Notification::assertNotSentTo($vendor, VendorFlagExpiredNotification::class);
        $this->assertNotNull($app->fresh()->flag_expired_alert_sent_at);
    }

    public function test_does_not_send_expired_alert_twice(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);

        VendorApplication::factory()->flagged()->create([
            'grace_period_ends_at' => now()->subDay(),
            'flag_expired_alert_sent_at' => now()->subHour(),
        ]);

        $this->artisan('vendor-applications:process-flag-deadlines')->assertSuccessful();

        Notification::assertNotSentTo($admin, VendorFlagExpiredNotification::class);
    }

    public function test_skips_applications_whose_status_changed_after_flagging(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);

        // Vendor resubmitted; status moved off "flagged"
        VendorApplication::factory()->create([
            'status' => VendorApplication::STATUS_UNDER_REVIEW,
            'grace_period_ends_at' => now()->subHour(),
            'flag_expired_alert_sent_at' => null,
        ]);

        $this->artisan('vendor-applications:process-flag-deadlines')->assertSuccessful();

        Notification::assertNotSentTo($admin, VendorFlagExpiredNotification::class);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker compose exec app php artisan test --compact --filter=ProcessVendorApplicationFlagDeadlinesTest
```

Expected: FAIL with "Command 'vendor-applications:process-flag-deadlines' is not defined".

- [ ] **Step 3: Generate the command**

```bash
docker compose exec app php artisan make:command ProcessVendorApplicationFlagDeadlines --no-interaction
```

- [ ] **Step 4: Implement the command**

Replace `app/Console/Commands/ProcessVendorApplicationFlagDeadlines.php` contents with:

```php
<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\User;
use App\Models\VendorApplication;
use App\Notifications\VendorFlagExpiredNotification;
use App\Notifications\VendorFlagReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class ProcessVendorApplicationFlagDeadlines extends Command
{
    protected $signature = 'vendor-applications:process-flag-deadlines';

    protected $description = 'Send pre-deadline reminders to vendors and post-deadline alerts to admins for flagged vendor applications.';

    public function handle(): int
    {
        $this->sendReminders();
        $this->sendExpiredAlerts();

        return self::SUCCESS;
    }

    protected function sendReminders(): void
    {
        $reminderDays = (int) Setting::get('vendor_application_flag_reminder_days_before', 2);
        $auditService = app(\App\Services\AuditService::class);

        VendorApplication::query()
            ->flagged()
            ->whereNull('flag_reminder_sent_at')
            ->where('grace_period_ends_at', '>', now())
            ->where('grace_period_ends_at', '<=', now()->addDays($reminderDays))
            ->with('user')
            ->chunkById(50, function ($apps) use ($auditService, $reminderDays) {
                foreach ($apps as $app) {
                    if ($app->user) {
                        $app->user->notify(new VendorFlagReminderNotification($app));
                    }
                    $app->update(['flag_reminder_sent_at' => now()]);

                    $auditService->record(
                        'vendor_application.flag_reminder_sent',
                        $app,
                        null,
                        extra: ['days_before_deadline' => $reminderDays],
                        retentionClass: 'standard'
                    );
                }
            });
    }

    protected function sendExpiredAlerts(): void
    {
        $admins = User::admins()->get();
        $auditService = app(\App\Services\AuditService::class);

        VendorApplication::query()
            ->flagged()
            ->whereNull('flag_expired_alert_sent_at')
            ->where('grace_period_ends_at', '<', now())
            ->chunkById(50, function ($apps) use ($admins, $auditService) {
                foreach ($apps as $app) {
                    if ($admins->isNotEmpty()) {
                        Notification::send($admins, new VendorFlagExpiredNotification($app));
                    }
                    $app->update(['flag_expired_alert_sent_at' => now()]);

                    $auditService->record(
                        'vendor_application.flag_expired_alert_sent',
                        $app,
                        null,
                        extra: [
                            'deadline' => $app->grace_period_ends_at?->toIso8601String(),
                            'admins_notified_count' => $admins->count(),
                        ],
                        retentionClass: 'standard'
                    );
                }
            });
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
docker compose exec app php artisan test --compact --filter=ProcessVendorApplicationFlagDeadlinesTest
```

Expected: PASS for all seven command tests.

- [ ] **Step 6: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/ProcessVendorApplicationFlagDeadlines.php tests/Feature/Console/ProcessVendorApplicationFlagDeadlinesTest.php
git commit -m "feat(vendor-application): add ProcessVendorApplicationFlagDeadlines scheduled command"
```

---

## Task 15: Schedule the command in `routes/console.php`

**Files:**
- Modify: `routes/console.php`

- [ ] **Step 1: Add the schedule entry**

Append to `routes/console.php`:

```php
// Vendor application flag deadlines: send reminders before deadline, alert admins after.
Schedule::command('vendor-applications:process-flag-deadlines')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer();
```

- [ ] **Step 2: Verify the schedule registered**

```bash
docker compose exec app php artisan schedule:list
```

Expected: a row containing `vendor-applications:process-flag-deadlines` running daily.

- [ ] **Step 3: Commit**

```bash
git add routes/console.php
git commit -m "feat(vendor-application): schedule flag-deadlines command daily"
```

---

## Task 16: Vendor-facing API — surface flagged status in `/vendor-registration/status`

**Files:**
- Modify: `app/Http/Controllers/Api/V1/VendorRegistrationController.php`

The mobile app calls `GET /api/v1/vendor-registration/status` and reads `is_editable`, `status`, and `message`. We need the response to include the flag reason and deadline so the mobile UI can render the banner.

- [ ] **Step 1: Read the current `status()` method**

```bash
docker compose exec app php artisan tinker --execute="readfile(app_path('Http/Controllers/Api/V1/VendorRegistrationController.php'));" | head -90
```

Locate the existing `status()` method (around line 22-60) and the `getStatusMessage()` helper (around line 62-87).

- [ ] **Step 2: Extend the response payload**

In `status()`, find the array returned to the client (the block around `'is_editable' => $isEditable,`) and add the following keys:

```php
                'flag_reason' => $application->flag_reason,
                'grace_period_ends_at' => $application->grace_period_ends_at?->toIso8601String(),
                'is_flagged' => $application->status === VendorApplication::STATUS_FLAGGED,
```

In `getStatusMessage()`, add a flagged branch *before* the existing fallback. Locate the method body — based on its current structure (`if ($application->status === STATUS_REJECTED)` etc.), add:

```php
        if ($application->status === VendorApplication::STATUS_FLAGGED) {
            $deadline = $application->grace_period_ends_at?->toFormattedDateString() ?? 'soon';

            return "We need more details on your application. Reason: {$application->flag_reason}. Please respond by {$deadline}.";
        }
```

If the existing method uses a different control structure (e.g. `match`), translate the same intent into that style. Read the surrounding 30 lines before editing.

- [ ] **Step 3: Spot-test by hitting the endpoint via tinker**

```bash
docker compose exec app php artisan tinker --execute="
\$user = \App\Models\User::factory()->create();
\$app = \App\Models\VendorApplication::factory()->for(\$user)->flagged()->create();
\$controller = app(\App\Http\Controllers\Api\V1\VendorRegistrationController::class);
\$request = new \Illuminate\Http\Request();
\$request->setUserResolver(fn () => \$user);
dump(\$controller->status(\$request));
"
```

Expected: payload includes `is_flagged: true`, a non-null `flag_reason`, a `grace_period_ends_at`, and a `message` mentioning the reason.

- [ ] **Step 4: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/V1/VendorRegistrationController.php
git commit -m "feat(vendor-application): expose flagged state in vendor-registration status endpoint"
```

---

## Task 17: Pass `grace_period_days` from controller to admin show page

**Files:**
- Modify: `app/Http/Controllers/VendorApplicationController.php`
- Modify: `resources/js/pages/vendor-applications/show.tsx`

We need the admin UI to display "Vendor will have N days to respond" where N comes from settings, not a hard-coded constant.

- [ ] **Step 1: Pass the setting in `show()`**

In `app/Http/Controllers/VendorApplicationController.php`, add a `use` statement near the top:

```php
use App\Models\Setting;
```

In the `show()` method's `Inertia::render(...)` call, alongside the existing `'application' => …` and `'vendorOrders' => …` keys, add:

```php
            'gracePeriodDays' => (int) Setting::get('vendor_application_grace_period_days', 7),
```

Also add to the application array (alongside `rejection_reason`) the new flagging fields the frontend will read:

```php
                'flagged_at' => $vendorApplication->flagged_at?->toIso8601String(),
                'flag_reason' => $vendorApplication->flag_reason,
                'flagged_by' => $vendorApplication->flagger ? [
                    'id' => $vendorApplication->flagger->id,
                    'name' => $vendorApplication->flagger->name,
                ] : null,
                'grace_period_ends_at' => $vendorApplication->grace_period_ends_at?->toIso8601String(),
                'flag_reminder_sent_at' => $vendorApplication->flag_reminder_sent_at?->toIso8601String(),
```

Update the eager-load on the same method to include `flagger`:

```php
        $vendorApplication->load(['user', 'reviewer', 'flagger', 'bespokeServices', 'latestOnboardingPayment', 'vendorVisit.fieldAgent']);
```

- [ ] **Step 2: Add the new fields to the React `Application` interface**

In `resources/js/pages/vendor-applications/show.tsx`, find the `Application` interface (around line 70-123). Add these fields just below `rejection_reason: string | null;`:

```ts
    flagged_at: string | null;
    flag_reason: string | null;
    flagged_by: { id: number; name: string } | null;
    grace_period_ends_at: string | null;
    flag_reminder_sent_at: string | null;
```

Update the `Props` interface to include `gracePeriodDays`:

```ts
interface Props {
    application: Application;
    vendorOrders: VendorOrders | null;
    gracePeriodDays: number;
}
```

Update the component signature to destructure the new prop:

```ts
export default function ShowVendorApplication({
    application,
    vendorOrders,
    gracePeriodDays,
}: Props) {
```

- [ ] **Step 3: Build the frontend to surface any type errors**

```bash
docker compose exec app pnpm run build
```

Expected: build completes with no TypeScript errors.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/VendorApplicationController.php resources/js/pages/vendor-applications/show.tsx
git commit -m "feat(vendor-application): surface flagging fields and grace days to admin show page"
```

---

## Task 18: Frontend — register `flagged` in status badge maps

**Files:**
- Modify: `resources/js/pages/vendor-applications/show.tsx`
- Modify: `resources/js/pages/vendor-applications/index.tsx`

- [ ] **Step 1: Add `flagged` to the status badge in show.tsx**

In `resources/js/pages/vendor-applications/show.tsx`, find `getStatusBadge` (around line 164-175). Update the `variants` map to include flagged:

```ts
const getStatusBadge = (status: string) => {
    const variants: Record<string, { variant: any; label: string }> = {
        pending: { variant: 'secondary', label: 'Pending Review' },
        under_review: { variant: 'default', label: 'Under Review' },
        flagged: { variant: 'secondary', label: 'Needs More Info' },
        approved: { variant: 'default', label: 'Approved' },
        rejected: { variant: 'destructive', label: 'Rejected' },
    };

    const config = variants[status] || variants.pending;

    return <Badge variant={config.variant}>{config.label}</Badge>;
};
```

- [ ] **Step 2: Add the same entry in `index.tsx`**

In `resources/js/pages/vendor-applications/index.tsx`, locate the equivalent status-to-label map (search for `'pending'` and `'rejected'` near each other). Add a `flagged` key with label `'Needs More Info'`. The exact code shape depends on the file; mirror whatever structure exists.

If the file uses the same `getStatusBadge` helper, copy the exact `variants` map shape from Task 18 Step 1.

- [ ] **Step 3: Build to verify**

```bash
docker compose exec app pnpm run build
```

Expected: no TypeScript errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/vendor-applications/show.tsx resources/js/pages/vendor-applications/index.tsx
git commit -m "feat(vendor-application): render flagged status badge in admin views"
```

---

## Task 19: Frontend — Flag button and dialog on the admin show page

**Files:**
- Modify: `resources/js/pages/vendor-applications/show.tsx`

- [ ] **Step 1: Add `showFlagDialog` state and `flag_reason` form field**

Find the existing `useState`/`useForm` block at the top of `ShowVendorApplication` (around line 204-211). Beside `setShowRejectDialog`, add:

```ts
    const [showFlagDialog, setShowFlagDialog] = useState(false);
```

Update the `useForm` initial state to include `flag_reason`:

```ts
    const { data, setData, post, processing, reset } = useForm({
        rejection_reason: '',
        flag_reason: '',
    });
```

- [ ] **Step 2: Add the `handleFlag` callback**

After the existing `handleReject` function (around line 234-244), add:

```ts
    const handleFlag = () => {
        if (data.flag_reason.trim().length < 10) {
            alert('Please provide a detailed reason (at least 10 characters).');
            return;
        }
        post(`/dashboard/vendor-applications/${application.id}/flag`, {
            onSuccess: () => {
                setShowFlagDialog(false);
                reset('flag_reason');
            },
        });
    };
```

- [ ] **Step 3: Update `canApproveOrReject` and add a separate `canFlag` flag**

Find the line setting `canApproveOrReject` (around line 252-254) and replace with:

```ts
    const canApproveOrReject =
        application.can_be_reviewed &&
        ['pending', 'under_review', 'flagged'].includes(application.status);

    const canFlag =
        application.can_be_reviewed &&
        ['pending', 'under_review', 'flagged'].includes(application.status);
```

- [ ] **Step 4: Render the Flag button in the admin actions area**

Find the existing approve/reject buttons in the JSX (search for `handleApprove` and `handleReject`). Adjacent to them, conditionally render a Flag button when `canFlag` is true. Mirror the existing button structure exactly — same component (`Button`), same sizing, same icon (`AlertCircle` from `lucide-react` if present in the import set; otherwise `Flag`):

```tsx
                                {canFlag && (
                                    <Button
                                        variant="outline"
                                        onClick={() => setShowFlagDialog(true)}
                                        sx={{
                                            borderColor: 'warning.main',
                                            color: 'warning.main',
                                        }}
                                    >
                                        {application.status === 'flagged'
                                            ? 'Re-flag'
                                            : 'Flag for missing details'}
                                    </Button>
                                )}
```

If the existing buttons aren't using `sx` for custom colors, drop the `sx` prop and rely on `variant="outline"` + the default theme.

- [ ] **Step 5: Add the Flag dialog at the bottom of the component**

After the existing reject dialog (around line 2384-2436), add a new dialog:

```tsx
            {/* Flag Dialog */}
            <Dialog open={showFlagDialog} onOpenChange={setShowFlagDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Flag Vendor Application</DialogTitle>
                        <DialogDescription>
                            Tell the vendor what is missing or unclear. They will
                            have <strong>{gracePeriodDays} days</strong> to update
                            and resubmit before you decide whether to approve or
                            reject.
                        </DialogDescription>
                    </DialogHeader>
                    <Box sx={{ py: 2 }}>
                        <Textarea
                            placeholder="e.g. The Ghana card back image is unreadable, please re-upload a clearer photo."
                            value={data.flag_reason}
                            onChange={(e) =>
                                setData('flag_reason', e.target.value)
                            }
                            rows={5}
                            style={{ resize: 'none' }}
                        />
                        {data.flag_reason &&
                            data.flag_reason.length < 10 && (
                                <Typography
                                    sx={{
                                        mt: 1,
                                        fontSize: '0.875rem',
                                        color: 'error.main',
                                    }}
                                >
                                    Please provide at least 10 characters
                                </Typography>
                            )}
                    </Box>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setShowFlagDialog(false)}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={handleFlag}
                            disabled={
                                processing || data.flag_reason.length < 10
                            }
                        >
                            Flag Application
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
```

- [ ] **Step 6: Build and smoke check**

```bash
docker compose exec app pnpm run build
```

Expected: no TypeScript errors. If you see "Cannot find name 'reset'", verify Step 1 added `reset` to the destructured `useForm` return.

- [ ] **Step 7: Commit**

```bash
git add resources/js/pages/vendor-applications/show.tsx
git commit -m "feat(vendor-application): add Flag button and dialog to admin show page"
```

---

## Task 20: Frontend — flagged-state banner with reason and deadline timeline

**Files:**
- Modify: `resources/js/pages/vendor-applications/show.tsx`

- [ ] **Step 1: Add the flagged banner near the existing rejection banner**

Find the existing rejection banner block in `show.tsx` (around line 533-563, recognisable by `application.rejection_reason &&`). Just above (or below — same parent container) add a flagged banner:

```tsx
                        {application.flag_reason && (
                            <Box
                                sx={{
                                    mt: 2,
                                    borderRadius: 2,
                                    bgcolor: 'warning.light',
                                    opacity: 0.95,
                                    p: 1.5,
                                }}
                            >
                                <Typography
                                    sx={{
                                        fontSize: '0.875rem',
                                        fontWeight: 500,
                                        color: 'warning.dark',
                                    }}
                                >
                                    Flag Reason:
                                </Typography>
                                <Typography
                                    sx={{
                                        mt: 0.5,
                                        fontSize: '0.875rem',
                                        color: 'warning.dark',
                                    }}
                                >
                                    {application.flag_reason}
                                </Typography>
                                {application.grace_period_ends_at && (
                                    <Typography
                                        sx={{
                                            mt: 1,
                                            fontSize: '0.8125rem',
                                            color: 'warning.dark',
                                        }}
                                    >
                                        Vendor deadline:{' '}
                                        <strong>
                                            {new Date(
                                                application.grace_period_ends_at,
                                            ).toLocaleDateString()}
                                        </strong>
                                        {new Date(application.grace_period_ends_at) <
                                            new Date() && ' — passed'}
                                    </Typography>
                                )}
                                {application.flagged_by &&
                                    application.flagged_at && (
                                        <Typography
                                            sx={{
                                                mt: 0.5,
                                                fontSize: '0.75rem',
                                                color: 'warning.dark',
                                                opacity: 0.8,
                                            }}
                                        >
                                            Flagged by{' '}
                                            {application.flagged_by.name} on{' '}
                                            {new Date(
                                                application.flagged_at,
                                            ).toLocaleDateString()}
                                            {application.flag_reminder_sent_at &&
                                                ` · Reminder sent ${new Date(application.flag_reminder_sent_at).toLocaleDateString()}`}
                                        </Typography>
                                    )}
                            </Box>
                        )}
```

- [ ] **Step 2: Build**

```bash
docker compose exec app pnpm run build
```

Expected: build succeeds.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/vendor-applications/show.tsx
git commit -m "feat(vendor-application): show flag reason and deadline banner on admin page"
```

---

## Task 21: Frontend — `index.tsx` deadline-passed badge

**Files:**
- Modify: `resources/js/pages/vendor-applications/index.tsx`

- [ ] **Step 1: Read the current row rendering**

```bash
docker compose exec app php artisan tinker --execute="readfile(resource_path('js/pages/vendor-applications/index.tsx'));" | head -120
```

Locate the area where each row's status badge is rendered. The Inertia controller already passes `status` per row.

- [ ] **Step 2: Pass `grace_period_ends_at` from controller**

In `app/Http/Controllers/VendorApplicationController.php`, find the `index()` method's `$applications->through(fn ($app) => …)` (around line 52-67) and add to the per-row payload:

```php
                'grace_period_ends_at' => $app->grace_period_ends_at?->toIso8601String(),
```

- [ ] **Step 3: Update the row interface and render the badge**

In `resources/js/pages/vendor-applications/index.tsx`, locate the row interface (similar to the `Application` interface in show.tsx). Add the field:

```ts
    grace_period_ends_at: string | null;
```

In the row's JSX, where the status badge is rendered, add a sibling chip when the application is flagged. Insert near the existing status badge cell:

```tsx
{app.status === 'flagged' && app.grace_period_ends_at && (
    <Box
        component="span"
        sx={{
            ml: 1,
            px: 1,
            py: 0.25,
            borderRadius: 1,
            fontSize: '0.75rem',
            bgcolor:
                new Date(app.grace_period_ends_at) < new Date()
                    ? 'error.light'
                    : 'warning.light',
            color:
                new Date(app.grace_period_ends_at) < new Date()
                    ? 'error.dark'
                    : 'warning.dark',
        }}
    >
        {new Date(app.grace_period_ends_at) < new Date()
            ? 'Deadline passed'
            : `Due ${new Date(app.grace_period_ends_at).toLocaleDateString()}`}
    </Box>
)}
```

If `Box` is not imported in `index.tsx`, add the import from `@mui/material` to match `show.tsx`'s pattern.

- [ ] **Step 4: Build**

```bash
docker compose exec app pnpm run build
```

Expected: build succeeds.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/vendor-applications/index.tsx app/Http/Controllers/VendorApplicationController.php
git commit -m "feat(vendor-application): render deadline chip on flagged rows in admin index"
```

---

## Task 22: Final pass — full test suite and Pint

**Files:** none (verification)

- [ ] **Step 1: Run the full vendor-application test surface**

```bash
docker compose exec app php artisan test --compact \
  --filter='VendorApplicationFlagTest|VendorFlaggedNotificationTest|ProcessVendorApplicationFlagDeadlinesTest|VendorApplicationSubmittedNotificationTest|VendorApplicationDeletionTest'
```

Expected: PASS for every test.

- [ ] **Step 2: Run Pint over the dirty surface**

```bash
vendor/bin/pint --dirty --format agent
```

Expected: no remaining issues.

- [ ] **Step 3: Run the full suite**

```bash
docker compose exec app php artisan test --compact
```

Expected: PASS for the entire suite — confirms we did not regress anything outside the flagging feature.

If any test fails outside this feature's surface, investigate before claiming done. Do not skip or remove tests.

- [ ] **Step 4: Final review of `git log`**

```bash
git log --oneline main..HEAD
```

Expected: ~20 focused commits, one per task. Spec at the bottom, frontend at the top.

- [ ] **Step 5: Done — branch ready for PR**

The branch `feat/vendor-application-flagging` is ready to push and open a PR against `main`. Mention in the PR description that the spec is at `docs/superpowers/specs/2026-05-01-vendor-application-flagging-design.md` and the plan at `docs/superpowers/plans/2026-05-01-vendor-application-flagging.md`.

---

## Notes for the implementing engineer

- **The `dump()` call in `approve()`** (around line 256 of the current `VendorApplicationController.php`) appears to be leftover debugging. Task 13 Step 5 removes it. If review pushes back on bundling that fix into this branch, revert just that hunk and let it ship in its own PR.
- **Setting rows are not required** for the feature to function. `Setting::get(...)` returns sensible defaults (`7` grace days, `2` reminder days). If product wants to tune them, they can run `Setting::set('vendor_application_grace_period_days', '14', 'number')` from tinker once.
- **Deep links / mobile FCM `action_url`**: the URLs we send in notifications point to `/dashboard/vendor-applications/{id}` — that's the admin page. For the mobile app, FCM `data.type: vendor_flagged` is the routing signal, and the mobile client uses its own deep-link scheme. If `config('deep_links.share_base_url')` looks wrong on mail CTAs, that's the same value the existing `VendorApprovalNotification` uses; reuse without modification.
- **`User::admins()` scope**: if a similarly-named scope already exists (search for `whereIn.*role.*admin`), use that one instead of adding a duplicate. Task 11 includes a guard test that proves the behavior either way.
- **Resubmit path for first-time submissions**: Task 5 is intentionally narrow — first-time submissions still go to `STATUS_PENDING` with `submitted_at` set, identical to today. Only flagged-resubmit flips to `under_review`. Don't broaden this.
- **Test data and factories**: every test uses `RefreshDatabase`. The `flagged()` factory state in Task 3 creates a deadline 6 days in the future by default. Tests that need different timing pass explicit `grace_period_ends_at`/`flag_reminder_sent_at` overrides.
