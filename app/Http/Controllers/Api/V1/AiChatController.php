<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AiChat\SendAiMessageRequest;
use App\Http\Requests\Api\V1\AiChat\StoreAiConversationRequest;
use App\Models\AiConversation;
use App\Services\AiChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiChatController extends Controller
{
    public function __construct(
        private AiChatService $chatService
    ) {}

    /**
     * List the authenticated user's AI conversations.
     */
    public function index(Request $request): JsonResponse
    {
        $conversations = $this->chatService->getConversations(
            $request->user(),
            $request->integer('per_page', 20)
        );

        return response()->json($conversations);
    }

    /**
     * Start a new AI conversation.
     */
    public function store(StoreAiConversationRequest $request): JsonResponse
    {
        $conversation = $this->chatService->startConversation(
            $request->user(),
            $request->validated('partner_profile_id'),
            $request->validated('message'),
        );

        return response()->json([
            'message' => 'Conversation started successfully.',
            'data' => $conversation,
        ], 201);
    }

    /**
     * Get a single conversation with all messages.
     */
    public function show(Request $request, AiConversation $aiConversation): JsonResponse
    {
        if ($aiConversation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $conversation = $this->chatService->getConversation($aiConversation);

        return response()->json(['data' => $conversation]);
    }

    /**
     * Send a message to an AI conversation.
     */
    public function sendMessage(SendAiMessageRequest $request, AiConversation $aiConversation): JsonResponse
    {
        if ($aiConversation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $aiMessage = $this->chatService->sendMessage(
            $aiConversation,
            $request->validated('message'),
        );

        return response()->json([
            'message' => 'Message sent successfully.',
            'data' => $aiMessage,
        ]);
    }

    /**
     * Soft-delete an AI conversation.
     */
    public function destroy(Request $request, AiConversation $aiConversation): JsonResponse
    {
        if ($aiConversation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $aiConversation->delete();

        return response()->json(['message' => 'Conversation deleted successfully.']);
    }
}
