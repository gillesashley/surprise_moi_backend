<?php

namespace Tests\Feature;

use App\Ai\Agents\GiftAssistant;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\PartnerProfile;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiChatApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'customer']);
    }

    // ==================== Start Conversation Tests ====================

    public function test_user_can_start_ai_conversation(): void
    {
        GiftAssistant::fake(['{"type":"greeting","message":"Hi! I\'m your gift assistant."}']);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/ai-chat/conversations');

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'user_id',
                    'title',
                    'status',
                    'messages',
                ],
            ]);

        $this->assertDatabaseHas('ai_conversations', [
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('ai_messages', [
            'role' => 'assistant',
            'type' => 'greeting',
        ]);
    }

    public function test_user_can_start_conversation_with_partner_profile(): void
    {
        GiftAssistant::fake(['{"type":"greeting","message":"Hi! Looking for a gift for Mom."}']);

        $profile = PartnerProfile::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Mom',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/ai-chat/conversations', [
                'partner_profile_id' => $profile->id,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('ai_conversations', [
            'user_id' => $this->user->id,
            'partner_profile_id' => $profile->id,
        ]);
    }

    public function test_user_cannot_use_another_users_partner_profile(): void
    {
        $otherUser = User::factory()->create();
        $profile = PartnerProfile::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/ai-chat/conversations', [
                'partner_profile_id' => $profile->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['partner_profile_id']);
    }

    // ==================== Send Message Tests ====================

    public function test_user_can_send_message_to_ai_conversation(): void
    {
        GiftAssistant::fake([
            '{"type":"clarification","message":"Tell me more!","questions":["What are their hobbies?"]}',
        ]);

        $conversation = AiConversation::factory()->create([
            'user_id' => $this->user->id,
        ]);

        // Add initial greeting message
        AiMessage::factory()->greeting()->create([
            'ai_conversation_id' => $conversation->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ai-chat/conversations/{$conversation->id}/messages", [
                'message' => 'I need a gift for my wife',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'ai_conversation_id',
                    'role',
                    'content',
                    'type',
                    'metadata',
                ],
            ]);

        // Should have user message and assistant response
        $this->assertDatabaseHas('ai_messages', [
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'I need a gift for my wife',
        ]);

        $this->assertDatabaseHas('ai_messages', [
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
        ]);
    }

    public function test_message_validation_requires_message_field(): void
    {
        $conversation = AiConversation::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ai-chat/conversations/{$conversation->id}/messages", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_message_max_length_validation(): void
    {
        $conversation = AiConversation::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ai-chat/conversations/{$conversation->id}/messages", [
                'message' => str_repeat('a', 2001),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    // ==================== List Conversations Tests ====================

    public function test_user_can_list_ai_conversations(): void
    {
        AiConversation::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        // Another user's conversations should not appear
        AiConversation::factory()->count(2)->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/ai-chat/conversations');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_archived_conversations_not_listed(): void
    {
        AiConversation::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        AiConversation::factory()->archived()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/ai-chat/conversations');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    // ==================== View Conversation Tests ====================

    public function test_user_can_view_ai_conversation_with_messages(): void
    {
        $conversation = AiConversation::factory()->create([
            'user_id' => $this->user->id,
        ]);

        AiMessage::factory()->count(5)->create([
            'ai_conversation_id' => $conversation->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/ai-chat/conversations/{$conversation->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'user_id',
                    'title',
                    'status',
                    'messages',
                ],
            ]);
    }

    public function test_user_cannot_access_other_users_conversations(): void
    {
        $otherUser = User::factory()->create();
        $conversation = AiConversation::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/ai-chat/conversations/{$conversation->id}");

        $response->assertStatus(403);
    }

    // ==================== Delete Conversation Tests ====================

    public function test_user_can_delete_ai_conversation(): void
    {
        $conversation = AiConversation::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/ai-chat/conversations/{$conversation->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('ai_conversations', [
            'id' => $conversation->id,
        ]);
    }

    public function test_user_cannot_delete_other_users_conversations(): void
    {
        $otherUser = User::factory()->create();
        $conversation = AiConversation::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/ai-chat/conversations/{$conversation->id}");

        $response->assertStatus(403);
    }

    public function test_send_message_returns_non_empty_assistant_content(): void
    {
        GiftAssistant::fake([
            json_encode([
                'type' => 'suggestions',
                'suggestions' => [
                    [
                        'product_id' => 1,
                        'product_name' => 'Test Gift',
                        'vendor_name' => 'Vendor',
                        'price' => 50.00,
                        'thumbnail' => 'https://example.com/img.jpg',
                        'personalization_reason' => 'Perfect match.',
                    ],
                ],
            ]),
        ]);

        $conversation = AiConversation::factory()->create([
            'user_id' => $this->user->id,
        ]);

        AiMessage::factory()->greeting()->create([
            'ai_conversation_id' => $conversation->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ai-chat/conversations/{$conversation->id}/messages", [
                'message' => 'She loves cooking and is very creative',
            ]);

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNotEmpty($data['content'], 'Assistant content should not be empty');
        $this->assertEquals('suggestions', $data['type']);
    }

    // ==================== Authentication Tests ====================

    public function test_unauthenticated_user_cannot_access_ai_chat(): void
    {
        $response = $this->getJson('/api/v1/ai-chat/conversations');
        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_start_conversation(): void
    {
        $response = $this->postJson('/api/v1/ai-chat/conversations');
        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_send_message(): void
    {
        $conversation = AiConversation::factory()->create();

        $response = $this->postJson("/api/v1/ai-chat/conversations/{$conversation->id}/messages", [
            'message' => 'Test',
        ]);

        $response->assertStatus(401);
    }

    // ==================== Product Card Resolution Tests ====================

    public function test_product_card_response_returns_full_product_data(): void
    {
        $product = Product::factory()->create([
            'name' => 'Sweet Bouquet',
            'price' => 6.00,
            'is_available' => true,
        ]);

        GiftAssistant::fake([
            json_encode([
                'type' => 'product_card',
                'selected_product_id' => $product->id,
                'personalization_reason' => 'Perfect for a romantic surprise.',
                'message' => 'Great choice! Here are the full details.',
            ]),
        ]);

        $conversation = AiConversation::factory()->create([
            'user_id' => $this->user->id,
        ]);

        AiMessage::factory()->greeting()->create([
            'ai_conversation_id' => $conversation->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ai-chat/conversations/{$conversation->id}/messages", [
                'message' => 'I like option 1',
            ]);

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals('suggestions', $data['type']);
        $this->assertEquals('product_card', $data['metadata']['display_type']);
        $this->assertCount(1, $data['metadata']['suggestions']);

        $suggestion = $data['metadata']['suggestions'][0];
        $this->assertEquals($product->id, $suggestion['id']);
        $this->assertEquals('Sweet Bouquet', $suggestion['name']);
        $this->assertEquals('Perfect for a romantic surprise.', $suggestion['personalization_reason']);
    }

    public function test_product_card_falls_back_to_text_when_product_unavailable(): void
    {
        $product = Product::factory()->create([
            'is_available' => false,
        ]);

        GiftAssistant::fake([
            json_encode([
                'type' => 'product_card',
                'selected_product_id' => $product->id,
                'personalization_reason' => 'Great fit.',
                'message' => 'Here you go!',
            ]),
        ]);

        $conversation = AiConversation::factory()->create([
            'user_id' => $this->user->id,
        ]);

        AiMessage::factory()->greeting()->create([
            'ai_conversation_id' => $conversation->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ai-chat/conversations/{$conversation->id}/messages", [
                'message' => 'I want that one',
            ]);

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals('text', $data['type']);
        $this->assertStringContainsString('no longer available', $data['content']);
    }

    public function test_product_card_falls_back_to_text_when_product_not_found(): void
    {
        GiftAssistant::fake([
            json_encode([
                'type' => 'product_card',
                'selected_product_id' => 99999,
                'personalization_reason' => 'Great fit.',
                'message' => 'Here you go!',
            ]),
        ]);

        $conversation = AiConversation::factory()->create([
            'user_id' => $this->user->id,
        ]);

        AiMessage::factory()->greeting()->create([
            'ai_conversation_id' => $conversation->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ai-chat/conversations/{$conversation->id}/messages", [
                'message' => 'Option 3 please',
            ]);

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals('text', $data['type']);
        $this->assertStringContainsString('no longer available', $data['content']);
    }
}
