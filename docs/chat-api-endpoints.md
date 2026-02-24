# Chat API Documentation

## Overview

The chat system enables real-time messaging between customers and vendors with full WhatsApp-like functionality including emoji support, read receipts, typing indicators, and chat history.

## Authentication

All endpoints require Bearer token authentication using Laravel Sanctum.

**Header:**

```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

## Endpoints

### 1. Get Conversations

**GET** `/api/v1/chat/conversations`

Get all conversations for the authenticated user.

**Query Parameters:**

- `per_page` (optional): Number of conversations per page (default: 20)

**Response:**

```json
{
  "data": [
    {
      "id": 1,
      "participant": {
        "id": 21,
        "name": "Premium Gifts Ghana",
        "avatar": "https://...",
        "role": "vendor",
        "is_online": true
      },
      "last_message": "Thank you for your order!",
      "last_message_at": "2026-02-07T13:08:04+00:00",
      "last_message_sender_id": 21,
      "unread_count": 2,
      "is_customer": true,
      "created_at": "2026-02-07T13:06:54+00:00",
      "updated_at": "2026-02-07T13:08:04+00:00"
    }
  ],
  "meta": { ... pagination ... }
}
```

### 2. Start Conversation

**POST** `/api/v1/chat/conversations`

Start a new conversation with a vendor. If conversation already exists, it will be returned.

**Payload:**

```json
{
    "vendor_id": 21,
    "message": "Hi! I'm interested in your products! 👋"
}
```

**Response:**

```json
{
  "message": "Conversation started successfully.",
  "data": {
    "id": 1,
    "customer": { ... },
    "vendor": { ... },
    "participant": { ... },
    "last_message": "Hi! I'm interested in your products! 👋",
    "last_message_at": "2026-02-07T13:06:54+00:00",
    "unread_count": 0,
    ...
  }
}
```

### 3. Get Conversation Details

**GET** `/api/v1/chat/conversations/{conversation}`

Get a specific conversation with participant details.

**Response:**

```json
{
  "data": {
    "id": 1,
    "customer": { ... },
    "vendor": { ... },
    "participant": { ... },
    ...
  }
}
```

### 4. Search Conversations

**GET** `/api/v1/chat/conversations/search?query={search_term}`

Search conversations by participant name or message content.

**Query Parameters:**

- `query` (required): Search term
- `per_page` (optional): Results per page (default: 20)

### 5. Get Messages

**GET** `/api/v1/chat/conversations/{conversation}/messages`

Get messages for a conversation with pagination.

**Query Parameters:**

- `per_page` (optional): Messages per page (default: 50)

**Response:**

```json
{
  "data": [
    {
      "id": 1,
      "conversation_id": 1,
      "sender_id": 2,
      "sender": {
        "id": 2,
        "name": "John Doe",
        "avatar": null
      },
      "body": "Hello! 👋",
      "type": "text",
      "attachments": null,
      "is_read": true,
      "read_at": "2026-02-07T13:07:51+00:00",
      "is_mine": true,
      "created_at": "2026-02-07T13:06:54+00:00",
      "updated_at": "2026-02-07T13:07:51+00:00"
    }
  ],
  "meta": { ... pagination ... }
}
```

### 6. Send Message

**POST** `/api/v1/chat/conversations/{conversation}/messages`

Send a message in a conversation. Supports text, emojis, and attachments.

**Payload (Text/Emoji):**

```json
{
    "body": "Great! 🎁 I will order soon! 😊",
    "type": "text"
}
```

**Payload (With Attachments):**

```json
{
    "body": "Here's the photo",
    "type": "image",
    "attachments": ["file1", "file2"] // multipart/form-data
}
```

**Message Types:**

- `text` - Text message (can include emojis)
- `image` - Image attachment
- `file` - File attachment

**Validation:**

- `body`: Max 5000 characters, required without attachments
- `attachments`: Max 10 files, 10MB each

**Response:**

```json
{
  "message": "Message sent successfully.",
  "data": {
    "id": 3,
    "conversation_id": 1,
    "sender_id": 21,
    "sender": { ... },
    "body": "Great! 🎁 I will order soon! 😊",
    "type": "text",
    "is_read": false,
    "read_at": null,
    "is_mine": true,
    "created_at": "2026-02-07T13:08:04+00:00"
  }
}
```

### 7. Mark Messages as Read

**POST** `/api/v1/chat/conversations/{conversation}/read`

Mark all unread messages in a conversation as read. Updates `read_at` timestamp and broadcasts read event.

**Response:**

```json
{
    "message": "Messages marked as read."
}
```

### 8. Send Typing Indicator

**POST** `/api/v1/chat/conversations/{conversation}/typing`

Send typing status to other participant (real-time via broadcasting).

**Payload:**

```json
{
    "is_typing": true
}
```

**Response:**

```json
{
    "message": "Typing status sent."
}
```

### 9. Get Unread Count

**GET** `/api/v1/chat/unread-count`

Get total unread messages count across all conversations.

**Response:**

```json
{
    "unread_count": 5
}
```

### 10. Delete Conversation (DISABLED)

**DELETE** `/api/v1/chat/conversations/{conversation}`

⚠️ **THIS ENDPOINT IS DISABLED** for database integrity.

**Response:** `405 Method Not Allowed`

## Features

### ✅ Emoji Support

- Full Unicode emoji support (🎁, 😊, 👍, 💬, 🚀, ✅, etc.)
- Emojis stored correctly in PostgreSQL UTF-8 database
- Emojis display correctly in API responses

### ✅ Read Receipts (Double Check Mark)

- `is_read`: Boolean indicating if message was read
- `read_at`: Timestamp when message was read
- Recipient marking messages as read broadcasts event to sender
- Sender can see when their message was read

### ✅ Chat History

- All previous messages load on conversation open
- Pagination supported (50 messages per page by default)
- Messages ordered by timestamp (newest first)
- Scroll to load more functionality

### ✅ Unread Message Tracking

- Per-conversation unread count
- Total unread count across all conversations
- Unread count updates in real-time
- Marks as read when conversation is opened

### ✅ Typing Indicator

- Real-time typing status broadcast
- Shows when other participant is typing
- Broadcasts via Laravel Reverb/Pusher

### ✅ Conversation Search

- Search by participant name
- Search by message content
- Case-insensitive search

### ✅ Database Integrity

- Conversation deletion disabled
- Soft deletes configured but endpoint disabled
- All chat history preserved

## WebSocket Events

The chat system broadcasts the following events via Laravel Reverb/Pusher:

1. **MessageSent** - When a new message is sent
2. **MessagesRead** - When messages are marked as read
3. **UserTyping** - When a user is typing

## Security

- All endpoints require authentication
- Users can only access their own conversations
- Prevents unauthorized access to other users' chats
- Validates user is participant before allowing actions

## Testing

All functionality tested with:

- Customer account: `pending.vendor1@example.com`
- Vendor account: `vendor1@example.com`
- 8 test messages exchanged
- All features verified working
