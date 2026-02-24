<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => User::factory(),
            'vendor_id' => User::factory()->vendor(),
            'last_message' => $this->faker->optional()->sentence(),
            'last_message_at' => $this->faker->optional()->dateTimeThisMonth(),
            'last_message_sender_id' => null,
            'customer_unread_count' => 0,
            'vendor_unread_count' => 0,
        ];
    }

    /**
     * Set the customer for the conversation.
     */
    public function forCustomer(User $customer): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_id' => $customer->id,
        ]);
    }

    /**
     * Set the vendor for the conversation.
     */
    public function forVendor(User $vendor): static
    {
        return $this->state(fn (array $attributes) => [
            'vendor_id' => $vendor->id,
        ]);
    }

    /**
     * Create a conversation with unread messages for the customer.
     */
    public function withUnreadForCustomer(int $count = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_unread_count' => $count,
        ]);
    }

    /**
     * Create a conversation with unread messages for the vendor.
     */
    public function withUnreadForVendor(int $count = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'vendor_unread_count' => $count,
        ]);
    }

    /**
     * Create a conversation with a last message.
     */
    public function withLastMessage(?string $message = null, ?User $sender = null): static
    {
        return $this->state(function (array $attributes) use ($message, $sender) {
            $senderId = $sender?->id ?? $attributes['customer_id'];

            return [
                'last_message' => $message ?? $this->faker->sentence(),
                'last_message_at' => now(),
                'last_message_sender_id' => $senderId,
            ];
        });
    }
}
