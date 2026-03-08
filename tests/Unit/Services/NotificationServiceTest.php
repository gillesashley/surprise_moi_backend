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
