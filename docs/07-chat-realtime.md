# Chat & Real-time Features

This document covers the real-time messaging system built with Laravel Reverb and WebSocket broadcasting.

## Overview

The chat system enables direct communication between customers and vendors. Key features:

- One-to-one conversations between customer and vendor
- Real-time message delivery via WebSockets
- Typing indicators
- Read receipts
- File attachments
- Unread message counters

## Technology

**Laravel Reverb** - Laravel's first-party WebSocket server that handles broadcasting events to connected clients.

**Broadcasting Driver**: Reverb (configured in `config/broadcasting.php`)

## Core Models

### Conversation

**Location**: `app/Models/Conversation.php`

A conversation between a customer and a vendor.

#### Attributes

```php
[
    'customer_id',              // Customer user ID
    'vendor_id',                // Vendor user ID
    'last_message',             // Preview of last message
    'last_message_at',          // Timestamp of last message
    'last_message_sender_id',   // Who sent the last message
    'customer_unread_count',    // Unread count for customer
    'vendor_unread_count',      // Unread count for vendor
]
```

#### Relationships

```php
$conversation->customer()            // BelongsTo User
$conversation->vendor()              // BelongsTo User
$conversation->lastMessageSender()   // BelongsTo User
$conversation->messages()            // HasMany Message
```

#### Helper Methods

```php
// Get the other participant
$otherUser = $conversation->getOtherParticipant($currentUser);

// Check if user is a participant
$isParticipant = $conversation->hasParticipant($user);

// Get unread count for specific user
$unreadCount = $conversation->getUnreadCountFor($user);

// Mark all messages as read
$conversation->markAsReadFor($user);
```

#### Scopes

```php
// Get conversations for a user (as customer or vendor)
Conversation::forUser($user)->get();

// Order by most recent message
Conversation::latestMessage()->get();
```

#### Finding/Creating Conversations

```php
// Find existing or create new conversation
$conversation = Conversation::findOrCreateBetween($customer, $vendor);

// Implementation
public static function findOrCreateBetween(User $customer, User $vendor): self
{
    return static::firstOrCreate([
        'customer_id' => $customer->id,
        'vendor_id' => $vendor->id,
    ]);
}
```

### Message

**Location**: `app/Models/Message.php`

Individual messages within a conversation.

#### Attributes

```php
[
    'conversation_id',
    'sender_id',         // User who sent the message
    'body',              // Message text content
    'type',              // 'text', 'image', 'file', 'system'
    'attachments',       // File attachments (JSON array)
    'read_at',           // When message was read (null if unread)
]
```

#### Relationships

```php
$message->conversation()  // BelongsTo Conversation
$message->sender()        // BelongsTo User
```

## Broadcasting Setup

### Configuration

**File**: `config/reverb.php`

```php
'servers' => [
    'reverb' => [
        'host' => env('REVERB_SERVER_HOST', '0.0.0.0'),
        'port' => env('REVERB_SERVER_PORT', 8080),
        'hostname' => env('REVERB_HOST'),
        // ...
    ],
],

'apps' => [
    'apps' => [
        [
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
        ],
    ],
],
```

**.env Configuration**:

```
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=123456
REVERB_APP_KEY=abc123def456
REVERB_APP_SECRET=xyz789uvw012
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http
```

### Running Reverb

**Development**:

```bash
php artisan reverb:start
```

**Production** (via Supervisor):

```ini
[program:reverb]
command=php /path/to/artisan reverb:start
user=www-data
autostart=true
autorestart=true
```

### Channel Authorization

**File**: `routes/channels.php`

Defines which users can access which channels.

```php
// Private user channel
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Private conversation channel
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    $conversation = Conversation::find($conversationId);

    if (!$conversation) {
        return false;
    }

    // Only participants can access
    return $conversation->hasParticipant($user);
});
```

**Authorization Request**:

```
POST /broadcasting/auth
Headers:
  Authorization: Bearer {token}
Body:
  channel_name=conversation.5
  socket_id=123.456
```

## Events

### MessageSent

**Location**: `app/Events/MessageSent.php`

Broadcast when a new message is sent.

```php
class MessageSent implements ShouldBroadcast
{
    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->message->conversation_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id,
            'sender' => [
                'id' => $this->message->sender->id,
                'name' => $this->message->sender->name,
                'avatar' => $this->message->sender->avatar,
            ],
            'body' => $this->message->body,
            'type' => $this->message->type,
            'attachments' => $this->message->attachments,
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }
}
```

### MessagesRead

**Location**: `app/Events/MessagesRead.php`

Broadcast when a user reads messages.

