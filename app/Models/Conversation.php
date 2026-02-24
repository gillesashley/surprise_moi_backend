<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'vendor_id',
        'last_message',
        'last_message_at',
        'last_message_sender_id',
        'customer_unread_count',
        'vendor_unread_count',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'customer_unread_count' => 'integer',
            'vendor_unread_count' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function lastMessageSender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_message_sender_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get the other participant in the conversation.
     */
    public function getOtherParticipant(User $user): User
    {
        // Ensure relationships are loaded
        if (! $this->relationLoaded('customer')) {
            $this->load('customer');
        }

        if (! $this->relationLoaded('vendor')) {
            $this->load('vendor');
        }

        // Return the other party: if user is customer, return vendor; otherwise return customer
        return $user->id === $this->customer_id ? $this->vendor : $this->customer;
    }

    /**
     * Check if a user is a participant in this conversation.
     */
    public function hasParticipant(User $user): bool
    {
        return $this->customer_id === $user->id || $this->vendor_id === $user->id;
    }

    /**
     * Get unread count for a specific user.
     */
    public function getUnreadCountFor(User $user): int
    {
        if ($user->id === $this->customer_id) {
            return $this->customer_unread_count ?? 0;
        }

        if ($user->id === $this->vendor_id) {
            return $this->vendor_unread_count ?? 0;
        }

        return 0;
    }

    /**
     * Mark all messages as read for a specific user.
     */
    public function markAsReadFor(User $user): void
    {
        if ($user->id === $this->customer_id) {
            $this->update(['customer_unread_count' => 0]);
        } elseif ($user->id === $this->vendor_id) {
            $this->update(['vendor_unread_count' => 0]);
        }

        // Mark individual messages as read
        $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Increment unread count for the other participant.
     */
    public function incrementUnreadFor(User $sender): void
    {
        if ($sender->id === $this->customer_id) {
            $this->increment('vendor_unread_count');
        } elseif ($sender->id === $this->vendor_id) {
            $this->increment('customer_unread_count');
        }
    }

    /**
     * Find or create a conversation between a customer and vendor.
     */
    public static function findOrCreateBetween(User $customer, User $vendor): self
    {
        return self::firstOrCreate(
            [
                'customer_id' => $customer->id,
                'vendor_id' => $vendor->id,
            ]
        );
    }

    /**
     * Scope to get conversations for a specific user.
     */
    public function scopeForUser($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('customer_id', $user->id)
                ->orWhere('vendor_id', $user->id);
        });
    }

    /**
     * Scope to order by most recent message.
     */
    public function scopeLatestMessage($query)
    {
        return $query->orderByDesc('last_message_at');
    }
}
