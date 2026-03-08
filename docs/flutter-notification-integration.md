# Surprise Moi - Notification System Integration Guide (Flutter)

This document describes how to consume the backend notification system from the Flutter mobile app. It covers the REST API for managing notifications and the WebSocket connection for receiving real-time notifications.

---

## Table of Contents

1. [Authentication](#authentication)
2. [REST API Endpoints](#rest-api-endpoints)
3. [Notification Object Shape](#notification-object-shape)
4. [Notification Types](#notification-types)
5. [Real-Time Notifications (WebSocket)](#real-time-notifications-websocket)
6. [Recommended Flutter Packages](#recommended-flutter-packages)
7. [Implementation Examples](#implementation-examples)

---

## Authentication

All notification endpoints require authentication via **Laravel Sanctum**. Include the auth token in every request:

```
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

For WebSocket connections, authentication happens via a POST to `/broadcasting/auth` with the Sanctum token (see WebSocket section below).

---

## REST API Endpoints

**Base URL:** `https://dashboard.surprisemoi.com/api/v1`

### GET /notifications

Fetch paginated notifications for the authenticated user.

**Query Parameters:**
| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `page` | int | 1 | Page number |
| `per_page` | int | 20 | Items per page (max 100) |

**Response (200):**
```json
{
  "success": true,
  "data": {
    "notifications": [
      {
        "id": "9f1a2b3c-4d5e-6f7a-8b9c-0d1e2f3a4b5c",
        "type": "waw_video_liked",
        "title": "Someone liked your video",
        "message": "John Doe liked your video",
        "action_url": "/waw/videos/42",
        "actor": {
          "id": 7,
          "name": "John Doe",
          "avatar": "https://cdn.surprisemoi.com/avatars/7.jpg"
        },
        "data": {
          "type": "waw_video_liked",
          "title": "Someone liked your video",
          "message": "John Doe liked your video",
          "action_url": "/waw/videos/42",
          "actor": { "id": 7, "name": "John Doe", "avatar": "..." },
          "subject": { "id": 42, "type": "waw_video" }
        },
        "read_at": null,
        "created_at": "2026-03-07T14:30:00+00:00",
        "updated_at": "2026-03-07T14:30:00+00:00"
      }
    ],
    "meta": {
      "current_page": 1,
      "last_page": 3,
      "per_page": 20,
      "total": 55
    }
  }
}
```

### GET /notifications/unread

Fetch all unread notifications (not paginated).

**Response (200):**
```json
{
  "success": true,
  "data": {
    "notifications": [ ... ]
  }
}
```

### GET /notifications/unread-count

Get the count of unread notifications. Use this for badge numbers.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "unread_count": 12
  }
}
```

### PATCH /notifications/{id}/read

Mark a single notification as read.

**Response (200):**
```json
{
  "success": true,
  "message": "Notification marked as read"
}
```

### PATCH /notifications/{id}/unread

Mark a single notification as unread.

**Response (200):**
```json
{
  "success": true,
  "message": "Notification marked as unread"
}
```

### PATCH /notifications/read-all

Mark all notifications as read.

**Response (200):**
```json
{
  "success": true,
  "message": "All notifications marked as read",
  "data": {
    "marked_count": 5
  }
}
```

### DELETE /notifications/{id}

Delete a notification.

**Response (200):**
```json
{
  "success": true,
  "message": "Notification deleted"
}
```

### Error Responses

| Status | Meaning |
|--------|---------|
| 401 | Unauthenticated - token missing or invalid |
| 404 | Notification not found or belongs to another user |

---

## Notification Object Shape

Every notification returned by the API or received via WebSocket has this shape:

```dart
class AppNotification {
  final String id;           // UUID
  final String type;         // e.g., "waw_video_liked", "new_review", "order_status_changed"
  final String title;        // Human-readable title
  final String message;      // Human-readable description
  final String? actionUrl;   // Deep link path (e.g., "/waw/videos/42", "/orders/15")
  final Actor? actor;        // The user who triggered the notification (null for system notifications)
  final Map<String, dynamic> data; // Full notification payload (includes subject details)
  final String? readAt;      // ISO 8601 timestamp, null if unread
  final String createdAt;    // ISO 8601 timestamp
  final String updatedAt;    // ISO 8601 timestamp
}

class Actor {
  final int id;
  final String name;
  final String? avatar;      // Full URL to avatar image, or null
}
```

---

## Notification Types

These are the notification types currently supported:

| `type` value | Title | Recipient | Trigger | `actor` | `data.subject` |
|---|---|---|---|---|---|
| `waw_video_liked` | "Someone liked your video" | Video owner (vendor) | User likes a Waw video | The liker | `{ id, type: "waw_video" }` |
| `new_review` | "New Review" | Vendor | Customer reviews a product/service | The reviewer | `{ id, type: "review", rating, reviewable_type, reviewable_id }` |
| `review_replied` | "New Reply to Your Review" | Review author | Vendor replies to a review | The vendor | `{ id, type: "review_reply", review_id }` |
| `order_status_changed` | "Order Confirmed" / "Order Shipped" / etc. | Customer | Order status updates | `null` | `{ id, type: "order", order_number, status }` |
| `new_chat_message` | "New Message" | Chat recipient | New message in conversation | The sender | `{ id, type: "message", conversation_id }` |
| `vendor_approved` | "Application Approved" | Vendor | Admin approves vendor app | `null` | `{ id, type: "vendor_application", status }` |
| `vendor_rejected` | "Application Rejected" | Vendor | Admin rejects vendor app | `null` | `{ id, type: "vendor_application", status }` |

### Using `action_url` for Deep Links

The `action_url` field provides a relative path that maps to in-app screens:

| Pattern | Screen |
|---|---|
| `/waw/videos/{id}` | Waw video detail |
| `/reviews/{id}` | Review detail |
| `/orders/{id}` | Order detail |
| `/conversations/{id}` | Chat conversation |
| `/dashboard/vendor-applications/{id}` | Vendor application status |

Parse these paths to navigate to the appropriate screen in your Flutter app.

---

## Real-Time Notifications (WebSocket)

The backend uses **Laravel Reverb** (a Pusher-compatible WebSocket server) for real-time notification delivery. When a notification is created, it is automatically broadcast to the user's private channel.

### Connection Details

| Setting | Value |
|---|---|
| **Protocol** | Pusher-compatible WebSocket |
| **Host** | `dashboard.surprisemoi.com` |
| **Port** | `443` |
| **Scheme** | `wss` (TLS) |
| **App Key** | Provided separately (env variable) |
| **Auth Endpoint** | `POST https://dashboard.surprisemoi.com/broadcasting/auth` |

### Channel

Subscribe to the **private** channel for the authenticated user:

```
Channel: private-user.{userId}
```

For example, if the user's ID is `42`, subscribe to `private-user.42`.

### Authentication

Private channels require authentication. When connecting, the WebSocket client will make a POST request to the auth endpoint:

```
POST https://dashboard.surprisemoi.com/broadcasting/auth
Headers:
  Authorization: Bearer <sanctum_token>
  Content-Type: application/x-www-form-urlencoded
Body:
  socket_id=<socket_id>&channel_name=private-user.42
```

The server responds with an auth signature that the WebSocket library uses automatically.

### Event Name

Listen for this event on the private channel:

```
Event: Illuminate\Notifications\Events\BroadcastNotificationCreated
```

When using Pusher-compatible clients, this is typically received as:
```
.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated
```

Some Pusher clients also support Laravel's `.notification()` shorthand which listens for this event automatically.

### Event Payload

The WebSocket event payload has the same shape as the `data` field in the REST API notification object:

```json
{
  "id": "9f1a2b3c-4d5e-6f7a-8b9c-0d1e2f3a4b5c",
  "type": "waw_video_liked",
  "title": "Someone liked your video",
  "message": "John Doe liked your video",
  "action_url": "/waw/videos/42",
  "actor": {
    "id": 7,
    "name": "John Doe",
    "avatar": "https://cdn.surprisemoi.com/avatars/7.jpg"
  },
  "subject": {
    "id": 42,
    "type": "waw_video"
  }
}
```

---

## Recommended Flutter Packages

| Package | Purpose |
|---|---|
| [`pusher_channels_flutter`](https://pub.dev/packages/pusher_channels_flutter) | WebSocket connection (Pusher-compatible, works with Reverb) |
| [`dio`](https://pub.dev/packages/dio) | HTTP client for REST API calls |
| [`flutter_local_notifications`](https://pub.dev/packages/flutter_local_notifications) | Display local notification popups (in-app alerts) |

---

## Implementation Examples

### 1. Dart Model

```dart
class AppNotification {
  final String id;
  final String type;
  final String title;
  final String message;
  final String? actionUrl;
  final Actor? actor;
  final Map<String, dynamic> data;
  final DateTime? readAt;
  final DateTime createdAt;
  final DateTime updatedAt;

  AppNotification({
    required this.id,
    required this.type,
    required this.title,
    required this.message,
    this.actionUrl,
    this.actor,
    required this.data,
    this.readAt,
    required this.createdAt,
    required this.updatedAt,
  });

  bool get isUnread => readAt == null;

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    return AppNotification(
      id: json['id'],
      type: json['type'] ?? 'unknown',
      title: json['title'] ?? '',
      message: json['message'] ?? '',
      actionUrl: json['action_url'],
      actor: json['actor'] != null ? Actor.fromJson(json['actor']) : null,
      data: json['data'] ?? {},
      readAt: json['read_at'] != null ? DateTime.parse(json['read_at']) : null,
      createdAt: DateTime.parse(json['created_at']),
      updatedAt: DateTime.parse(json['updated_at']),
    );
  }
}

class Actor {
  final int id;
  final String name;
  final String? avatar;

  Actor({required this.id, required this.name, this.avatar});

  factory Actor.fromJson(Map<String, dynamic> json) {
    return Actor(
      id: json['id'],
      name: json['name'],
      avatar: json['avatar'],
    );
  }
}
```

### 2. Notification API Service

```dart
class NotificationApiService {
  final Dio _dio;

  NotificationApiService(this._dio);

  Future<List<AppNotification>> getNotifications({int page = 1, int perPage = 20}) async {
    final response = await _dio.get('/api/v1/notifications', queryParameters: {
      'page': page,
      'per_page': perPage,
    });
    final list = response.data['data']['notifications'] as List;
    return list.map((json) => AppNotification.fromJson(json)).toList();
  }

  Future<int> getUnreadCount() async {
    final response = await _dio.get('/api/v1/notifications/unread-count');
    return response.data['data']['unread_count'];
  }

  Future<void> markAsRead(String notificationId) async {
    await _dio.patch('/api/v1/notifications/$notificationId/read');
  }

  Future<void> markAllAsRead() async {
    await _dio.patch('/api/v1/notifications/read-all');
  }

  Future<void> delete(String notificationId) async {
    await _dio.delete('/api/v1/notifications/$notificationId');
  }
}
```

### 3. WebSocket Connection (using pusher_channels_flutter)

```dart
import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';

class NotificationWebSocket {
  late PusherChannelsFlutter _pusher;

  Future<void> connect({
    required String appKey,
    required String authToken,
    required int userId,
    required void Function(Map<String, dynamic> data) onNotification,
  }) async {
    _pusher = PusherChannelsFlutter.getInstance();

    await _pusher.init(
      apiKey: appKey,
      cluster: '', // Not used with custom host
      host: 'dashboard.surprisemoi.com',
      wsPort: 443,
      wssPort: 443,
      useTLS: true,
      onAuthorizer: (String channelName, String socketId, dynamic options) async {
        // Authenticate the private channel
        final dio = Dio();
        final response = await dio.post(
          'https://dashboard.surprisemoi.com/broadcasting/auth',
          data: {
            'socket_id': socketId,
            'channel_name': channelName,
          },
          options: Options(
            headers: {
              'Authorization': 'Bearer $authToken',
              'Accept': 'application/json',
            },
            contentType: Headers.formUrlEncodedContentType,
          ),
        );
        return response.data;
      },
    );

    await _pusher.connect();

    // Subscribe to the user's private notification channel
    await _pusher.subscribe(
      channelName: 'private-user.$userId',
      onEvent: (event) {
        if (event.eventName ==
            'Illuminate\\Notifications\\Events\\BroadcastNotificationCreated') {
          final data = jsonDecode(event.data ?? '{}');
          onNotification(data);
        }
      },
    );
  }

  Future<void> disconnect() async {
    await _pusher.disconnect();
  }
}
```

### 4. Usage in a Provider/Bloc

```dart
// On app start (after login):
final ws = NotificationWebSocket();
await ws.connect(
  appKey: 'YOUR_REVERB_APP_KEY',
  authToken: userToken,
  userId: currentUser.id,
  onNotification: (data) {
    // 1. Add to local notification list
    final notification = AppNotification.fromJson({
      'id': data['id'],
      'type': data['type'] ?? 'unknown',
      'title': data['title'] ?? '',
      'message': data['message'] ?? '',
      'action_url': data['action_url'],
      'actor': data['actor'],
      'data': data,
      'read_at': null,
      'created_at': DateTime.now().toIso8601String(),
      'updated_at': DateTime.now().toIso8601String(),
    });

    // 2. Update unread badge count
    notificationState.addNotification(notification);

    // 3. Show local notification popup
    showLocalNotification(
      title: notification.title,
      body: notification.message,
    );

    // 4. Navigate on tap (use action_url for deep linking)
  },
);
```

---

## Important Notes

1. **Notification IDs are UUIDs** (strings, not integers). Use `String` type in Dart.
2. **`read_at` is null for unread notifications.** Check `notification.isUnread` to show the unread indicator.
3. **`actor` is null for system notifications** (order status changes, vendor approval). Always null-check before accessing actor fields.
4. **`action_url` is a relative path**, not a full URL. Map these paths to your app's route/screen system.
5. **WebSocket reconnection**: Implement reconnection logic with exponential backoff in case the connection drops.
6. **Badge count**: Fetch `/notifications/unread-count` on app launch and update locally when WebSocket events arrive.
7. **Pagination**: The `/notifications` endpoint supports pagination. Implement infinite scroll by incrementing the `page` parameter.
