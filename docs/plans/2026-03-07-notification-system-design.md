# Notification System Design

**Date**: 2026-03-07
**Status**: Approved
**Branch**: feat/notification-system

## Context

Surprise Moi has a hand-rolled notification system (custom model, service, controller) that bypasses Laravel's built-in notification framework. The User model imports the `Notifiable` trait but overrides its methods with custom ones, creating a conflict. The current system only supports 3 notification types (vendor approval, chat, system) with no multi-channel capability.

## Decision

Migrate to Laravel's built-in notification system (`Illuminate\Notifications`). This is the industry standard for Laravel applications and provides multi-channel dispatch, queuing, and broadcasting out of the box.

## Phase 1 Notification Types

| # | Notification Class | Recipient | Trigger | Channels |
|---|---|---|---|---|
| 1 | `OrderStatusChanged` | Customer | Order status updated (confirmed/shipped/delivered/cancelled) | database, broadcast |
| 2 | `NewReview` | Vendor | Customer reviews their product/service | database, broadcast |
| 3 | `WawVideoLiked` | Video owner | Someone likes their Waw video | database, broadcast |
| 4 | `ReviewReplied` | Review author | Someone replies to their review | database, broadcast |
| 5 | `NewChatMessage` | Recipient | Someone sends a chat message | database, broadcast |

## Architecture

```
Trigger (Observer / Event Listener)
    |
    v
$user->notify(new SomeNotification($actor, $subject))
    |
    v
Notification Class (app/Notifications/)
    |-- via(): ['database', 'broadcast']
    |-- toDatabase(): returns array stored in notifications.data JSON
    |-- toBroadcast(): returns BroadcastMessage for Reverb
    |
    v
+------------------+     +-----------------------+
|  Database Store  |     |  Reverb WebSocket      |
|  (notifications  |     |  (real-time push to    |
|   table)         |     |   user.{id} channel)   |
+------------------+     +-----------------------+
```

### Dispatching Strategy

Use **Laravel Observers** on models to trigger notifications:

- `WawVideoLikeObserver::created()` -> notify video owner
- `ReviewObserver::created()` -> notify vendor
- `ReviewReplyObserver::created()` -> notify review author
- `OrderObserver::updated()` (status change) -> notify customer
- `MessageObserver::created()` -> notify recipient (enhance existing)

### Queuing

All notification classes implement `ShouldQueue` with the `notifications` queue. Processed by Horizon.

### Broadcasting

Use existing `user.{userId}` private channel (already authorized in `routes/channels.php`). Frontend listens for Laravel's `Illuminate\Notifications\Events\BroadcastNotificationCreated` event.

### Data Shape

Each notification's `toDatabase()` returns a consistent shape:

```php
[
    'title' => 'Someone liked your video',
    'message' => 'John Doe liked your video "Beach Day"',
    'type' => 'waw_video_liked',
    'action_url' => '/waw/videos/123',
    'actor' => [
        'id' => 1,
        'name' => 'John Doe',
        'avatar' => 'https://...',
    ],
    'subject' => [
        'id' => 123,
        'type' => 'waw_video',
    ],
]
```

## Migration Strategy

### Database Migration

Alter the existing `notifications` table to match Laravel's schema:

1. Add `notifiable_type` (string) and `notifiable_id` (unsigned big integer)
2. Populate `notifiable_type` = `App\Models\User`, `notifiable_id` = `user_id` for existing rows
3. Merge `title` and `message` into `data` JSON for existing rows
4. Rename `type` values to fully qualified class names (Laravel convention)
5. Drop `user_id`, `title`, `message` columns
6. Add composite index on `(notifiable_type, notifiable_id)`

### Code Changes

1. **Delete** `app/Models/Notification.php` (custom model)
2. **Clean User model**: Remove overridden `notifications()`, `unreadNotifications()`, `getUnreadNotificationsCount()` methods. The `Notifiable` trait provides these.
3. **Create notification classes** in `app/Notifications/`:
   - `OrderStatusChanged`
   - `NewReview`
   - `WawVideoLiked`
   - `ReviewReplied`
   - `NewChatMessage`
4. **Create observers** in `app/Observers/`:
   - `WawVideoLikeObserver`
   - `ReviewObserver`
   - `ReviewReplyObserver`
   - `OrderObserver`
   - `MessageObserver` (enhance existing if present)
5. **Refactor `NotificationService`**: Simplify to use `$user->notify()` dispatch. Keep read/unread/delete methods using `$user->notifications()` from the trait.
6. **Refactor `NotificationController`**: Use `$user->notifications` from trait + `NotificationResource` for consistent API responses.
7. **Create `NotificationResource`**: API Resource that extracts `title`, `message`, `type`, `action_url`, `actor` from the `data` JSON column, maintaining the same frontend API contract.
8. **Update frontend `useEchoNotifications`**: Listen on private `user.{userId}` channel for `.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated` event.

### Self-Notification Guard

Notifications should NOT be sent when the actor is the same as the recipient (e.g., a user liking their own video). This guard is applied in each Observer before dispatching.

## API Contract (Preserved)

The existing REST endpoints remain the same:

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/notifications` | Paginated list |
| GET | `/api/v1/notifications/unread` | Unread only |
| GET | `/api/v1/notifications/unread-count` | Count |
| PATCH | `/api/v1/notifications/{id}/read` | Mark read |
| PATCH | `/api/v1/notifications/{id}/unread` | Mark unread |
| PATCH | `/api/v1/notifications/read-all` | Mark all read |
| DELETE | `/api/v1/notifications/{id}` | Delete |

Response shape via `NotificationResource`:

```json
{
    "id": "uuid",
    "type": "waw_video_liked",
    "title": "Someone liked your video",
    "message": "John Doe liked your video",
    "action_url": "/waw/videos/123",
    "actor": { "id": 1, "name": "John Doe", "avatar": "..." },
    "read_at": null,
    "created_at": "2026-03-07T12:00:00Z"
}
```

## Testing Strategy

- Feature test per notification class (dispatched, stored in DB, correct data shape)
- Feature test per observer (trigger creates notification, self-notification guard)
- Feature test for controller endpoints (index, unread, mark read, delete)
- Broadcast assertion tests using `Notification::fake()` and `Event::fake()`

## Out of Scope (Future Phases)

- User notification preferences (opt-in/opt-out per type)
- Email notification channel
- Push notification channel (FCM/APNs)
- Notification aggregation ("X and 5 others liked your video")
- Notification retention/cleanup policy
- System-wide announcements
