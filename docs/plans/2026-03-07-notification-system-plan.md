# Notification System Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Migrate from custom notification system to Laravel's built-in notification framework and add Phase 1 notification types (OrderStatusChanged, NewReview, WawVideoLiked, ReviewReplied, NewChatMessage).

**Architecture:** Observer-triggered notification classes dispatch to database + broadcast channels. User's `Notifiable` trait handles storage and real-time delivery via Reverb. NotificationResource maintains existing frontend API contract.

**Tech Stack:** Laravel 12 Notifications, Reverb broadcasting, PHPUnit, React Echo

---

### Task 1: Database Migration

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_migrate_notifications_to_laravel_schema.php`

**Step 1: Create the migration**

```bash
php artisan make:migration migrate_notifications_to_laravel_schema --table=notifications --no-interaction
```

**Step 2: Write migration code**

Edit the generated migration file:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add Laravel notification columns
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('notifiable_type')->after('id');
            $table->unsignedBigInteger('notifiable_id')->after('notifiable_type');
        });

        // Step 2: Migrate existing data
        DB::table('notifications')->whereNotNull('user_id')->update([
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => DB::raw('user_id'),
        ]);

        // Step 3: Merge title and message into data JSON
        DB::table('notifications')->orderBy('id')->each(function ($notification) {
            $data = json_decode($notification->data ?? '{}', true) ?: [];
            $data['title'] = $notification->title;
            $data['message'] = $notification->message;

            DB::table('notifications')
                ->where('id', $notification->id)
                ->update(['data' => json_encode($data)]);
        });

        // Step 4: Drop old columns and add new indexes
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id', 'read_at']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropColumn(['user_id', 'title', 'message']);
            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('title')->after('type');
            $table->text('message')->after('title');
            $table->foreignId('user_id')->after('data')->constrained()->cascadeOnDelete();
        });

        DB::table('notifications')->update([
            'user_id' => DB::raw('notifiable_id'),
        ]);

        // Extract title/message from data JSON
        DB::table('notifications')->orderBy('id')->each(function ($notification) {
            $data = json_decode($notification->data ?? '{}', true) ?: [];

            DB::table('notifications')
                ->where('id', $notification->id)
                ->update([
                    'title' => $data['title'] ?? '',
                    'message' => $data['message'] ?? '',
                ]);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['notifiable_type', 'notifiable_id']);
            $table->dropColumn(['notifiable_type', 'notifiable_id']);
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
        });
    }
};
```

**Step 3: Run the migration**

```bash
php artisan migrate --no-interaction
```

**Step 4: Commit**

```bash
git add database/migrations/*migrate_notifications_to_laravel_schema*
git commit -m "refactor: migrate notifications table to Laravel schema"
```

---

### Task 2: Delete Custom Notification Model and Clean User Model

**Files:**
- Delete: `app/Models/Notification.php`
- Delete: `database/factories/NotificationFactory.php`
- Modify: `app/Models/User.php:514-533` (remove custom notification methods)
- Modify: `app/Models/User.php` (add `receivesBroadcastNotificationsOn()`)

**Step 1: Delete the custom Notification model**

```bash
rm app/Models/Notification.php
```

**Step 2: Delete the custom NotificationFactory**

```bash
rm database/factories/NotificationFactory.php
```

**Step 3: Clean User model - remove custom notification methods**

Remove lines 512-533 from `app/Models/User.php` (the `notifications()`, `unreadNotifications()`, and `getUnreadNotificationsCount()` methods). The `Notifiable` trait already provides these.

**Step 4: Add broadcast channel override to User model**

Add this method to `app/Models/User.php`:

```php
/**
 * The channels the user receives notification broadcasts on.
 */
public function receivesBroadcastNotificationsOn(): string
{
    return 'user.' . $this->id;
}
```

**Step 5: Commit**

```bash
git add -A
git commit -m "refactor: remove custom Notification model, use Notifiable trait"
```

---

### Task 3: Create NotificationResource

**Files:**
- Create: `app/Http/Resources/NotificationResource.php`
- Test: `tests/Unit/Resources/NotificationResourceTest.php`

**Step 1: Create the resource**

```bash
php artisan make:resource NotificationResource --no-interaction
```

**Step 2: Write the resource**

Edit `app/Http/Resources/NotificationResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->data ?? [];

        return [
            'id' => $this->id,
            'type' => $data['type'] ?? Str::snake(class_basename($this->type)),
            'title' => $data['title'] ?? '',
            'message' => $data['message'] ?? '',
            'action_url' => $data['action_url'] ?? null,
            'actor' => $data['actor'] ?? null,
            'data' => $data,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
```

**Step 3: Write the test**

```bash
php artisan make:test Resources/NotificationResourceTest --unit --phpunit --no-interaction
```

Edit `tests/Unit/Resources/NotificationResourceTest.php`:

