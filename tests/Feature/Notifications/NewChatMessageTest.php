<?php

namespace Tests\Feature\Notifications;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NewChatMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipient_is_notified_when_message_is_sent(): void
    {
        Notification::fake();

        $customer = User::factory()->create(['role' => 'customer']);
        $vendor = User::factory()->create(['role' => 'vendor']);

        $conversation = Conversation::findOrCreateBetween($customer, $vendor);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $customer->id,
            'body' => 'Hello, I have a question about your product.',
            'type' => 'text',
        ]);

        Notification::assertSentTo($vendor, NewChatMessage::class, function (NewChatMessage $notification) use ($customer) {
            return $notification->sender->id === $customer->id;
        });
    }

    public function test_notification_message_is_truncated(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $vendor = User::factory()->create(['role' => 'vendor']);

        $conversation = Conversation::findOrCreateBetween($customer, $vendor);

        $longBody = str_repeat('A', 200);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $customer->id,
            'body' => $longBody,
            'type' => 'text',
        ]);

        $notification = new NewChatMessage($customer, $message);
        $data = $notification->toDatabase($vendor);

        // The message should be truncated: "SenderName: " + 80 chars + "..."
        // Total must be shorter than the full body
        $this->assertLessThan(
            strlen("{$customer->name}: {$longBody}"),
            strlen($data['message'])
        );
        $this->assertStringContainsString('...', $data['message']);
    }

    public function test_notification_data_has_correct_shape(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $vendor = User::factory()->create(['role' => 'vendor']);

        $conversation = Conversation::findOrCreateBetween($customer, $vendor);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $customer->id,
            'body' => 'Hello vendor!',
            'type' => 'text',
        ]);

        $notification = new NewChatMessage($customer, $message);
        $data = $notification->toDatabase($vendor);

        $this->assertSame('new_chat_message', $data['type']);
        $this->assertSame('New Message', $data['title']);
        $this->assertSame("{$customer->name}: Hello vendor!", $data['message']);
        $this->assertSame("/conversations/{$conversation->id}", $data['action_url']);

        $this->assertArrayHasKey('actor', $data);
        $this->assertSame($customer->id, $data['actor']['id']);
        $this->assertSame($customer->name, $data['actor']['name']);
        $this->assertSame($customer->avatar, $data['actor']['avatar']);

        $this->assertArrayHasKey('subject', $data);
        $this->assertSame($message->id, $data['subject']['id']);
        $this->assertSame('message', $data['subject']['type']);
        $this->assertSame($conversation->id, $data['subject']['conversation_id']);
    }
}
