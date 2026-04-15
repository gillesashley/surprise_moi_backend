<?php

namespace Tests\Unit;

use App\Enums\FieldAgentApplicationStatus;
use App\Models\FieldAgentApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldAgentApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_pending_application(): void
    {
        $app = FieldAgentApplication::factory()->create();

        $this->assertSame(FieldAgentApplicationStatus::Pending, $app->status);
        $this->assertNotNull($app->password);
    }

    public function test_can_be_reviewed_when_pending_or_under_review(): void
    {
        $pending = FieldAgentApplication::factory()->pending()->create();
        $underReview = FieldAgentApplication::factory()->underReview()->create();
        $approved = FieldAgentApplication::factory()->approved()->create();
        $rejected = FieldAgentApplication::factory()->rejected()->create();

        $this->assertTrue($pending->canBeReviewed());
        $this->assertTrue($underReview->canBeReviewed());
        $this->assertFalse($approved->canBeReviewed());
        $this->assertFalse($rejected->canBeReviewed());
    }

    public function test_route_notification_for_sms_returns_contact_number(): void
    {
        $app = FieldAgentApplication::factory()->create(['contact_number' => '+233555123456']);

        $this->assertSame('+233555123456', $app->routeNotificationForSms());
    }

    public function test_full_name_concatenates_first_and_last(): void
    {
        $app = FieldAgentApplication::factory()->create([
            'first_name' => 'Kofi',
            'last_name' => 'Mensah',
        ]);

        $this->assertSame('Kofi Mensah', $app->fullName());
    }

    public function test_password_hidden_from_array(): void
    {
        $app = FieldAgentApplication::factory()->create();

        $this->assertArrayNotHasKey('password', $app->toArray());
    }
}