```php
<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\NotificationResource;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use PHPUnit\Framework\TestCase;

class NotificationResourceTest extends TestCase
{
    public function test_transforms_notification_with_full_data(): void
    {
        $notification = new DatabaseNotification([
            'id' => 'test-uuid-123',
            'type' => 'App\\Notifications\\WawVideoLiked',
            'data' => [
                'type' => 'waw_video_liked',
                'title' => 'Someone liked your video',
                'message' => 'John liked your video',
                'action_url' => '/waw/videos/1',
                'actor' => ['id' => 1, 'name' => 'John', 'avatar' => null],
            ],
            'read_at' => null,
            'created_at' => '2026-03-07 12:00:00',
            'updated_at' => '2026-03-07 12:00:00',
        ]);

        $resource = (new NotificationResource($notification))->toArray(new Request());

        $this->assertEquals('test-uuid-123', $resource['id']);
        $this->assertEquals('waw_video_liked', $resource['type']);
        $this->assertEquals('Someone liked your video', $resource['title']);
        $this->assertEquals('John liked your video', $resource['message']);
        $this->assertEquals('/waw/videos/1', $resource['action_url']);
        $this->assertEquals(['id' => 1, 'name' => 'John', 'avatar' => null], $resource['actor']);
        $this->assertNull($resource['read_at']);
    }

    public function test_falls_back_to_class_basename_for_type(): void
    {
        $notification = new DatabaseNotification([
            'id' => 'test-uuid-456',
            'type' => 'App\\Notifications\\OrderStatusChanged',
            'data' => [
                'title' => 'Order Updated',
                'message' => 'Your order was confirmed',
            ],
            'read_at' => null,
            'created_at' => '2026-03-07 12:00:00',
            'updated_at' => '2026-03-07 12:00:00',
        ]);

        $resource = (new NotificationResource($notification))->toArray(new Request());

        $this->assertEquals('order_status_changed', $resource['type']);
    }
}
```

**Step 4: Run the test**

```bash
php artisan test --compact --filter=NotificationResourceTest
```
Expected: PASS

**Step 5: Commit**

```bash
git add app/Http/Resources/NotificationResource.php tests/Unit/Resources/NotificationResourceTest.php
git commit -m "feat: add NotificationResource for consistent API formatting"
```

---

### Task 4: Refactor NotificationService

**Files:**
- Modify: `app/Services/NotificationService.php`
- Rewrite: `tests/Unit/Services/NotificationServiceTest.php`

**Step 1: Rewrite the NotificationService**

Replace `app/Services/NotificationService.php` with:

```php
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationService
{
    public function getNotificationsForUser(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $user->notifications()->latest()->paginate($perPage);
    }

    public function getUnreadNotificationsForUser(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return $user->unreadNotifications()->latest()->get();
    }

    public function getUnreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function markAsRead(DatabaseNotification $notification): void
    {
        $notification->markAsRead();
    }

    public function markAsUnread(DatabaseNotification $notification): void
    {
        $notification->markAsUnread();
    }

    public function markAllAsRead(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }

    public function deleteNotification(DatabaseNotification $notification): bool
    {
        return $notification->delete();
    }
}
```

**Step 2: Rewrite the NotificationService test**

Replace `tests/Unit/Services/NotificationServiceTest.php` with:

```php
<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NotificationService;
    }

    private function createNotification(User $user, array $overrides = []): DatabaseNotification
    {
        return DatabaseNotification::create(array_merge([
            'id' => Str::uuid()->toString(),
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => $user->id,
            'data' => ['title' => 'Test', 'message' => 'Test message', 'type' => 'test'],
            'read_at' => null,
        ], $overrides));
    }

    public function test_gets_notifications_for_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->createNotification($user);
        $this->createNotification($user);
        $this->createNotification($user);
        $this->createNotification($other);

        $notifications = $this->service->getNotificationsForUser($user);

        $this->assertCount(3, $notifications);
    }

    public function test_gets_unread_notifications(): void
    {
        $user = User::factory()->create();

        $this->createNotification($user);
        $this->createNotification($user);
        $this->createNotification($user, ['read_at' => now()]);

        $unread = $this->service->getUnreadNotificationsForUser($user);

        $this->assertCount(2, $unread);
    }

    public function test_gets_unread_count(): void
    {
        $user = User::factory()->create();

        $this->createNotification($user);
        $this->createNotification($user);
        $this->createNotification($user, ['read_at' => now()]);

        $this->assertEquals(2, $this->service->getUnreadCount($user));
    }

    public function test_marks_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = $this->createNotification($user);

        $this->service->markAsRead($notification);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_marks_notification_as_unread(): void
    {
        $user = User::factory()->create();
        $notification = $this->createNotification($user, ['read_at' => now()]);

        $this->service->markAsUnread($notification);

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_marks_all_notifications_as_read(): void
    {
        $user = User::factory()->create();

        $this->createNotification($user);
        $this->createNotification($user);
        $this->createNotification($user, ['read_at' => now()]);

        $count = $this->service->markAllAsRead($user);

        $this->assertEquals(2, $count);
        $this->assertEquals(0, $user->unreadNotifications()->count());
    }

    public function test_deletes_notification(): void
    {
        $user = User::factory()->create();
        $notification = $this->createNotification($user);

        $result = $this->service->deleteNotification($notification);

        $this->assertTrue($result);
        $this->assertNull(DatabaseNotification::find($notification->id));
    }
}
```

**Step 3: Run the tests**

```bash
php artisan test --compact --filter=NotificationServiceTest
```
Expected: PASS

**Step 4: Commit**

```bash
git add app/Services/NotificationService.php tests/Unit/Services/NotificationServiceTest.php
git commit -m "refactor: simplify NotificationService to use Laravel Notifiable trait"
```

---

### Task 5: Refactor NotificationController

**Files:**
- Modify: `app/Http/Controllers/Api/V1/NotificationController.php`
- Modify: `routes/api.php:216-219` (change `{notification}` to `{id}` on parameterized routes)

**Step 1: Update routes**