```php
class MessagesRead implements ShouldBroadcast
{
    public function __construct(
        public Conversation $conversation,
        public User $reader
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->conversation->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'messages.read';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'reader_id' => $this->reader->id,
            'read_at' => now()->toIso8601String(),
        ];
    }
}
```

### UserTyping

**Location**: `app/Events/UserTyping.php`

Broadcast typing indicators.

```php
class UserTyping implements ShouldBroadcast
{
    public function __construct(
        public Conversation $conversation,
        public User $user,
        public bool $isTyping
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->conversation->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'user.typing';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->user->id,
            'is_typing' => $this->isTyping,
        ];
    }
}
```

## API Endpoints

**Controller**: `app/Http/Controllers/Api/V1/ChatController.php`

All chat endpoints require authentication (`auth:sanctum`).

### List Conversations

`GET /api/v1/chat/conversations`

Returns all conversations for the authenticated user.

**Query Parameters**:

- `per_page` - Pagination (default: 20)

**Response**:

```json
{
    "data": [
        {
            "id": 5,
            "customer": { "id": 10, "name": "John Doe", "avatar": "..." },
            "vendor": { "id": 25, "name": "Gift Shop", "avatar": "..." },
            "last_message": "Thanks for your help!",
            "last_message_at": "2026-02-03T14:30:00Z",
            "last_message_sender": { "id": 10, "name": "John Doe" },
            "unread_count": 2,
            "other_participant": { "id": 25, "name": "Gift Shop" }
        }
    ],
    "meta": {
        /* pagination */
    }
}
```

### Start Conversation

`POST /api/v1/chat/conversations`

Create a new conversation with a vendor.

**Request**:

```json
{
    "vendor_id": 25,
    "message": "Hi, I'm interested in your products"
}
```

**Validation**:

- `vendor_id` must be a valid user with role 'vendor'
- `message` is optional

**Response**:

```json
{
    "message": "Conversation started successfully.",
    "data": {
        "id": 5,
        "customer": { ... },
        "vendor": { ... },
        "messages": [ /* if initial message sent */ ]
    }
}
```

### View Conversation

`GET /api/v1/chat/conversations/{conversation}`

Get conversation details.

**Authorization**: User must be a participant.

**Side Effect**: Marks all messages as read for the requesting user and broadcasts `messages.read` event.

**Response**:

```json
{
    "data": {
        "id": 5,
        "customer": { ... },
        "vendor": { ... },
        "last_message": "...",
        "unread_count": 0,
        "created_at": "2026-01-15T10:00:00Z"
    }
}
```

### Get Messages

`GET /api/v1/chat/conversations/{conversation}/messages`

Paginated list of messages in a conversation.

**Query Parameters**:

- `per_page` - Default: 50

**Response**:

```json
{
    "data": [
        {
            "id": 123,
            "sender": { "id": 10, "name": "John Doe", "avatar": "..." },
            "body": "Hello!",
            "type": "text",
            "attachments": [],
            "read_at": null,
            "created_at": "2026-02-03T14:25:00Z"
        }
    ],
    "meta": {
        /* pagination */
    }
}
```

### Send Message

`POST /api/v1/chat/conversations/{conversation}/messages`

Send a message in a conversation.

**Request** (Text Message):

```json
{
    "body": "Hello! I have a question about shipping.",
    "type": "text"
}
```

**Request** (With Attachments):

```
POST /api/v1/chat/conversations/5/messages
Content-Type: multipart/form-data

body=Check out this image
type=image
attachments[]=@image.jpg
```

**Supported Types**:

- `text` - Plain text
- `image` - Image attachment
- `file` - File attachment
- `system` - System-generated message

**Process**:

```php
public function sendMessage(SendMessageRequest $request, Conversation $conversation): JsonResponse
{
    // Check authorization
    if (!$conversation->hasParticipant($request->user())) {
        abort(403);
    }

    $attachments = [];

    // Handle file uploads
    if ($request->hasFile('attachments')) {
        foreach ($request->file('attachments') as $file) {
            $path = $file->store('chat-attachments', 'public');
            $attachments[] = [
                'path' => $path,
                'url' => Storage::url($path),
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ];
        }
    }

    // Create message
    $message = $conversation->messages()->create([
        'sender_id' => $request->user()->id,
        'body' => $request->validated('body'),
        'type' => $request->validated('type', 'text'),
        'attachments' => $attachments,
    ]);

    // Update conversation
    $conversation->update([
        'last_message' => $request->validated('body'),
        'last_message_at' => now(),
        'last_message_sender_id' => $request->user()->id,
    ]);

    // Increment unread count for other participant
    $conversation->incrementUnreadFor($conversation->getOtherParticipant($request->user()));

    // Broadcast event
    broadcast(new MessageSent($message))->toOthers();

    return response()->json([
        'message' => 'Message sent successfully.',
        'data' => new MessageResource($message->load('sender')),
    ], 201);
}
```

