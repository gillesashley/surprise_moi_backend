<?php

namespace App\Models;

use App\Enums\FieldAgentApplicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;

class FieldAgentApplication extends Model
{
    /** @use HasFactory<\Database\Factories\FieldAgentApplicationFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'contact_number',
        'region_id',
        'city_id',
        'location',
        'ghana_card_number',
        'ghana_card_image_path',
        'selfie_path',
        'password',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'approved_user_id',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'status' => FieldAgentApplicationStatus::class,
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_user_id');
    }

    public function canBeReviewed(): bool
    {
        return in_array($this->status->value, FieldAgentApplicationStatus::reviewableStatuses(), true);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function routeNotificationForSms(): ?string
    {
        return $this->contact_number;
    }

    public function routeNotificationForMail(): string
    {
        return $this->email;
    }
}
