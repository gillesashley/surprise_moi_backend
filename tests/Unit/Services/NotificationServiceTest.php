<?php

namespace Tests\Unit\Services;

use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_creates_notification_for_user(): void
    {
        $user = User::factory()->create();

        $notification = $this->service->createNotification(
            $user,
            'test_type',
            'Test Title',
            'Test message',
            ['key' => 'value']
        );

        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertEquals('test_type', $notification->type);
        $this->assertEquals('Test Title', $notification->title);
        $this->assertEquals('Test message', $notification->message);
        $this->assertEquals(['key' => 'value'], $notification->data);
        $this->assertEquals($user->id, $notification->user_id);
    }

    public function test_gets_notifications_for_user(): void
    {
        $user = User::factory()->create();
        
        Notification::factory()->count(3)->create(['user_id' => $user->id]);
        Notification::factory()->count(2)->create();

        $notifications = $this->service->getNotificationsForUser($user);

        $this->assertCount(3, $notifications);
    }

    public function test_gets_unread_notifications(): void
    {
        $user = User::factory()->create();
        
        Notification::factory()->count(2)->create([
            'user_id' => $user->id,
            'read_at' => null,
        ]);
        Notification::factory()->count(3)->create([
            'user_id' => $user->id,
            'read_at' => now(),
        ]);

        $unread = $this->service->getUnreadNotificationsForUser($user);

        $this->assertCount(2, $unread);
    }

    public function test_gets_unread_count(): void
    {
        $user = User::factory()->create();
        
        Notification::factory()->count(5)->create([
            'user_id' => $user->id,
            'read_at' => null,
        ]);
        Notification::factory()->count(3)->create([
            'user_id' => $user->id,
            'read_at' => now(),
        ]);

        $count = $this->service->getUnreadCount($user);

        $this->assertEquals(5, $count);
    }

    public function test_marks_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'read_at' => null,
        ]);

        $this->service->markAsRead($notification);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_marks_notification_as_unread(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'read_at' => now(),
        ]);

        $this->service->markAsUnread($notification);

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_marks_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        
        Notification::factory()->count(3)->create([
            'user_id' => $user->id,
            'read_at' => null,
        ]);
        Notification::factory()->count(2)->create([
            'user_id' => $user->id,
            'read_at' => now(),
        ]);

        $count = $this->service->markAllAsRead($user);

        $this->assertEquals(3, $count);
        $this->assertEquals(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_deletes_notification(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->create([
            'user_id' => $user->id,
        ]);

        $result = $this->service->deleteNotification($notification);

        $this->assertTrue($result);
        $this->assertNull($notification->fresh());
    }

    public function test_creates_vendor_notification(): void
    {
        $user = User::factory()->create();

        $notification = $this->service->createVendorNotification(
            $user->id,
            'Test Vendor',
            'submitted',
            ['application_id' => 1]
        );

        $this->assertEquals('vendor_submitted', $notification->type);
        $this->assertEquals('New Vendor Application', $notification->title);
    }

    public function test_creates_chat_notification(): void
    {
        $user = User::factory()->create();

        $notification = $this->service->createChatNotification(
            $user->id,
            'John Doe',
            'Hello there!'
        );

        $this->assertEquals('chat_message', $notification->type);
        $this->assertEquals('New Message', $notification->title);
    }

    public function test_creates_system_notification(): void
    {
        $user = User::factory()->create();

        $notification = $this->service->createSystemNotification(
            $user->id,
            'System Update',
            'A system update is available'
        );

        $this->assertEquals('system', $notification->type);
        $this->assertEquals('System Update', $notification->title);
    }
}
