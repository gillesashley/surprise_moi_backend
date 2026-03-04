<?php

namespace App\Services;

use App\Ai\Agents\GiftAssistant;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AiChatService
{
    /**
     * Start a new AI conversation with an optional partner profile.
     */
    public function startConversation(User $user, ?int $partnerProfileId = null, ?string $initialMessage = null): AiConversation
    {
        $partnerProfile = null;
        if ($partnerProfileId) {
            $partnerProfile = PartnerProfile::where('user_id', $user->id)
                ->findOrFail($partnerProfileId);
        }

        $conversation = AiConversation::create([
            'user_id' => $user->id,
            'partner_profile_id' => $partnerProfileId,
            'title' => $partnerProfile ? "Gift ideas for {$partnerProfile->name}" : 'New Gift Chat',
            'status' => 'active',
        ]);

        // Store and send greeting
        $greeting = $this->buildGreeting($partnerProfile);
        $this->storeMessage($conversation, 'assistant', $greeting, 'greeting');

        // If initial message provided, process it
        if ($initialMessage) {
            $this->sendMessage($conversation, $initialMessage);
        }

        return $conversation->load('messages');
    }

    /**
     * Send a message to an AI conversation and get a response.
     */
    public function sendMessage(AiConversation $conversation, string $message): AiMessage
    {
        // Store user message
        $this->storeMessage($conversation, 'user', $message, 'text');

        // Build and invoke the agent
        $partnerProfile = $conversation->partner_profile_id
            ? $conversation->partnerProfile
            : null;

        $agent = new GiftAssistant($conversation, $partnerProfile);

        try {
            $response = $agent->prompt($message);

            Log::debug('AI agent raw response', [
                'conversation_id' => $conversation->id,
                'response_text' => $response->text,
            ]);

            $parsed = $this->parseAiResponse($response->text);
        } catch (\Throwable $e) {
            Log::error('AI agent error', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            $parsed = [
                'type' => 'text',
                'content' => "I'm sorry, I'm having trouble processing your request right now. Please try again in a moment.",
                'metadata' => ['error' => true],
            ];
        }

        // Store assistant response
        $aiMessage = $this->storeMessage(
            $conversation,
            'assistant',
            $parsed['content'],
            $parsed['type'],
            $parsed['metadata'] ?? null
        );

        // Update conversation title if it's still the default
        if ($conversation->title === 'New Gift Chat') {
            $this->updateConversationTitle($conversation, $message);
        }

        return $aiMessage;
    }

    /**
     * Parse the AI response into a structured format.
     *
     * @return array{type: string, content: string, metadata: ?array<string, mixed>}
     */
    public function parseAiResponse(string $response): array
    {
        // Try to extract JSON from the response
        $json = $this->extractJson($response);

        if ($json === null) {
            return [
                'type' => 'text',
                'content' => $response,
                'metadata' => null,
            ];
        }

        $type = $json['type'] ?? 'text';
        $metadata = null;

        switch ($type) {
            case 'greeting':
                $content = $json['message'] ?? $response;
                break;

            case 'clarification':
                $content = $json['message'] ?? $response;
                $metadata = [
                    'questions' => $json['questions'] ?? [],
                ];
                break;

            case 'suggestions':
                $content = $json['analysis'] ?? $json['message'] ?? 'Here are some gift suggestions for you:';
                $metadata = [
                    'analysis' => $json['analysis'] ?? '',
                    'suggestions' => $json['suggestions'] ?? [],
                ];
                break;

            default:
                $content = $json['message'] ?? $response;
        }

        return [
            'type' => $type,
            'content' => $content,
            'metadata' => $metadata,
        ];
    }

    /**
     * Get paginated conversations for a user.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getConversations(User $user, int $perPage = 20)
    {
        return AiConversation::where('user_id', $user->id)
            ->where('status', 'active')
            ->with(['partnerProfile:id,name', 'messages' => function ($query) {
                $query->latest()->limit(1);
            }])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get a full conversation with all messages.
     */
    public function getConversation(AiConversation $conversation): AiConversation
    {
        return $conversation->load(['messages' => function ($query) {
            $query->orderBy('created_at');
        }, 'partnerProfile']);
    }

    private function storeMessage(
        AiConversation $conversation,
        string $role,
        string $content,
        string $type = 'text',
        ?array $metadata = null
    ): AiMessage {
        return AiMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => $role,
            'content' => $content,
            'type' => $type,
            'metadata' => $metadata,
        ]);
    }

    private function buildGreeting(?PartnerProfile $partnerProfile): string
    {
        if ($partnerProfile) {
            return "Hi! I see you're looking for a gift for {$partnerProfile->name}. "
                 .'I already have some details about them. Tell me more about what you have in mind, '
                 .'or I can start searching for the perfect gift right away!';
        }

        return "Hi! I'm your gift assistant at Surprise Moi. "
             ."Tell me about the person you're shopping for — their personality, hobbies, "
             ."what they love (or hate!) — and I'll find the perfect surprise for them.";
    }

    /**
     * Extract JSON from a string that may contain markdown or other wrapping.
     *
     * @return ?array<string, mixed>
     */
    private function extractJson(string $text): ?array
    {
        // Try direct decode first
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Try extracting from code blocks
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $text, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // Try finding JSON object in the text by locating the first {
        // and attempting to decode progressively larger substrings
        $start = strpos($text, '{');
        if ($start !== false) {
            $lastBrace = strrpos($text, '}');
            if ($lastBrace !== false) {
                $candidate = substr($text, $start, $lastBrace - $start + 1);
                $decoded = json_decode($candidate, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return null;
    }

    private function updateConversationTitle(AiConversation $conversation, string $firstMessage): void
    {
        $title = mb_strlen($firstMessage) > 50
            ? mb_substr($firstMessage, 0, 47).'...'
            : $firstMessage;

        $conversation->update(['title' => $title]);
    }
}
