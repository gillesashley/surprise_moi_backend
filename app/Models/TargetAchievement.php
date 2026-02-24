<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class TargetAchievement extends Model
{
    /** @use HasFactory<\Database\Factories\TargetAchievementFactory> */
    use HasFactory;

    protected $fillable = [
        'target_id',
        'user_id',
        'achieved_value',
        'bonus_earned',
        'overachievement_bonus',
        'total_earned',
        'completion_percentage',
        'achieved_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'achieved_value' => 'decimal:2',
            'bonus_earned' => 'decimal:2',
            'overachievement_bonus' => 'decimal:2',
            'total_earned' => 'decimal:2',
            'completion_percentage' => 'integer',
            'achieved_at' => 'datetime',
        ];
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Target::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function earnings(): MorphMany
    {
        return $this->morphMany(Earning::class, 'earnable');
    }
}