In `routes/api.php`, change notification routes (lines 212-219) to:

```php
// Notification routes
Route::get('/notifications', [NotificationController::class, 'index']);
Route::get('/notifications/unread', [NotificationController::class, 'unread']);
Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
Route::patch('/notifications/{id}/unread', [NotificationController::class, 'markAsUnread']);
Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
```

**Step 2: Rewrite the controller**

Replace `app/Http/Controllers/Api/V1/NotificationController.php` with:

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min($request->input('per_page', 20), 100);
        $notifications = $this->notificationService->getNotificationsForUser($user, $perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'notifications' => NotificationResource::collection($notifications),
                'meta' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                ],
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $this->notificationService->getUnreadCount($user),
            ],
        ]);
    }

    public function unread(Request $request): JsonResponse
    {
        $user = $request->user();
        $notifications = $this->notificationService->getUnreadNotificationsForUser($user);

        return response()->json([
            'success' => true,
            'data' => [
                'notifications' => NotificationResource::collection($notifications),
            ],
        ]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $this->notificationService->markAsRead($notification);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
        ]);
    }

    public function markAsUnread(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $this->notificationService->markAsUnread($notification);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as unread',
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $this->notificationService->markAllAsRead($request->user());

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
            'data' => [
                'marked_count' => $count,
            ],
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $this->notificationService->deleteNotification($notification);

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted',
        ]);
    }
}
```

**Step 3: Write controller feature test**

```bash
php artisan make:test Api/V1/NotificationControllerTest --phpunit --no-interaction
```

Edit `tests/Feature/Api/V1/NotificationControllerTest.php`:

```php
<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createNotification(User $user, array $overrides = []): DatabaseNotification
    {
        return DatabaseNotification::create(array_merge([
            'id' => Str::uuid()->toString(),
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => $user->id,
            'data' => ['title' => 'Test', 'message' => 'Test message', 'type' => 'test'],
            'read_at' => null,
        ], $overrides));
    }

    public function test_index_returns_paginated_notifications(): void
    {
        $user = User::factory()->create();

        $this->createNotification($user);
        $this->createNotification($user);
        $this->createNotification($user);

        $response = $this->actingAs($user)->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'notifications' => [['id', 'type', 'title', 'message', 'read_at', 'created_at']],
                    'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                ],
            ])
            ->assertJsonCount(3, 'data.notifications');
    }

    public function test_unread_returns_only_unread_notifications(): void
    {
        $user = User::factory()->create();

        $this->createNotification($user);
        $this->createNotification($user, ['read_at' => now()]);

        $response = $this->actingAs($user)->getJson('/api/v1/notifications/unread');

        $response->assertOk()
            ->assertJsonCount(1, 'data.notifications');
    }

    public function test_unread_count_returns_count(): void
    {
        $user = User::factory()->create();

        $this->createNotification($user);
        $this->createNotification($user);
        $this->createNotification($user, ['read_at' => now()]);

        $response = $this->actingAs($user)->getJson('/api/v1/notifications/unread-count');

        $response->assertOk()
            ->assertJson(['data' => ['unread_count' => 2]]);
    }

    public function test_mark_as_read(): void
    {
        $user = User::factory()->create();
        $notification = $this->createNotification($user);

        $response = $this->actingAs($user)->patchJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertOk();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_as_unread(): void
    {
        $user = User::factory()->create();
        $notification = $this->createNotification($user, ['read_at' => now()]);

        $response = $this->actingAs($user)->patchJson("/api/v1/notifications/{$notification->id}/unread");

        $response->assertOk();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_as_read(): void
    {
        $user = User::factory()->create();

        $this->createNotification($user);
        $this->createNotification($user);

        $response = $this->actingAs($user)->patchJson('/api/v1/notifications/read-all');

        $response->assertOk()
            ->assertJson(['data' => ['marked_count' => 2]]);
        $this->assertEquals(0, $user->unreadNotifications()->count());
    }

    public function test_destroy_deletes_notification(): void
    {
        $user = User::factory()->create();
        $notification = $this->createNotification($user);

        $response = $this->actingAs($user)->deleteJson("/api/v1/notifications/{$notification->id}");

        $response->assertOk();
        $this->assertNull(DatabaseNotification::find($notification->id));
    }

    public function test_cannot_access_other_users_notification(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $notification = $this->createNotification($other);

        $response = $this->actingAs($user)->patchJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertNotFound();
    }

    public function test_unauthenticated_user_cannot_access_notifications(): void
    {
        $response = $this->getJson('/api/v1/notifications');

        $response->assertUnauthorized();
    }
}
```

**Step 4: Run the tests**

```bash
php artisan test --compact --filter=NotificationControllerTest
```
Expected: PASS

**Step 5: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

**Step 6: Commit**

```bash
git add app/Http/Controllers/Api/V1/NotificationController.php routes/api.php tests/Feature/Api/V1/NotificationControllerTest.php
git commit -m "refactor: NotificationController to use Laravel DatabaseNotification"
```

---

### Task 6: WawVideoLiked Notification + Observer

**Files:**
- Create: `app/Notifications/WawVideoLiked.php`
- Create: `app/Observers/WawVideoLikeObserver.php`
- Modify: `app/Providers/AppServiceProvider.php:36` (register observer)
- Test: `tests/Feature/Notifications/WawVideoLikedTest.php`

**Step 1: Create the notification class**

```bash
php artisan make:notification WawVideoLiked --no-interaction
```

Edit `app/Notifications/WawVideoLiked.php`:

```php
<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\WawVideo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class WawVideoLiked extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $liker,
        public WawVideo $video
    ) {
        $this->queue = 'notifications';
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'waw_video_liked',
            'title' => 'Someone liked your video',
            'message' => "{$this->liker->name} liked your video",
            'action_url' => "/waw/videos/{$this->video->id}",
            'actor' => [
                'id' => $this->liker->id,
                'name' => $this->liker->name,
                'avatar' => $this->liker->avatar,
            ],
            'subject' => [
                'id' => $this->video->id,
                'type' => 'waw_video',
            ],
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
```

**Step 2: Create the observer**

```bash
php artisan make:observer WawVideoLikeObserver --model=WawVideoLike --no-interaction
```

Edit `app/Observers/WawVideoLikeObserver.php`:

```php
<?php

namespace App\Observers;

use App\Models\WawVideoLike;
use App\Notifications\WawVideoLiked;

class WawVideoLikeObserver
{
    public function created(WawVideoLike $like): void
    {
        $video = $like->wawVideo;
        $videoOwner = $video->vendor;

        if ($like->user_id === $videoOwner->id) {
            return;
        }

        $videoOwner->notify(new WawVideoLiked($like->user, $video));
    }
}
```

**Step 3: Register the observer**

In `app/Providers/AppServiceProvider.php`, add after the Product observer line (line 36):

```php
\App\Models\WawVideoLike::observe(\App\Observers\WawVideoLikeObserver::class);
```

**Step 4: Write the test**

```bash
php artisan make:test Notifications/WawVideoLikedTest --phpunit --no-interaction
```

Edit `tests/Feature/Notifications/WawVideoLikedTest.php`:

```php
<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Models\WawVideo;
use App\Models\WawVideoLike;
use App\Notifications\WawVideoLiked;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WawVideoLikedTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_is_sent_when_video_is_liked(): void
    {
        Notification::fake();

        $vendor = User::factory()->create(['role' => 'vendor']);
        $video = WawVideo::factory()->create(['vendor_id' => $vendor->id]);
        $liker = User::factory()->create();

        WawVideoLike::create([
            'waw_video_id' => $video->id,
            'user_id' => $liker->id,
        ]);

        Notification::assertSentTo($vendor, WawVideoLiked::class, function ($notification) use ($liker, $video) {
            $data = $notification->toDatabase($notification->liker);
            return $data['type'] === 'waw_video_liked'
                && $data['actor']['id'] === $liker->id
                && $data['subject']['id'] === $video->id;
        });
    }

    public function test_notification_is_not_sent_when_owner_likes_own_video(): void
    {
        Notification::fake();

        $vendor = User::factory()->create(['role' => 'vendor']);
        $video = WawVideo::factory()->create(['vendor_id' => $vendor->id]);

        WawVideoLike::create([
            'waw_video_id' => $video->id,
            'user_id' => $vendor->id,
        ]);

        Notification::assertNotSentTo($vendor, WawVideoLiked::class);
    }

    public function test_notification_data_has_correct_shape(): void
    {
        $liker = User::factory()->create(['name' => 'Jane Doe']);
        $vendor = User::factory()->create(['role' => 'vendor']);
        $video = WawVideo::factory()->create(['vendor_id' => $vendor->id]);

        $notification = new WawVideoLiked($liker, $video);
        $data = $notification->toDatabase($vendor);

        $this->assertEquals('waw_video_liked', $data['type']);
        $this->assertEquals('Someone liked your video', $data['title']);
        $this->assertStringContainsString('Jane Doe', $data['message']);
        $this->assertArrayHasKey('actor', $data);
        $this->assertArrayHasKey('subject', $data);
        $this->assertArrayHasKey('action_url', $data);
    }
}
```

**Step 5: Run the test**

```bash
php artisan test --compact --filter=WawVideoLikedTest
```
Expected: PASS

**Step 6: Commit**

```bash
git add app/Notifications/WawVideoLiked.php app/Observers/WawVideoLikeObserver.php app/Providers/AppServiceProvider.php tests/Feature/Notifications/WawVideoLikedTest.php
git commit -m "feat: add WawVideoLiked notification with observer"
```

---

### Task 7: NewReview Notification + Observer

**Files:**
- Create: `app/Notifications/NewReview.php`
- Create: `app/Observers/ReviewObserver.php`
- Modify: `app/Providers/AppServiceProvider.php` (register observer)
- Test: `tests/Feature/Notifications/NewReviewTest.php`

**Step 1: Create the notification class**

```bash
php artisan make:notification NewReview --no-interaction
```

Edit `app/Notifications/NewReview.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Review;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class NewReview extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $reviewer,
        public Review $review
    ) {
        $this->queue = 'notifications';
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        $itemType = $this->review->item_type;

        return [
            'type' => 'new_review',
            'title' => 'New Review',
            'message' => "{$this->reviewer->name} left a {$this->review->rating}-star review on your {$itemType}",
            'action_url' => "/reviews/{$this->review->id}",
            'actor' => [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
                'avatar' => $this->reviewer->avatar,
            ],
            'subject' => [
                'id' => $this->review->id,
                'type' => 'review',
                'rating' => $this->review->rating,
                'reviewable_type' => $itemType,
                'reviewable_id' => $this->review->reviewable_id,
            ],
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
```

**Step 2: Create the observer**

```bash
php artisan make:observer ReviewObserver --model=Review --no-interaction
```

Edit `app/Observers/ReviewObserver.php`:

```php
<?php

namespace App\Observers;

use App\Models\Review;
use App\Notifications\NewReview;

class ReviewObserver
{
    public function created(Review $review): void
    {
        $reviewable = $review->reviewable;

        if (! $reviewable) {
            return;
        }

        // Get the vendor who owns the reviewed item (product or service)
        $vendor = $reviewable->vendor ?? null;

        if (! $vendor || $review->user_id === $vendor->id) {
            return;
        }

        $vendor->notify(new NewReview($review->user, $review));
    }
}
```

**Step 3: Register the observer**

In `app/Providers/AppServiceProvider.php`, add:

```php
\App\Models\Review::observe(\App\Observers\ReviewObserver::class);
```

**Step 4: Write the test**

```bash
php artisan make:test Notifications/NewReviewTest --phpunit --no-interaction
```

Edit `tests/Feature/Notifications/NewReviewTest.php`:

```php
<?php

namespace Tests\Feature\Notifications;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Notifications\NewReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NewReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_is_notified_when_product_is_reviewed(): void
    {
        Notification::fake();

        $vendor = User::factory()->create(['role' => 'vendor']);
        $product = Product::factory()->create(['vendor_id' => $vendor->id]);
        $reviewer = User::factory()->create();

        Review::factory()->create([
            'user_id' => $reviewer->id,
            'reviewable_type' => 'product',
            'reviewable_id' => $product->id,
            'rating' => 4.5,
        ]);

        Notification::assertSentTo($vendor, NewReview::class, function ($notification) use ($reviewer) {
            $data = $notification->toDatabase($notification->reviewer);
            return $data['type'] === 'new_review'
                && $data['actor']['id'] === $reviewer->id;
        });
    }

    public function test_notification_is_not_sent_when_vendor_reviews_own_product(): void
    {
        Notification::fake();

        $vendor = User::factory()->create(['role' => 'vendor']);
        $product = Product::factory()->create(['vendor_id' => $vendor->id]);

        Review::factory()->create([
            'user_id' => $vendor->id,
            'reviewable_type' => 'product',
            'reviewable_id' => $product->id,
        ]);

        Notification::assertNotSentTo($vendor, NewReview::class);
    }

    public function test_notification_data_has_correct_shape(): void
    {
        $reviewer = User::factory()->create(['name' => 'Alice']);
        $vendor = User::factory()->create(['role' => 'vendor']);
        $product = Product::factory()->create(['vendor_id' => $vendor->id]);
        $review = Review::factory()->create([
            'user_id' => $reviewer->id,
            'reviewable_type' => 'product',
            'reviewable_id' => $product->id,
            'rating' => 5.0,
        ]);

        $notification = new NewReview($reviewer, $review);
        $data = $notification->toDatabase($vendor);

        $this->assertEquals('new_review', $data['type']);
        $this->assertEquals('New Review', $data['title']);
        $this->assertStringContainsString('Alice', $data['message']);
        $this->assertStringContainsString('5', $data['message']);
        $this->assertArrayHasKey('actor', $data);
        $this->assertArrayHasKey('subject', $data);
    }
}
```

**Step 5: Run the test**

```bash
php artisan test --compact --filter=NewReviewTest
```
Expected: PASS

**Step 6: Commit**

```bash
git add app/Notifications/NewReview.php app/Observers/ReviewObserver.php app/Providers/AppServiceProvider.php tests/Feature/Notifications/NewReviewTest.php
git commit -m "feat: add NewReview notification with observer"
```

---

### Task 8: ReviewReplied Notification + Observer

**Files:**
- Create: `app/Notifications/ReviewReplied.php`
- Create: `app/Observers/ReviewReplyObserver.php`
- Modify: `app/Providers/AppServiceProvider.php` (register observer)
- Test: `tests/Feature/Notifications/ReviewRepliedTest.php`

**Step 1: Create the notification class**

```bash
php artisan make:notification ReviewReplied --no-interaction
```

Edit `app/Notifications/ReviewReplied.php`:

```php
<?php

namespace App\Notifications;

use App\Models\ReviewReply;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ReviewReplied extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $replier,
        public ReviewReply $reply
    ) {
        $this->queue = 'notifications';
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'review_replied',
            'title' => 'New Reply to Your Review',
            'message' => "{$this->replier->name} replied to your review",
            'action_url' => "/reviews/{$this->reply->review_id}",
            'actor' => [
                'id' => $this->replier->id,
                'name' => $this->replier->name,
                'avatar' => $this->replier->avatar,
            ],
            'subject' => [
                'id' => $this->reply->id,
                'type' => 'review_reply',
                'review_id' => $this->reply->review_id,
            ],
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
```

**Step 2: Create the observer**

```bash
php artisan make:observer ReviewReplyObserver --model=ReviewReply --no-interaction
```

Edit `app/Observers/ReviewReplyObserver.php`:

```php
<?php

namespace App\Observers;

use App\Models\ReviewReply;
use App\Notifications\ReviewReplied;

class ReviewReplyObserver
{
    public function created(ReviewReply $reply): void
    {
        $review = $reply->review;
        $reviewAuthor = $review->user;

        if ($reply->vendor_id === $reviewAuthor->id) {
            return;
        }

        $reviewAuthor->notify(new ReviewReplied($reply->vendor, $reply));
    }
}
```

**Step 3: Register the observer**

In `app/Providers/AppServiceProvider.php`, add:

```php
\App\Models\ReviewReply::observe(\App\Observers\ReviewReplyObserver::class);
```

**Step 4: Write the test**

```bash
php artisan make:test Notifications/ReviewRepliedTest --phpunit --no-interaction
```

Edit `tests/Feature/Notifications/ReviewRepliedTest.php`:

```php
<?php

namespace Tests\Feature\Notifications;

use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Models\User;
use App\Notifications\ReviewReplied;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReviewRepliedTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_author_is_notified_when_vendor_replies(): void
    {
        Notification::fake();

        $reviewer = User::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);
        $product = Product::factory()->create(['vendor_id' => $vendor->id]);
        $review = Review::factory()->create([
            'user_id' => $reviewer->id,
            'reviewable_type' => 'product',
            'reviewable_id' => $product->id,
        ]);

        ReviewReply::factory()->create([
            'review_id' => $review->id,
            'vendor_id' => $vendor->id,
        ]);

        Notification::assertSentTo($reviewer, ReviewReplied::class, function ($notification) use ($vendor) {
            $data = $notification->toDatabase($notification->replier);
            return $data['type'] === 'review_replied'
                && $data['actor']['id'] === $vendor->id;
        });
    }

    public function test_notification_is_not_sent_when_author_replies_to_own_review(): void
    {
        Notification::fake();

        $user = User::factory()->create(['role' => 'vendor']);
        $product = Product::factory()->create(['vendor_id' => $user->id]);
        $review = Review::factory()->create([
            'user_id' => $user->id,
            'reviewable_type' => 'product',
            'reviewable_id' => $product->id,
        ]);

        ReviewReply::factory()->create([
            'review_id' => $review->id,
            'vendor_id' => $user->id,
        ]);

        Notification::assertNotSentTo($user, ReviewReplied::class);
    }
}
```

**Step 5: Run the test**

```bash
php artisan test --compact --filter=ReviewRepliedTest
```
Expected: PASS

**Step 6: Commit**

```bash
git add app/Notifications/ReviewReplied.php app/Observers/ReviewReplyObserver.php app/Providers/AppServiceProvider.php tests/Feature/Notifications/ReviewRepliedTest.php
git commit -m "feat: add ReviewReplied notification with observer"
```

---

### Task 9: OrderStatusChanged Notification + Observer

**Files:**
- Create: `app/Notifications/OrderStatusChanged.php`
- Create: `app/Observers/OrderObserver.php`
- Modify: `app/Providers/AppServiceProvider.php` (register observer)
- Test: `tests/Feature/Notifications/OrderStatusChangedTest.php`

**Step 1: Create the notification class**

```bash
php artisan make:notification OrderStatusChanged --no-interaction
```

Edit `app/Notifications/OrderStatusChanged.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class OrderStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    /** @var array<string, string> */
    private const STATUS_TITLES = [
        'confirmed' => 'Order Confirmed',
        'processing' => 'Order Processing',
        'fulfilled' => 'Order Fulfilled',
        'shipped' => 'Order Shipped',
        'delivered' => 'Order Delivered',
        'refunded' => 'Order Refunded',
    ];

    /** @var array<string, string> */
    private const STATUS_MESSAGES = [
        'confirmed' => 'Your order #%s has been confirmed',
        'processing' => 'Your order #%s is being processed',
        'fulfilled' => 'Your order #%s has been fulfilled',
        'shipped' => 'Your order #%s has been shipped',
        'delivered' => 'Your order #%s has been delivered',
        'refunded' => 'Your order #%s has been refunded',
    ];

    public function __construct(
        public Order $order,
        public string $newStatus
    ) {
        $this->queue = 'notifications';
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'order_status_changed',
            'title' => self::STATUS_TITLES[$this->newStatus] ?? 'Order Updated',
            'message' => sprintf(
                self::STATUS_MESSAGES[$this->newStatus] ?? 'Your order #%s has been updated',
                $this->order->order_number
            ),
            'action_url' => "/orders/{$this->order->id}",
            'actor' => null,
            'subject' => [
                'id' => $this->order->id,
                'type' => 'order',
                'order_number' => $this->order->order_number,
                'status' => $this->newStatus,
            ],
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
```

**Step 2: Create the observer**

```bash
php artisan make:observer OrderObserver --model=Order --no-interaction
```

Edit `app/Observers/OrderObserver.php`:

```php
<?php

namespace App\Observers;

use App\Models\Order;
use App\Notifications\OrderStatusChanged;

class OrderObserver
{
    /** @var string[] */
    private const NOTIFIABLE_STATUSES = [
        'confirmed',
        'processing',
        'fulfilled',
        'shipped',
        'delivered',
        'refunded',
    ];

    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        $newStatus = $order->status;

        if (! in_array($newStatus, self::NOTIFIABLE_STATUSES)) {
            return;
        }

        $order->user->notify(new OrderStatusChanged($order, $newStatus));
    }
}
```

**Step 3: Register the observer**

In `app/Providers/AppServiceProvider.php`, add:

```php
\App\Models\Order::observe(\App\Observers\OrderObserver::class);
```

**Step 4: Write the test**

```bash
php artisan make:test Notifications/OrderStatusChangedTest --phpunit --no-interaction
```

Edit `tests/Feature/Notifications/OrderStatusChangedTest.php`:

```php
<?php

