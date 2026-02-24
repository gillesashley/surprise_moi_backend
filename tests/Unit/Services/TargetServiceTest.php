<?php

namespace Tests\Unit\Services;

use App\Models\Earning;
use App\Models\Target;
use App\Models\TargetAchievement;
use App\Models\User;
use App\Services\TargetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TargetServiceTest extends TestCase
{
    use RefreshDatabase;

    private TargetService $targetService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->targetService = new TargetService;
    }

    public function test_creates_target_for_field_agent(): void
    {
        $fieldAgent = User::factory()->create(['role' => 'field_agent']);
        $admin = User::factory()->create(['role' => 'admin']);

        $target = $this->targetService->createTarget(
            user: $fieldAgent,
            targetType: 'vendor_signups',
            targetValue: 10.0,
            baseBonus: 500.0,
            overachievementBonus: 100.0,
            startDate: now(),
            endDate: now()->addMonth(),
            assignedBy: $admin
        );

        $this->assertInstanceOf(Target::class, $target);
        $this->assertEquals($fieldAgent->id, $target->user_id);
        $this->assertEquals('field_agent', $target->user_role);
        $this->assertEquals('vendor_signups', $target->target_type);
        $this->assertEquals(10.0, $target->target_value);
        $this->assertEquals(500.0, $target->bonus_amount);
        $this->assertEquals(Target::STATUS_ACTIVE, $target->status);
    }

    public function test_creates_target_for_marketer(): void
    {
        $marketer = User::factory()->create(['role' => 'marketer']);
        $admin = User::factory()->create(['role' => 'admin']);

        $target = $this->targetService->createTarget(
            user: $marketer,
            targetType: 'revenue_generated',
            targetValue: 10000.0,
            baseBonus: 1000.0,
            overachievementBonus: 200.0,
            startDate: now(),
            endDate: now()->addMonth(),
            assignedBy: $admin
        );

        $this->assertInstanceOf(Target::class, $target);
        $this->assertEquals('marketer', $target->user_role);
    }

    public function test_throws_exception_for_invalid_user_role(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Targets can only be assigned to field agents or marketers.');

        $this->targetService->createTarget(
            user: $customer,
            targetType: 'vendor_signups',
            targetValue: 10.0,
            baseBonus: 500.0,
            overachievementBonus: 100.0,
            startDate: now(),
            endDate: now()->addMonth()
        );
    }

    public function test_updates_target_progress(): void
    {
        $fieldAgent = User::factory()->create(['role' => 'field_agent']);
        $target = Target::factory()->create([
            'user_id' => $fieldAgent->id,
            'user_role' => 'field_agent',
            'target_value' => 10.0,
            'current_value' => 0.0,
            'status' => Target::STATUS_ACTIVE,
        ]);

        $updatedTarget = $this->targetService->updateProgress($target, 5.0);

        $this->assertEquals(5.0, $updatedTarget->current_value);
    }

    public function test_records_achievement_when_target_completed(): void
    {
        $fieldAgent = User::factory()->create(['role' => 'field_agent']);
        $target = Target::factory()->create([
            'user_id' => $fieldAgent->id,
            'user_role' => 'field_agent',
            'target_value' => 10.0,
            'current_value' => 0.0,
            'bonus_amount' => 500.0,
            'status' => Target::STATUS_ACTIVE,
        ]);

        $this->targetService->updateProgress($target, 10.0);

        $target->refresh();
        $this->assertEquals(Target::STATUS_COMPLETED, $target->status);

        $achievement = TargetAchievement::where('target_id', $target->id)->first();
        $this->assertNotNull($achievement);
        $this->assertEquals($fieldAgent->id, $achievement->user_id);
        $this->assertEquals(10.0, $achievement->achieved_value);
    }

    public function test_creates_earnings_for_field_agent_on_completion(): void
    {
        $fieldAgent = User::factory()->create(['role' => 'field_agent']);
        $target = Target::factory()->create([
            'user_id' => $fieldAgent->id,
            'user_role' => 'field_agent',
            'target_value' => 10.0,
            'bonus_amount' => 500.0,
        ]);

        $this->targetService->updateProgress($target, 10.0);

        $earnings = Earning::where('user_id', $fieldAgent->id)->get();
        $this->assertGreaterThan(0, $earnings->count());
        $this->assertEquals(Earning::TYPE_TARGET_BONUS, $earnings->first()->earning_type);
    }

    public function test_creates_earnings_for_marketer_on_completion(): void
    {
        $marketer = User::factory()->create(['role' => 'marketer']);
        $target = Target::factory()->create([
            'user_id' => $marketer->id,
            'user_role' => 'marketer',
            'target_value' => 10000.0,
            'bonus_amount' => 1000.0,
        ]);

        $this->targetService->updateProgress($target, 10000.0);

        $earnings = Earning::where('user_id', $marketer->id)
            ->where('earning_type', Earning::TYPE_SIGN_ON_BONUS)
            ->get();

        $this->assertGreaterThan(0, $earnings->count());
    }

    public function test_gets_user_target_stats(): void
    {
        $fieldAgent = User::factory()->create(['role' => 'field_agent']);

        Target::factory()->count(3)->create([
            'user_id' => $fieldAgent->id,
            'status' => Target::STATUS_ACTIVE,
        ]);
        Target::factory()->count(2)->create([
            'user_id' => $fieldAgent->id,
            'status' => Target::STATUS_COMPLETED,
        ]);

        $stats = $this->targetService->getUserTargetStats($fieldAgent);

        $this->assertEquals(5, $stats['total_targets']);
        $this->assertEquals(3, $stats['active_targets']);
        $this->assertEquals(2, $stats['completed_targets']);
    }

    public function test_expires_old_targets(): void
    {
        Target::factory()->create([
            'status' => Target::STATUS_ACTIVE,
            'end_date' => now()->subDay(),
        ]);
        Target::factory()->create([
            'status' => Target::STATUS_ACTIVE,
            'end_date' => now()->addDay(),
        ]);

        $expiredCount = $this->targetService->expireTargets();

        $this->assertEquals(1, $expiredCount);
        $this->assertEquals(1, Target::where('status', Target::STATUS_EXPIRED)->count());
    }
}
