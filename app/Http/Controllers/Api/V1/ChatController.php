<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\MessageSent;
use App\Events\MessagesRead;
use App\Events\UserTyping;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Http\Requests\StartConversationRequest;
use App\Http\Resources\ConversationDetailResource;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    /**
     * Get all conversations for the authenticated user.
     */
    public function conversations(Request $request): AnonymousResourceCollection
    {
        $conversations = Conversation::forUser($request->user())
            ->with(['customer', 'vendor', 'lastMessageSender'])
            ->latestMessage()
            ->paginate($request->input('per_page', 20));

        return ConversationResource::collection($conversations);
    }

    /**
     * Start a new conversation with a vendor.
     */
    public function startConversation(StartConversationRequest $request): JsonResponse
    {
        $customer = $request->user();
        $vendor = User::findOrFail($request->validated('vendor_id'));

        // Ensure the vendor is actually a vendor
        if ($vendor->role !== 'vendor') {
            return response()->json([
                'message' => 'You can only start conversations with vendors.',
            ], 422);
        }

        // Find or create the conversation
        $conversation = Conversation::findOrCreateBetween($customer, $vendor);

        // Load relationships
        $conversation->load(['customer', 'vendor']);

        // If an initial message was provided, send it
        if ($request->filled('message')) {
            $message = $conversation->messages()->create([
                'sender_id' => $customer->id,
                'body' => $request->validated('message'),
                'type' => 'text',
            ]);

            // Update conversation
            $conversation->update([
                'last_message' => $request->validated('message'),
                'last_message_at' => now(),
                'last_message_sender_id' => $customer->id,
            ]);

            $conversation->incrementUnreadFor($customer);

            // Broadcast the message to the vendor
            $message->load('sender');
            broadcast(new MessageSent($message, $vendor->id))->toOthers();
        }

        return response()->json([
            'message' => 'Conversation started successfully.',
            'data' => new ConversationDetailResource($conversation),
        ], 201);
    }

    /**
     * Get a specific conversation with messages.
     */
    public function showConversation(Request $request, Conversation $conversation): JsonResponse
    {
        // Check if user is a participant
        if (! $conversation->hasParticipant($request->user())) {
            return response()->json([
                'message' => 'You are not authorized to view this conversation.',
            ], 403);
        }

        $conversation->load(['customer', 'vendor']);

        // Mark messages as read
        $conversation->markAsReadFor($request->user());

        // Broadcast that messages have been read to the other participant
        $otherParticipant = $conversation->getOtherParticipant($request->user());
        broadcast(new MessagesRead($conversation, $request->user(), $otherParticipant->id))->toOthers();

        return response()->json([
            'data' => new ConversationDetailResource($conversation),
        ]);
    }

    /**
     * Get messages for a conversation with pagination.
     */
    public function messages(Request $request, Conversation $conversation): AnonymousResourceCollection
    {
        // Check if user is a participant
        if (! $conversation->hasParticipant($request->user())) {
            abort(403, 'You are not authorized to view this conversation.');
        }

        $messages = $conversation->messages()
            ->with(['sender', 'replyTo.sender'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 50));

        return MessageResource::collection($messages);
    }

    /**
     * Send a message in a conversation.
     */
    public function sendMessage(SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        // Check if user is a participant
        if (! $conversation->hasParticipant($request->user())) {
            return response()->json([
                'message' => 'You are not authorized to send messages in this conversation.',
            ], 403);
        }

        $attachments = [];
        $type = $request->validated('type') ?? 'text';

        // Handle file uploads
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('chat-attachments');
                $attachments[] = [
                    'path' => $path,
                    'url' => Storage::url($path),
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ];
            }

            // Set type based on first attachment if not specified
            if (! $request->filled('type')) {
                $firstMime = $attachments[0]['mime_type'] ?? '';
                $type = str_starts_with($firstMime, 'image/') ? 'image' : 'file';
            }
        }

        // Create the message
        $messageData = [
            'sender_id' => $request->user()->id,
            'body' => $request->validated('body'),
            'type' => $type,
            'attachments' => ! empty($attachments) ? $attachments : null,
        ];

        if ($request->filled('reply_to_id')) {
            $messageData['reply_to_id'] = $request->validated('reply_to_id');
        }

        $message = $conversation->messages()->create($messageData);

        // Update conversation metadata
        $conversation->update([
            'last_message' => $request->validated('body') ?? '[Attachment]',
            'last_message_at' => now(),
            'last_message_sender_id' => $request->user()->id,
        ]);

        // Increment unread count for the other participant
        $conversation->incrementUnreadFor($request->user());

        // Load sender and reply_to relationships
        $message->load(['sender', 'replyTo.sender']);

        // Broadcast the new message to the other participant
        $conversation->load(['customer', 'vendor']);
        $recipient = $conversation->getOtherParticipant($request->user());
        broadcast(new MessageSent($message, $recipient->id))->toOthers();

        return response()->json([
            'message' => 'Message sent successfully.',
            'data' => new MessageResource($message),
        ], 201);
    }

    /**
     * Mark all messages in a conversation as read.
     */
    public function markAsRead(Request $request, Conversation $conversation): JsonResponse
    {
        // Check if user is a participant
        if (! $conversation->hasParticipant($request->user())) {
            return response()->json([
                'message' => 'You are not authorized to access this conversation.',
            ], 403);
        }

        $conversation->markAsReadFor($request->user());

        // Broadcast that messages have been read to the other participant
        $conversation->load(['customer', 'vendor']);
        $otherParticipant = $conversation->getOtherParticipant($request->user());
        broadcast(new MessagesRead($conversation, $request->user(), $otherParticipant->id))->toOthers();

        return response()->json([
            'message' => 'Messages marked as read.',
        ]);
    }

    /**
     * Send typing indicator.
     */
    public function typing(Request $request, Conversation $conversation): JsonResponse
    {
        // Check if user is a participant
        if (! $conversation->hasParticipant($request->user())) {
            return response()->json([
                'message' => 'You are not authorized to access this conversation.',
            ], 403);
        }

        $isTyping = $request->boolean('is_typing', true);

        // Broadcast typing indicator to the other participant
        $conversation->load(['customer', 'vendor']);
        $recipient = $conversation->getOtherParticipant($request->user());

        broadcast(new UserTyping(
            $conversation->id,
            $request->user(),
            $recipient->id,
            $isTyping
        ))->toOthers();

        return response()->json([
            'message' => 'Typing status sent.',
        ]);
    }

    /**
     * Delete a conversation (soft delete).
     */
    public function deleteConversation(Request $request, Conversation $conversation): JsonResponse
    {
        // Check if user is a participant
        if (! $conversation->hasParticipant($request->user())) {
            return response()->json([
                'message' => 'You are not authorized to delete this conversation.',
            ], 403);
        }

        $conversation->delete();

        return response()->json([
            'message' => 'Conversation deleted successfully.',
        ]);
    }

    /**
     * Get unread messages count for the authenticated user.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $request->user()->getUnreadMessagesCount();

        return response()->json([
            'unread_count' => $count,
        ]);
    }

    /**
     * Search conversations by participant name or message content.
     */
    public function searchConversations(Request $request): AnonymousResourceCollection
    {
        $query = $request->input('query', '');

        $conversations = Conversation::forUser($request->user())
            ->with(['customer', 'vendor', 'lastMessageSender'])
            ->where(function ($q) use ($query) {
                $q->whereHas('customer', function ($customerQuery) use ($query) {
                    $customerQuery->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($query).'%']);
                })
                    ->orWhereHas('vendor', function ($vendorQuery) use ($query) {
                        $vendorQuery->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($query).'%']);
                    })
                    ->orWhereRaw('LOWER(last_message) LIKE ?', ['%'.strtolower($query).'%']);
            })
            ->latestMessage()
            ->paginate($request->input('per_page', 20));

        return ConversationResource::collection($conversations);
    }
}
