<?php

namespace Database\Factories;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    protected $model = SupportTicket::class;

    public function definition(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);

        return [
            'subject' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'category' => fake()->randomElement(SupportTicket::CATEGORIES),
            'priority' => SupportTicket::PRIORITY_NORMAL,
            'status' => SupportTicket::STATUS_OPEN,
            'user_id' => null,
            'contact_name' => fake()->name(),
            'contact_phone' => '+233'.fake()->numerify('#########'),
            'contact_email' => fake()->safeEmail(),
            'assigned_to' => $admin->id,
            'created_by' => $admin->id,
        ];
    }

    public function closed(): self
    {
        return $this->state(fn () => [
            'status' => SupportTicket::STATUS_CLOSED,
            'closure_note' => 'Resolved by phone.',
            'closed_at' => now(),
        ]);
    }

    public function forUser(User $user): self
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
            'contact_name' => $user->name,
            'contact_phone' => $user->phone,
            'contact_email' => $user->email,
        ]);
    }
}
