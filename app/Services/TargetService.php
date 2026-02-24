<?php

namespace App\Services;

use App\Models\Earning;
use App\Models\Target;
use App\Models\TargetAchievement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TargetService
{
    /**
     * Create a new target for a user.
     */
    public function createTarget(
        User $user,
        string $targetType,
        float $targetValue,
        float $baseBonus,
        float $overachievementBonus,
        string|\DateTime $startDate,
        string|\DateTime $endDate,
        ?User $assignedBy = null
    ): Target {
        if (! in_array($user->role, ['field_agent', 'marketer'])) {
            throw new \InvalidArgumentException('Targets can only be assigned to field agents or marketers.');
        }

        if (is_string($startDate)) {
            $startDate = new \DateTime($startDate);
        }

        if (is_string($endDate)) {
            $endDate = new \DateTime($endDate);
        }

        // Calculate overachievement rate as a percentage
        $overachievementRate = $targetValue > 0 ? ($overachievementBonus / $targetValue) * 100 : 0;

        return Target::create([
            'user_id' => $user->id,
            'assigned_by' => $assignedBy?->id,
            'user_role' => $user->role,
            'target_type' => $targetType,
            'target_value' => $targetValue,
            'bonus_amount' => $baseBonus,
            'overachievement_rate' => $overachievementRate,
            'period_type' => 'custom',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => Target::STATUS_ACTIVE,
        ]);
    }

    /**
     * Update target progress.
     */
    public function updateProgress(Target $target, float $newValue): Target
    {
        $target->updateProgress($newValue);

        if ($target->status === Target::STATUS_COMPLETED) {
            $this->recordAchievement($target);
        }

        return $target->fresh();
    }

    /**
     * Record target achievement and create earnings.
     */
    public function recordAchievement(Target $target): TargetAchievement
    {
        return DB::transaction(function () use ($target) {
            $totalBonus = $target->calculateTotalBonus();
            $bonusEarned = (float) $target->bonus_amount;
            $overachievementBonus = $totalBonus - $bonusEarned;

            $achievement = TargetAchievement::create([
                'target_id' => $target->id,
                'user_id' => $target->user_id,
                'achieved_value' => $target->current_value,
                'bonus_earned' => $bonusEarned,
                'overachievement_bonus' => $overachievementBonus,
                'total_earned' => $totalBonus,
                'completion_percentage' => $target->getCompletionPercentage(),
                'achieved_at' => now(),
            ]);

            // For marketers, create sign-on bonus earnings
            if ($target->user_role === 'marketer') {
                Earning::create([
                    'user_id' => $target->user_id,
                    'user_role' => 'marketer',
                    'earning_type' => Earning::TYPE_SIGN_ON_BONUS,
                    'earnable_id' => $achievement->id,
                    'earnable_type' => TargetAchievement::class,
                    'amount' => $totalBonus,
                    'currency' => 'GHS',
                    'status' => Earning::STATUS_PENDING,
                    'description' => "Sign-on bonus for achieving target: {$target->target_type}",
                    'earned_at' => now(),
                ]);
            } else {
                // For field agents, create separate bonus earnings
                Earning::create([
                    'user_id' => $target->user_id,
                    'user_role' => 'field_agent',
                    'earning_type' => Earning::TYPE_TARGET_BONUS,
                    'earnable_id' => $achievement->id,
                    'earnable_type' => TargetAchievement::class,
                    'amount' => $bonusEarned,
                    'currency' => 'GHS',
                    'status' => Earning::STATUS_PENDING,
                    'description' => "Target bonus for achieving: {$target->target_type}",
                    'earned_at' => now(),
                ]);

                if ($overachievementBonus > 0) {
                    Earning::create([
                        'user_id' => $target->user_id,
                        'user_role' => 'field_agent',
                        'earning_type' => Earning::TYPE_OVERACHIEVEMENT_BONUS,
                        'earnable_id' => $achievement->id,
                        'earnable_type' => TargetAchievement::class,
                        'amount' => $overachievementBonus,
                        'currency' => 'GHS',
                        'status' => Earning::STATUS_PENDING,
                        'description' => "Overachievement bonus for exceeding target: {$target->target_type}",
                        'earned_at' => now(),
                    ]);
                }
            }

            return $achievement;
        });
    }

    /**
     * Get target statistics for a user.
     */
    public function getUserTargetStats(User $user): array
    {
        $targets = Target::where('user_id', $user->id)->get();

        return [
            'total_targets' => $targets->count(),
            'active_targets' => $targets->where('status', Target::STATUS_ACTIVE)->count(),
            'completed_targets' => $targets->where('status', Target::STATUS_COMPLETED)->count(),
            'expired_targets' => $targets->where('status', Target::STATUS_EXPIRED)->count(),
            'total_bonus_earned' => TargetAchievement::where('user_id', $user->id)->sum('total_earned'),
        ];
    }

    /**
     * Expire targets that have passed their end date.
     */
    public function expireTargets(): int
    {
        $expired = Target::where('status', Target::STATUS_ACTIVE)
            ->where('end_date', '<', now())
            ->get();

        foreach ($expired as $target) {
            $target->update(['status' => Target::STATUS_EXPIRED]);
        }

        return $expired->count();
    }
}
