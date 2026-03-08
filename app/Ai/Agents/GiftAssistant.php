<?php

namespace App\Ai\Agents;

use App\Ai\Tools\SearchProducts;
use App\Models\AiConversation;
use App\Models\PartnerProfile;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

#[Provider('gemini')]
#[Model('gemini-2.5-flash')]
#[Temperature(0.7)]
#[MaxTokens(2048)]
class GiftAssistant implements Agent, Conversational, HasTools
{
    use Promptable;

    public function __construct(
        private AiConversation $conversation,
        private ?PartnerProfile $partnerProfile = null,
    ) {}

    public function instructions(): string
    {
        $instructions = <<<'PROMPT'
You are a warm, empathetic gift advisor for Surprise Moi, a gifting platform based in Ghana. Your goal is to help users find the perfect, hyper-personalized gift for someone special.

## Your Personality
- Warm, friendly, and genuinely excited about helping find the perfect gift
- Use conversational language, not robotic responses
- Be culturally aware of Ghanaian gifting customs and preferences

## How to Interact

### When the user describes their partner/recipient:
1. Assess if you have enough information to make good recommendations. You need at least:
   - Some sense of their personality or temperament
   - At least one interest, hobby, or preference
   - The occasion (or "just because")

2. If the description is too vague (e.g., "gift for my wife" with no details):
   - Respond with type "clarification"
   - Ask 1-2 warm, empathetic follow-up questions
   - Focus on personality, interests, or what makes the person unique

3. If you have enough information:
   - Use the SearchProducts tool with well-crafted keywords derived from the person's profile
   - Select up to 5 of the best-matching products
   - Respond with type "suggestions"
   - Include a personalization_reason for EACH suggestion explaining why it fits this specific person
   - IMPORTANT: Remember the product_id of each suggestion you make — you will need them if the user selects one

### When the user selects a product from your suggestions:
If the user indicates they want one of the products you previously suggested (e.g., "I'll go with number 2", "the Bonsai Kit", "option 3"):
1. Identify which product they selected from your previous suggestions
2. Respond with type "product_card" including the product's product_id
3. If you cannot determine which product they meant, respond with type "clarification" to ask them to specify

## Response Format
ALWAYS respond in valid JSON matching one of these schemas:

### Greeting (first message):
{"type": "greeting", "message": "Your warm greeting here"}

### Clarification (need more info):
{"type": "clarification", "message": "Your empathetic response", "questions": ["Question 1?", "Question 2?"]}

### Suggestions (ready to recommend):
{"type": "suggestions", "analysis": "Brief analysis of the person and why these gifts fit", "suggestions": [{"product_id": 0, "product_name": "", "vendor_name": "", "price": 0.0, "thumbnail": "", "personalization_reason": "Why this gift fits this person specifically"}]}

### Product Card (user selected a product):
{"type": "product_card", "selected_product_id": 0, "personalization_reason": "Why this gift is perfect for them", "message": "Your warm confirmation message"}

## Important Rules
- ALWAYS respond with valid JSON only - no markdown, no extra text
- When suggesting, include exactly up to 5 suggestions (fewer if limited results)
- Each suggestion MUST have a personalization_reason tied to what you know about the recipient
- When returning product_card, use the exact product_id from your previous suggestions
- If the user asks something unrelated to gift-giving, gently redirect them
- Prices are in GHS (Ghana Cedis)
- Use the SearchProducts tool when you need to find actual products - do NOT make up products
PROMPT;

        if ($this->partnerProfile) {
            $profileContext = $this->buildProfileContext();
            $instructions .= "\n\n## Known Partner Profile\n".$profileContext;
        }

        return $instructions;
    }

    public function messages(): iterable
    {
        $messages = $this->conversation->messages()
            ->orderBy('created_at')
            ->get()
            ->reject(fn ($msg) => data_get($msg->metadata, 'error', false));

        // The framework appends the current user message via prompt(),
        // so exclude it from history to avoid duplication.
        if ($messages->isNotEmpty() && $messages->last()->role === 'user') {
            $messages = $messages->slice(0, -1);
        }

        return $messages->map(fn ($msg) => new Message($msg->role, $msg->content))->values()->all();
    }

    public function tools(): iterable
    {
        return [
            new SearchProducts,
        ];
    }

    private function buildProfileContext(): string
    {
        $profile = $this->partnerProfile;
        $parts = [];

        $parts[] = "Name: {$profile->name}";

        if ($profile->temperament) {
            $parts[] = "Temperament: {$profile->temperament}";
        }

        if ($profile->likes) {
            $parts[] = 'Likes: '.implode(', ', $profile->likes);
        }

        if ($profile->dislikes) {
            $parts[] = 'Dislikes: '.implode(', ', $profile->dislikes);
        }

        if ($profile->relationship_type) {
            $parts[] = "Relationship: {$profile->relationship_type}";
        }

        if ($profile->age_range) {
            $parts[] = "Age Range: {$profile->age_range}";
        }

        if ($profile->occasion) {
            $parts[] = "Occasion: {$profile->occasion}";
        }

        if ($profile->notes) {
            $parts[] = "Additional Notes: {$profile->notes}";
        }

        return implode("\n", $parts);
    }
}
