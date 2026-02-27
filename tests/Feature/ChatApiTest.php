<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Events\MessagesRead;
use App\Events\UserTyping;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected User $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->create(['role' => 'customer']);
        $this->vendor = User::factory()->vendor()->create();
    }

    // ==================== Conversation Tests ====================

    public function test_user_can_get_their_conversations(): void
    {
        // Create conversations for the customer
        $conversation1 = Conversation::factory()
            ->forCustomer($this->customer)
            ->forVendor($this->vendor)
            ->withLastMessage('Hello!')
            ->create();

        $otherVendor = User::factory()->vendor()->create();
        $conversation2 = Conversation::factory()
            ->forCustomer($this->customer)
            ->forVendor($otherVendor)
            ->withLastMessage('Hi there!')
            ->create();

        // Create a conversation for another user (should not be visible)
        $otherCustomer = User::factory()->create();
        Conversation::factory()
            ->forCustomer($otherCustomer)
            ->forVendor($this->vendor)
            ->create();

        $response = $this->actingAs($this->customer)
            ->getJson('/api/v1/chat/conversations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'participant' => ['id', 'name', 'avatar', 'role'],
                        'last_message',
                        'last_message_at',
                        'unread_count',
                        'is_customer',
                        'created_at',
                    ],
                ],
                'links',
                'meta',
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_vendor_can_see_their_conversations(): void
    {
        $conversation = Conversation::factory()
            ->forCustomer($this->customer)
            ->forVendor($this->vendor)
            ->create();

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/chat/conversations');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $conversation->id)
            ->assertJsonPath('data.0.is_customer', false);
    }

    public function test_conversation_participant_is_the_other_person_for_customer(): void
    {
        $conversation = Conversation::factory()
            ->forCustomer($this->customer)
            ->forVendor($this->vendor)
            ->withLastMessage('Hello!')
            ->create();

        $response = $this->actingAs($this->customer)
            ->getJson('/api/v1/chat/conversations');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.participant.id', $this->vendor->id)
            ->assertJsonPath('data.0.participant.name', $this->vendor->name)
            ->assertJsonPath('data.0.is_customer', true);

        // Ensure participant is NOT the authenticated user
        $this->assertNotEquals(
            $this->customer->id,
            $response->json('data.0.participant.id'),
            'Participant should be the other person (vendor), not the authenticated user (customer)'
        );
    }

    public function test_conversation_participant_is_the_other_person_for_vendor(): void
    {
        $conversation = Conversation::factory()
            ->forCustomer($this->customer)
            ->forVendor($this->vendor)
            ->withLastMessage('Hi there!')
            ->create();

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/chat/conversations');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.participant.id', $this->customer->id)
            ->assertJsonPath('data.0.participant.name', $this->customer->name)
            ->assertJsonPath('data.0.is_customer', false);

        // Ensure participant is NOT the authenticated user
        $this->assertNotEquals(
            $this->vendor->id,
            $response->json('data.0.participant.id'),
            'Participant should be the other person (customer), not the authenticated user (vendor)'
        );
    }

    public function test_user_can_start_conversation_with_vendor(): void
    {
        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/chat/conversations', [
                'vendor_id' => $this->vendor->id,
                'message' => 'Hello, I have a question about your product.',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'customer',
                    'vendor',
                    'participant',
                    'last_message',
                ],
            ]);

        $this->assertDatabaseHas('conversations', [
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
        ]);

        $this->assertDatabaseHas('messages', [
            'body' => 'Hello, I have a question about your product.',
        ]);
    }

    public function test_starting_existing_conversation_returns_same_conversation(): void
    {
        $existingConversation = Conversation::factory()
            ->forCustomer($this->customer)
            ->forVendor($this->vendor)
            ->create();

        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/chat/conversations', [
                'vendor_id' => $this->vendor->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.id', $existingConversation->id);
    }

    public function test_cannot_start_conversation_with_non_vendor(): void
    {
        $otherCustomer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/chat/conversations', [
                'vendor_id' => $otherCustomer->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'You can only start conversations with vendors.');
    }

    public function test_user_can_view_specific_conversation(): void
    {
        $conversation = Conversation::factory()
            ->forCustomer($this->customer)
            ->forVendor($this->vendor)
            ->create();

        Message::factory()
            ->forConversation($conversation)
            ->fromSender($this->vendor)
            ->count(3)
            ->create();

        $response = $this->actingAs($this->customer)
            ->getJson("/api/v1/chat/conversations/{$conversation->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'customer',
                    'vendor',
                    'participant',
                    'last_message',
                    'created_at',
                ],
            ]);
    }

    public function test_cannot_view_other_users_conversation(): void
    {
        $otherCustomer = User::factory()->create();
        $conversation = Conversation::factory()
            ->forCustomer($otherCustomer)
            ->forVendor($this->vendor)
            ->create();

        $response = $this->actingAs($this->customer)
            ->getJson("/api/v1/chat/conversations/{$conversation->id}");

        $response->assertStatus(403);
    }

    public function test_delete_conversation_route_is_disabled(): void
    {
        $conversation = Conversation::factory()
            ->forCustomer($this->customer)
            ->forVendor($this->vendor)
            ->create();

        $response = $this->actingAs($this->customer)
            ->deleteJson("/api/v1/chat/conversations/{$conversation->id}");

        $response->assertStatus(405);

        $this->assertDatabaseHas('conversations', ['id' => $conversation->id]);
    }

    public function test_user_can_search_conversations(): void
    {
        $vendor1 = User::factory()->vendor()->create(['name' => 'Gift Shop Ghana']);
        $vendor2 = User::factory()->vendor()->create(['name' => 'Flower Boutique']);

        Conversation::factory()
            ->forCustomer($this->customer)
            ->forVendor($vendor1)
            ->create();

        Conversation::factory()
            ->forCustomer($this->customer)
            ->forVendor($vendor2)
            ->create();

        $response = $this->actingAs($this->customer)
            ->getJson('/api/v1/chat/conversations/search?query=Gift');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    // ==================== Message Tests ====================

    public function test_user_can_get_messages_from_conversation(): void
    {
        $conversation = Conversation::factory()
            ->forCustomer($this->customer)
            ->forVendor($this->vendor)
            ->create();

        Message::factory()
            ->forConversation($conversation)
            ->fromSender($this->customer)
            ->count(5)
            ->create();

        Message::factory()
            ->forConversation($conversation)
            ->fromSender($this->vendor)
            ->count(3)
            ->create();

        $response = $this->actingAs($this->customer)
            ->getJson("/api/v1/chat/conversations/{$conversation->id}/messages");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'conversation_id',
                        'sender_id',
                        'sender' => ['id', 'name', 'avatar'],
                        'body',
                        'type',
                        'attachments',
                        'is_read',
                        'is_mine',
                        'created_at',
                    ],
                ],
                'links',
                'meta',
            ])
            ->assertJsonCount(8, 'data');
    }

    public function test_user_can_send_text_message(): void
    {
        Event::fake([MessageSent::class]);

        $conversation = Conversation::factory()
            ->forCustomer($this->customer)
            ->forVendor($this->vendor)
            ->create();

        $response = $this->actingAs($this->customer)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
                'body' => 'Hello, I am interested in your product!',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'conversation_id',
                    'sender_id',
                    'body',
                    'type',
                    'is_mine',
                ],
            ])
            ->assertJsonPath('data.body', 'Hello, I am interested in your product!')
            ->assertJsonPath('data.type', 'text')
            ->assertJsonPath('data.is_mine', true);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $this->customer->id,
            'body' => 'Hello, I am interested in your product!',
        ]);

        // Verify conversation was updated
        $conversation->refresh();
        $this->assertEquals('Hello, I am interested in your product!', $conversation->last_message);
        $this->assertEquals($this->customer->id, $conversation->last_message_sender_id);

        Event::assertDispatched(MessageSent::class);
    }

    public function test_user_can_send_message_with_attachments(): void
    {
        Storage::fake('public');
        Event::fake([MessageSent::class]);

        $conversation = Conversation::factory()
            ->forCustomer($this->customer)
            ->forVendor($this->vendor)
            ->create();

        // Use a fake file instead of an image to avoid GD extension requirement
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->customer)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
                'body' => 'Check out this file',
                'attachments' => [$file],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'file');

        $message = Message::latest()->first();
        $this->assertNotNull($message->attachments);
        $this->assertCount(1, $message->attachments);

        Storage::disk()->assertExists($message->attachments[0]['path']);
    }

    public function test_cannot_send_message_to_others_conversation(): void
    {
        $otherCustomer = User::factory()->create();
        $conversation = Conversation::factory()
            ->forCustomer($otherCustomer)
            ->forVendor($this->vendor)
            ->create();

        $response = $this->actingAs($this->customer)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
                'body' => 'Trying to send unauthorized message',
            ]);

        $response->assertStatus(403);
    }

    public function test_vendor_can_reply_to_message(): void
    {
        Event::fake([MessageSent::class]);

        $conversation = Conversation::factory()
            ->forCustomer($this->customer)
            ->forVendor($this->vendor)
            ->create();

        // Customer sends initial message
        Message::factory()
            ->forConversation($conversation)
            ->fromSender($this->customer)
            ->create(['body' => 'Do you have this in blue?']);

        // Vendor replies
        $response = $this->actingAs($this->vendor)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
                'body' => 'Yes, we have it in blue! Would you like to order?',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.sender_id', $this->vendor->id);

        Event::assertDispatched(MessageSent::class);
    }

    // ==================== Read Status Tests ====================

    public function test_user_can_mark_messages_as_read(): void
    {
        Event::fake([MessagesRead::class]);

        $conversation = Conversation::factory()
            ->forCustomer($this->customer)
            ->forVendor($this->vendor)
            ->withUnreadForCustomer(5)
            ->create();

        // Vendor sent messages
        Message::factory()
            ->forConversation($conversation)
            ->fromSender($this->vendor)
            ->unread()
            ->count(5)
            ->create();

        $response = $this->actingAs($this->customer)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/read");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Messages marked as read.');

        $conversation->refresh();
        $this->assertEquals(0, $conversation->customer_unread_count);

        // All messages should now be read
        $unreadCount = Message::where('conversation_id', $conversation->id)
            ->whereNull('read_at')
            ->count();
        $this->assertEquals(0, $unreadCount);

        Event::assertDispatched(MessagesRead::class);
    }

    public function test_viewing_conversation_marks_messages_as_read(): void
    {
        $conversation = Conversation::factory()
            ->forCustomer($this->customer)
            ->forVendor($this->vendor)
            ->withUnreadForCustomer(3)
            ->create();

        Message::factory()
            ->forConversation($conversation)
            ->fromSender($this->vendor)
            ->unread()
            ->count(3)
            ->create();

        $this->actingAs($this->customer)
            ->getJson("/api/v1/chat/conversations/{$conversation->id}");

        $conversation->refresh();
        $this->assertEquals(0, $conversation->customer_unread_count);
    }

    // ==================== Typing Indicator Tests ====================

    public function test_user_can_send_typing_indicator(): void
    {
        Event::fake([UserTyping::class]);

        $conversation = Conversation::factory()
            ->forCustomer($this->customer)
            ->forVendor($this->vendor)
            ->create();

        $response = $this->actingAs($this->customer)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/typing", [
                'is_typing' => true,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Typing status sent.');

        Event::assertDispatched(UserTyping::class, function ($event) {
            return $event->isTyping === true
                && $event->user->id === $this->customer->id;
        });
    }

    public function test_user_can_stop_typing_indicator(): void
    {
        Event::fake([UserTyping::class]);

        $conversation = Conversation::factory()
            ->forCustomer($this->customer)
            ->forVendor($this->vendor)
            ->create();

        $response = $this->actingAs($this->customer)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/typing", [
                'is_typing' => false,
            ]);

        $response->assertStatus(200);

        Event::assertDispatched(UserTyping::class, function ($event) {
            return $event->isTyping === false;
        });
    }

    // ==================== Unread Count Tests ====================

    public function test_user_can_get_total_unread_count(): void
    {
        $vendor2 = User::factory()->vendor()->create();

        $conversation1 = Conversation::factory()
            ->forCustomer($this->customer)
            ->forVendor($this->vendor)
            ->withUnreadForCustomer(3)
            ->create();

        $conversation2 = Conversation::factory()
            ->forCustomer($this->customer)
            ->forVendor($vendor2)
            ->withUnreadForCustomer(2)
            ->create();

        $response = $this->actingAs($this->customer)
            ->getJson('/api/v1/chat/unread-count');

        $response->assertStatus(200)
            ->assertJsonPath('unread_count', 5);
    }

    // ==================== Authentication Tests ====================

    public function test_unauthenticated_user_cannot_access_chat(): void
    {
        $response = $this->getJson('/api/v1/chat/conversations');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_send_message(): void
    {
        $conversation = Conversation::factory()
            ->forCustomer($this->customer)
            ->forVendor($this->vendor)
            ->create();

        $response = $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
            'body' => 'Test message',
        ]);

        $response->assertStatus(401);
    }

    // ==================== Validation Tests ====================

    public function test_message_body_is_required_without_attachments(): void
    {
        $conversation = Conversation::factory()
            ->forCustomer($this->customer)
            ->forVendor($this->vendor)
            ->create();

        $response = $this->actingAs($this->customer)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['body']);
    }

    public function test_message_body_max_length_validation(): void
    {
        $conversation = Conversation::factory()
            ->forCustomer($this->customer)
            ->forVendor($this->vendor)
            ->create();

        $response = $this->actingAs($this->customer)
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
                'body' => str_repeat('a', 5001),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['body']);
    }

    public function test_vendor_id_required_when_starting_conversation(): void
    {
        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/chat/conversations', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['vendor_id']);
    }

    // ==================== Pagination Tests ====================

    public function test_conversations_are_paginated(): void
    {
        // Create 25 conversations
        for ($i = 0; $i < 25; $i++) {
            $vendor = User::factory()->vendor()->create();
            Conversation::factory()
                ->forCustomer($this->customer)
                ->forVendor($vendor)
                ->create();
        }

        $response = $this->actingAs($this->customer)
            ->getJson('/api/v1/chat/conversations?per_page=10');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 25);
    }

    public function test_messages_are_paginated(): void
    {
        $conversation = Conversation::factory()
            ->forCustomer($this->customer)
            ->forVendor($this->vendor)
            ->create();

        Message::factory()
            ->forConversation($conversation)
            ->fromSender($this->customer)
            ->count(75)
            ->create();

        $response = $this->actingAs($this->customer)
            ->getJson("/api/v1/chat/conversations/{$conversation->id}/messages?per_page=50");

        $response->assertStatus(200)
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('meta.total', 75);
    }

    // ==================== Message Order Tests ====================

    public function test_messages_are_ordered_by_newest_first(): void
    {
        $conversation = Conversation::factory()
            ->forCustomer($this->customer)
            ->forVendor($this->vendor)
            ->create();

        $oldMessage = Message::factory()
            ->forConversation($conversation)
            ->fromSender($this->customer)
            ->create(['created_at' => now()->subHours(2)]);

        $newMessage = Message::factory()
            ->forConversation($conversation)
            ->fromSender($this->vendor)
            ->create(['created_at' => now()]);

        $response = $this->actingAs($this->customer)
            ->getJson("/api/v1/chat/conversations/{$conversation->id}/messages");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals($newMessage->id, $data[0]['id']);
        $this->assertEquals($oldMessage->id, $data[1]['id']);
    }

    public function test_conversations_are_ordered_by_most_recent_message(): void
    {
        $vendor2 = User::factory()->vendor()->create();

        $olderConversation = Conversation::factory()
            ->forCustomer($this->customer)
            ->forVendor($this->vendor)
            ->create(['last_message_at' => now()->subHours(2)]);

        $newerConversation = Conversation::factory()
            ->forCustomer($this->customer)
            ->forVendor($vendor2)
            ->create(['last_message_at' => now()]);

        $response = $this->actingAs($this->customer)
            ->getJson('/api/v1/chat/conversations');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals($newerConversation->id, $data[0]['id']);
        $this->assertEquals($olderConversation->id, $data[1]['id']);
    }
}