namespace Tests\Feature\Notifications;

use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderStatusChangedTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_is_notified_when_order_is_confirmed(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'status' => 'pending',
        ]);

        $order->update(['status' => 'confirmed']);

        Notification::assertSentTo($customer, OrderStatusChanged::class, function ($notification) {
            $data = $notification->toDatabase($notification->order->user);
            return $data['type'] === 'order_status_changed'
                && $data['subject']['status'] === 'confirmed';
        });
    }

    public function test_customer_is_notified_when_order_is_delivered(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'status' => 'shipped',
        ]);

        $order->update(['status' => 'delivered']);

        Notification::assertSentTo($customer, OrderStatusChanged::class);
    }

    public function test_no_notification_for_pending_status(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'status' => 'confirmed',
        ]);

        // pending is not in NOTIFIABLE_STATUSES, but the order already starts as something else
        // Test that non-status changes don't trigger
        $order->update(['special_instructions' => 'Leave at door']);

        Notification::assertNotSentTo($customer, OrderStatusChanged::class);
    }

    public function test_notification_data_includes_order_number(): void
    {
        $customer = User::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'order_number' => 'ORD-TEST123',
        ]);

        $notification = new OrderStatusChanged($order, 'shipped');
        $data = $notification->toDatabase($customer);

        $this->assertEquals('order_status_changed', $data['type']);
        $this->assertEquals('Order Shipped', $data['title']);
        $this->assertStringContainsString('ORD-TEST123', $data['message']);
        $this->assertEquals('shipped', $data['subject']['status']);
    }
}
```

**Step 5: Run the test**

```bash
php artisan test --compact --filter=OrderStatusChangedTest
```
Expected: PASS

**Step 6: Commit**

```bash
git add app/Notifications/OrderStatusChanged.php app/Observers/OrderObserver.php app/Providers/AppServiceProvider.php tests/Feature/Notifications/OrderStatusChangedTest.php
git commit -m "feat: add OrderStatusChanged notification with observer"
```

---

### Task 10: NewChatMessage Notification + Observer

**Files:**
- Create: `app/Notifications/NewChatMessage.php`
- Create: `app/Observers/MessageObserver.php`
- Modify: `app/Providers/AppServiceProvider.php` (register observer)
- Test: `tests/Feature/Notifications/NewChatMessageTest.php`

**Step 1: Create the notification class**

```bash
php artisan make:notification NewChatMessage --no-interaction
```

Edit `app/Notifications/NewChatMessage.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Message;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class NewChatMessage extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $sender,
        public Message $message
    ) {
        $this->queue = 'notifications';
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        $preview = \Illuminate\Support\Str::limit($this->message->body, 80);

        return [
            'type' => 'new_chat_message',
            'title' => 'New Message',
            'message' => "{$this->sender->name}: {$preview}",
            'action_url' => "/conversations/{$this->message->conversation_id}",
            'actor' => [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
                'avatar' => $this->sender->avatar,
            ],
            'subject' => [
                'id' => $this->message->id,
                'type' => 'message',
                'conversation_id' => $this->message->conversation_id,
            ],
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
```

**Step 2: Create the observer**

```bash
php artisan make:observer MessageObserver --model=Message --no-interaction
```

Edit `app/Observers/MessageObserver.php`:

```php
<?php

namespace App\Observers;

use App\Models\Message;
use App\Notifications\NewChatMessage;

class MessageObserver
{
    public function created(Message $message): void
    {
        $conversation = $message->conversation;
        $recipient = $conversation->getOtherParticipant($message->sender);

        if (! $recipient) {
            return;
        }

        $recipient->notify(new NewChatMessage($message->sender, $message));
    }
}
```

**Step 3: Register the observer**

In `app/Providers/AppServiceProvider.php`, add:

```php
\App\Models\Message::observe(\App\Observers\MessageObserver::class);
```

**Step 4: Write the test**

```bash
php artisan make:test Notifications/NewChatMessageTest --phpunit --no-interaction
```

Edit `tests/Feature/Notifications/NewChatMessageTest.php`:

```php
<?php

namespace Tests\Feature\Notifications;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NewChatMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipient_is_notified_when_message_is_sent(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $vendor = User::factory()->create(['role' => 'vendor']);
        $conversation = Conversation::findOrCreateBetween($customer, $vendor);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $customer->id,
            'body' => 'Hello, is this product available?',
            'type' => 'text',
        ]);

        Notification::assertSentTo($vendor, NewChatMessage::class, function ($notification) use ($customer) {
            $data = $notification->toDatabase($notification->sender);
            return $data['type'] === 'new_chat_message'
                && $data['actor']['id'] === $customer->id;
        });
    }

    public function test_notification_message_is_truncated(): void
    {
        $sender = User::factory()->create(['name' => 'Bob']);
        $vendor = User::factory()->create(['role' => 'vendor']);
        $conversation = Conversation::findOrCreateBetween($sender, $vendor);

        $longMessage = str_repeat('A', 200);
        $message = Message::make([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'body' => $longMessage,
            'type' => 'text',
        ]);

        $notification = new NewChatMessage($sender, $message);
        $data = $notification->toDatabase($vendor);

        $this->assertLessThanOrEqual(100, strlen($data['message']));
        $this->assertStringContainsString('Bob', $data['message']);
    }
}
```

**Step 5: Run the test**

```bash
php artisan test --compact --filter=NewChatMessageTest
```
Expected: PASS

**Step 6: Commit**

```bash
git add app/Notifications/NewChatMessage.php app/Observers/MessageObserver.php app/Providers/AppServiceProvider.php tests/Feature/Notifications/NewChatMessageTest.php
git commit -m "feat: add NewChatMessage notification with observer"
```

---

### Task 11: Update VendorApprovalNotification

**Files:**
- Modify: `app/Notifications/VendorApprovalNotification.php` (add database channel, add `toDatabase()`)

**Step 1: Add database channel to VendorApprovalNotification**

Edit `app/Notifications/VendorApprovalNotification.php`:

Update `via()` method to include `'database'`:

```php
public function via(object $notifiable): array
{
    return ['database', 'mail', BroadcastChannel::class];
}
```

Add `toDatabase()` method:

```php
public function toDatabase(object $notifiable): array
{
    $isApproved = $this->status === 'approved';

    return [
        'type' => 'vendor_' . $this->status,
        'title' => $isApproved ? 'Application Approved' : 'Application Rejected',
        'message' => $isApproved
            ? 'Your vendor application has been approved.'
            : 'Your vendor application has been rejected.',
        'action_url' => '/dashboard/vendor-applications/' . $this->vendorApplication->id,
        'actor' => null,
        'subject' => [
            'id' => $this->vendorApplication->id,
            'type' => 'vendor_application',
            'status' => $this->status,
        ],
    ];
}
```

**Step 2: Run existing vendor tests**

```bash
php artisan test --compact --filter=Vendor
```
Expected: PASS

**Step 3: Commit**

```bash
git add app/Notifications/VendorApprovalNotification.php
git commit -m "feat: add database channel to VendorApprovalNotification"
```

---

### Task 12: Update Frontend

**Files:**
- Modify: `resources/js/lib/notifications/api.ts` (update Notification interface)
- Modify: `resources/js/hooks/useEchoNotifications.ts` (listen on user channel)

**Step 1: Update Notification interface**

Edit `resources/js/lib/notifications/api.ts`, replace the `Notification` interface:

```typescript
export interface Notification {
    id: string;
    type: string;
    title: string;
    message: string;
    action_url: string | null;
    actor: {
        id: number;
        name: string;
        avatar: string | null;
    } | null;
    data: Record<string, unknown>;
    read_at: string | null;
    created_at: string;
    updated_at: string;
}
```

**Step 2: Rewrite useEchoNotifications hook**

Replace `resources/js/hooks/useEchoNotifications.ts` with:

```typescript
import { useEffect } from 'react';
import echo from '@/lib/echo';
import { useNotifications } from '@/hooks/useNotifications';
import { showNotificationToast } from '@/components/notifications';
import type { Notification } from '@/lib/notifications/api';
import { usePage } from '@inertiajs/react';

