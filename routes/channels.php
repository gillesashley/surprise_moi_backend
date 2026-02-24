<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Admin channel for vendor application notifications.
 * Only admin users can access.
 */
Broadcast::channel('admin', function ($user) {
    return $user->role === 'admin';
});

/**
 * Personal user channel for individual notifications.
 * Users can only access their own channel.
 */
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

/**
 * Private channel for conversation messages.
 * Only participants (customer or vendor) can access.
 */
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    $conversation = Conversation::find($conversationId);

    if (! $conversation) {
        return false;
    }

    return $conversation->hasParticipant($user);
});
