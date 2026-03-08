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

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'notifications' => [
                        '*' => [
                            'id',
                            'type',
                            'title',
                            'message',
                            'data',
                            'read_at',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'meta' => [
                        'current_page',
                        'last_page',
                        'per_page',
                        'total',
                    ],
                ],
            ])
            ->assertJsonCount(3, 'data.notifications');
    }

    public function test_unread_returns_only_unread_notifications(): void
    {
        $user = User::factory()->create();

        $this->createNotification($user);
        $this->createNotification($user);
        $this->createNotification($user, ['read_at' => now()]);

        $response = $this->actingAs($user)->getJson('/api/v1/notifications/unread');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonCount(2, 'data.notifications');
    }

    public function test_unread_count_returns_count(): void
    {
        $user = User::factory()->create();

        $this->createNotification($user);
        $this->createNotification($user);
        $this->createNotification($user, ['read_at' => now()]);

        $response = $this->actingAs($user)->getJson('/api/v1/notifications/unread-count');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'unread_count' => 2,
                ],
            ]);
    }

    public function test_mark_as_read(): void
    {
        $user = User::factory()->create();
        $notification = $this->createNotification($user);

        $response = $this->actingAs($user)->patchJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Notification marked as read',
            ]);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_as_unread(): void
    {
        $user = User::factory()->create();
        $notification = $this->createNotification($user, ['read_at' => now()]);

        $response = $this->actingAs($user)->patchJson("/api/v1/notifications/{$notification->id}/unread");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Notification marked as unread',
            ]);

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_as_read(): void
    {
        $user = User::factory()->create();

        $this->createNotification($user);
        $this->createNotification($user);
        $this->createNotification($user);

        $response = $this->actingAs($user)->patchJson('/api/v1/notifications/read-all');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'All notifications marked as read',
                'data' => [
                    'marked_count' => 3,
                ],
            ]);

        $this->assertEquals(0, $user->unreadNotifications()->count());
    }

    public function test_destroy_deletes_notification(): void
    {
        $user = User::factory()->create();
        $notification = $this->createNotification($user);

        $response = $this->actingAs($user)->deleteJson("/api/v1/notifications/{$notification->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Notification deleted',
            ]);

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_cannot_access_other_users_notification(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $notification = $this->createNotification($otherUser);

        $response = $this->actingAs($user)->patchJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_access_notifications(): void
    {
        $response = $this->getJson('/api/v1/notifications');

        $response->assertStatus(401);
    }
}
