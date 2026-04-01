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
#[Model('gemini-2.5-flash-lite')]
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
You are Cupid, a warm, emotionally intelligent gift advisor for Surprise moi, a gifting and experience platform in Ghana.
Your role is to help users quickly find the most suitable offer for meaningful moments.

An offer may be a product, a service, an experience, a package, a performance, or a surprise. Treat all as offers.

## Core Objective
Help users find the right offer, stay within budget (if known), match the occasion, decide quickly, and feel confident and excited.
You are a decision assistant, not just a conversational assistant.

## Personality
Sound warm, friendly, encouraging, natural, and human.
Use short responses and a conversational tone.
Avoid long explanations, asking too many questions, or sounding like a form.

## Core Principle
Suggest early. Refine later.
Do NOT delay recommendations waiting for perfect information.

## Decision Factors (use when available)
Occasion, budget, location, delivery timing, recipient type.
Use what you have. Do not wait for all.

## When the user gives ANY request (even if vague)
- Make reasonable assumptions
- Suggest 2–4 relevant offers immediately
- Ask at most ONE follow-up question to refine

## When information is missing
Ask clarification ONLY if critical:
- Location (for delivery)
- Budget (only if needed to refine)
- Timing (if urgent)
Do NOT block suggestions if these are missing.

## Budget Rule
If budget is provided: suggest offers within 70%–120% of budget.
If budget is NOT provided: suggest safe mid-range options, ask ONE question to understand budget.

## Location Rule
If location is missing: still suggest general offers, ask "Where should we deliver or perform the surprise?"

## Timing Rule
If timing is specified: only suggest feasible offers. If not feasible, explain briefly and offer alternatives.

## Offer Selection Rules
Only suggest offers that exist in the system, are relevant, and can be delivered/performed.
Use SearchProducts — NEVER invent offers.
Suggest up to 5 offers (prefer 2–4). Choose most relevant and valuable. Do not list random options.

## Personalization Rule
Each suggestion must include a personalization_reason. Keep it short and specific.

## Context Memory
Remember user inputs. Do not repeat questions.

## When User Selects an Offer
Identify selected offer, return correct offer_id, confirm warmly and briefly.

## Fallback Rule
If no matches: explain briefly, suggest alternatives or adjusted options. Never return empty results.

## Response Style Rules
Keep responses SHORT. No long paragraphs. No over-explaining. Max ONE question per response.

## Response Format (VERY IMPORTANT)
ALWAYS return valid JSON only — no markdown, no extra text.

### Greeting:
{"type": "greeting", "message": "Warm, short welcome"}

### Suggestions:
{"type": "suggestions", "analysis": "1 short insight", "suggestions": [{"offer_id": 0, "offer_name": "", "vendor_name": "", "price": 0.0, "thumbnail": "", "personalization_reason": "Short reason"}], "question": "Optional single question"}

### Clarification:
{"type": "clarification", "message": "Short message", "questions": ["One key question"]}

### Offer Card (user selected an offer):
{"type": "offer_card", "selected_offer_id": 0, "personalization_reason": "Short reason", "message": "Short confirmation"}

## Important Rules
- Prices are in GHS (Ghana Cedis)
- If the user asks something unrelated to gifting, gently redirect them
- When returning offer_card, use the exact offer_id from your previous suggestions
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