const isDevelopment = import.meta.env.DEV;

interface BroadcastNotification {
    id: string;
    type: string;
    title: string;
    message: string;
    action_url?: string;
    actor?: {
        id: number;
        name: string;
        avatar: string | null;
    };
    [key: string]: unknown;
}

export function useEchoNotifications() {
    const { addNotification, fetchUnreadCount } = useNotifications();
    const { auth } = usePage<{ auth: { user: { id: number } } }>().props;

    useEffect(() => {
        if (!auth?.user?.id) {
            return;
        }

        const userId = auth.user.id;

        if (isDevelopment) {
            console.log(
                `[useEchoNotifications] Subscribing to user.${userId} channel...`,
            );
        }

        const channel = echo.private(`user.${userId}`);

        channel.notification((data: BroadcastNotification) => {
            if (isDevelopment) {
                console.log('[useEchoNotifications] Notification received:', data);
            }

            const notification: Notification = {
                id: data.id,
                type: data.type ?? 'unknown',
                title: data.title ?? '',
                message: data.message ?? '',
                action_url: data.action_url ?? null,
                actor: data.actor ?? null,
                data: data,
                read_at: null,
                created_at: new Date().toISOString(),
                updated_at: new Date().toISOString(),
            };

            addNotification(notification);
            showNotificationToast(notification);
            fetchUnreadCount();
        });

        channel.error((error: unknown) => {
            if (isDevelopment) {
                console.error('[useEchoNotifications] Channel error:', error);
            }
        });

        return () => {
            if (isDevelopment) {
                console.log(
                    `[useEchoNotifications] Leaving user.${userId} channel`,
                );
            }
            echo.leaveChannel(`private-user.${userId}`);
        };
    }, [auth?.user?.id, addNotification, fetchUnreadCount]);
}
```

**Step 3: Commit**

```bash
git add resources/js/lib/notifications/api.ts resources/js/hooks/useEchoNotifications.ts
git commit -m "feat: update frontend to use Laravel notification broadcasting"
```

---

### Task 13: Final Integration + Cleanup

**Files:**
- Verify all tests pass
- Run Pint formatter
- Clean up any remaining references to deleted model

**Step 1: Search for remaining references to old Notification model**

```bash
grep -rn "App\\\\Models\\\\Notification" --include="*.php" app/ tests/ routes/ config/
grep -rn "use App\\\\Models\\\\Notification" --include="*.php" app/ tests/
```

Fix any remaining references found.

**Step 2: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

**Step 3: Run all notification-related tests**

```bash
php artisan test --compact --filter=Notification
```
Expected: ALL PASS

**Step 4: Ask user if they want to run the full test suite**

```bash
php artisan test --compact
```

**Step 5: Final commit**

```bash
git add -A
git commit -m "chore: cleanup remaining references, run pint"
```