**Response**:

```json
{
    "message": "Message sent successfully.",
    "data": {
        "id": 124,
        "sender": { ... },
        "body": "Hello!",
        "type": "text",
        "attachments": [],
        "created_at": "2026-02-03T14:30:00Z"
    }
}
```

### Mark as Read

`POST /api/v1/chat/conversations/{conversation}/read`

Manually mark all messages as read.

**Response**:

```json
{
    "message": "Messages marked as read."
}
```

**Side Effect**: Broadcasts `messages.read` event.

### Typing Indicator

`POST /api/v1/chat/conversations/{conversation}/typing`

Indicate that user is typing.

**Request**:

```json
{
    "is_typing": true
}
```

**Process**:

```php
public function typing(Request $request, Conversation $conversation): JsonResponse
{
    if (!$conversation->hasParticipant($request->user())) {
        abort(403);
    }

    broadcast(new UserTyping(
        $conversation,
        $request->user(),
        $request->boolean('is_typing')
    ))->toOthers();

    return response()->json(['message' => 'Typing status broadcast.']);
}
```

### Delete Conversation

`DELETE /api/v1/chat/conversations/{conversation}`

Soft delete a conversation for the user.

**Note**: This doesn't delete for the other participant, just hides it for the requesting user.

### Search Conversations

`GET /api/v1/chat/conversations/search`

Search conversations by participant name or message content.

**Query Parameters**:

- `query` - Search term

### Unread Count

`GET /api/v1/chat/unread-count`

Get total unread message count across all conversations.

**Response**:

```json
{
    "unread_count": 5
}
```

## Frontend Integration

### Connect to Reverb

```typescript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    auth: {
        headers: {
            Authorization: `Bearer ${authToken}`,
        },
    },
});
```

### Listen for Messages

```typescript
// Join conversation channel
window.Echo.private(`conversation.${conversationId}`)
    .listen('.message.sent', (event) => {
        // Add message to UI
        addMessageToChat(event);
    })
    .listen('.messages.read', (event) => {
        // Update read status
        markMessagesAsRead(event.reader_id);
    })
    .listen('.user.typing', (event) => {
        // Show/hide typing indicator
        setTypingStatus(event.user_id, event.is_typing);
    });
```

### Send Message

```typescript
// Send via API
const response = await fetch(
    `/api/v1/chat/conversations/${conversationId}/messages`,
    {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({
            body: messageText,
            type: 'text',
        }),
    },
);

// Message will be broadcast automatically
```

### Typing Indicator

```typescript
let typingTimeout: NodeJS.Timeout;

function handleTyping() {
    // Send typing=true
    fetch(`/api/v1/chat/conversations/${conversationId}/typing`, {
        method: 'POST',
        headers: {
            /* ... */
        },
        body: JSON.stringify({ is_typing: true }),
    });

    // Clear previous timeout
    clearTimeout(typingTimeout);

    // Send typing=false after 3 seconds of inactivity
    typingTimeout = setTimeout(() => {
        fetch(`/api/v1/chat/conversations/${conversationId}/typing`, {
            method: 'POST',
            body: JSON.stringify({ is_typing: false }),
        });
    }, 3000);
}
```

## Performance Considerations

### Message Pagination

Always paginate messages to avoid loading thousands of messages at once:

```php
$messages = $conversation->messages()
    ->orderBy('created_at', 'desc')
    ->paginate(50);
```

### Conversation Caching

Cache conversation lists to reduce database queries:

```php
$conversations = Cache::remember(
    "user:{$userId}:conversations",
    300, // 5 minutes
    fn() => Conversation::forUser($user)->with('lastMessageSender')->get()
);
```

### Broadcasting Queues

Broadcast jobs are queued by default. Ensure queue workers are running:

```bash
php artisan queue:work
```

## Testing

### Feature Test

```php
use App\Events\MessageSent;
use Illuminate\Support\Facades\Event;

public function test_can_send_message(): void
{
    Event::fake([MessageSent::class]);

    $customer = User::factory()->create(['role' => 'customer']);
    $vendor = User::factory()->create(['role' => 'vendor']);
    $conversation = Conversation::factory()->create([
        'customer_id' => $customer->id,
        'vendor_id' => $vendor->id,
    ]);

    Sanctum::actingAs($customer);

    $response = $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
        'body' => 'Hello vendor!',
        'type' => 'text',
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->id,
        'sender_id' => $customer->id,
        'body' => 'Hello vendor!',
    ]);

    Event::assertDispatched(MessageSent::class);
}
```

---

This real-time chat system provides instant, secure communication between customers and vendors, enhancing the marketplace experience.
