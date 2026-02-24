<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'customer']);
        $this->otherUser = User::factory()->create(['role' => 'customer']);
    }

    // ─── GET /api/v1/report-categories ────────────────────────────────────────

    public function test_authenticated_user_can_get_report_categories(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/report-categories');

        $response->assertOk()
            ->assertJsonStructure([
                'categories' => [
                    '*' => ['value', 'label', 'icon'],
                ],
            ]);

        $this->assertCount(5, $response->json('categories'));
    }

    public function test_unauthenticated_user_cannot_get_report_categories(): void
    {
        $this->getJson('/api/v1/report-categories')
            ->assertUnauthorized();
    }

    // ─── GET /api/v1/reports ──────────────────────────────────────────────────

    public function test_authenticated_user_can_list_own_reports(): void
    {
        Report::factory()->count(3)->create(['user_id' => $this->user->id]);
        Report::factory()->count(2)->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'report_number', 'category', 'description', 'status'],
                ],
                'meta',
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_user_can_filter_reports_by_status(): void
    {
        Report::factory()->pending()->count(2)->create(['user_id' => $this->user->id]);
        Report::factory()->resolved()->count(1)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports?status=pending');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));

        foreach ($response->json('data') as $report) {
            $this->assertEquals('pending', $report['status']);
        }
    }

    public function test_unauthenticated_user_cannot_list_reports(): void
    {
        $this->getJson('/api/v1/reports')
            ->assertUnauthorized();
    }

    // ─── POST /api/v1/reports ─────────────────────────────────────────────────

    public function test_user_can_create_a_report(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/reports', [
                'category' => Report::CATEGORY_ORDER_ISSUE,
                'description' => 'My order has not arrived after 2 weeks.',
            ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'report' => ['id', 'report_number', 'category', 'description', 'status'],
            ]);

        $this->assertDatabaseHas('reports', [
            'user_id' => $this->user->id,
            'category' => Report::CATEGORY_ORDER_ISSUE,
            'status' => Report::STATUS_PENDING,
        ]);
    }

    public function test_report_number_is_auto_generated(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/reports', [
                'category' => Report::CATEGORY_OTHER,
                'description' => 'This is a test report for number generation.',
            ]);

        $response->assertCreated();
        $reportNumber = $response->json('report.report_number');
        $this->assertMatchesRegularExpression('/^REP-\d{8}-\d{4}$/', $reportNumber);
    }

    public function test_user_can_create_report_with_attachments(): void
    {
        Storage::fake('public');

        $file1 = UploadedFile::fake()->create('screenshot1.jpg', 200, 'image/jpeg');
        $file2 = UploadedFile::fake()->create('screenshot2.png', 150, 'image/png');

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/reports', [
                'category' => Report::CATEGORY_PRODUCT_PROBLEM,
                'description' => 'The product arrived damaged with visible cracks.',
                'attachments' => [$file1, $file2],
            ]);

        $response->assertCreated();

        $reportId = $response->json('report.id');
        $this->assertDatabaseCount('report_attachments', 2);
        $this->assertDatabaseHas('report_attachments', ['report_id' => $reportId]);
    }

    public function test_report_creation_requires_category(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/reports', [
                'description' => 'Missing category field test.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);
    }

    public function test_report_creation_requires_description_of_minimum_length(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/reports', [
                'category' => Report::CATEGORY_OTHER,
                'description' => 'Short',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['description']);
    }

    public function test_report_creation_rejects_invalid_category(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/reports', [
                'category' => 'invalid_category',
                'description' => 'This should fail due to invalid category value.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);
    }

    public function test_unauthenticated_user_cannot_create_report(): void
    {
        $this->postJson('/api/v1/reports', [
            'category' => Report::CATEGORY_OTHER,
            'description' => 'This should not work without auth.',
        ])->assertUnauthorized();
    }

    // ─── GET /api/v1/reports/{report} ─────────────────────────────────────────

    public function test_user_can_view_own_report(): void
    {
        $report = Report::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/reports/{$report->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'report' => ['id', 'report_number', 'category', 'description', 'status'],
            ]);

        $this->assertEquals($report->id, $response->json('report.id'));
    }

    public function test_user_cannot_view_another_users_report(): void
    {
        $report = Report::factory()->create(['user_id' => $this->otherUser->id]);

        $this->actingAs($this->user)
            ->getJson("/api/v1/reports/{$report->id}")
            ->assertNotFound();
    }

    public function test_unauthenticated_user_cannot_view_report(): void
    {
        $report = Report::factory()->create(['user_id' => $this->user->id]);

        $this->getJson("/api/v1/reports/{$report->id}")
            ->assertUnauthorized();
    }

    // ─── POST /api/v1/reports/{report}/cancel ────────────────────────────────

    public function test_user_can_cancel_pending_report(): void
    {
        $report = Report::factory()->pending()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/reports/{$report->id}/cancel", [
                'reason' => 'I no longer need assistance with this issue.',
            ]);

        $response->assertOk()
            ->assertJson(['message' => 'Report cancelled successfully.']);

        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => Report::STATUS_CANCELLED,
        ]);
    }

    public function test_user_cannot_cancel_in_progress_report(): void
    {
        $report = Report::factory()->inProgress()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->postJson("/api/v1/reports/{$report->id}/cancel", [
                'reason' => 'Trying to cancel an in-progress report.',
            ])
            ->assertUnprocessable();
    }

    public function test_user_cannot_cancel_resolved_report(): void
    {
        $report = Report::factory()->resolved()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->postJson("/api/v1/reports/{$report->id}/cancel", [
                'reason' => 'Trying to cancel a resolved report.',
            ])
            ->assertUnprocessable();
    }

    public function test_cancellation_requires_a_reason(): void
    {
        $report = Report::factory()->pending()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->postJson("/api/v1/reports/{$report->id}/cancel", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_user_cannot_cancel_another_users_report(): void
    {
        $report = Report::factory()->pending()->create(['user_id' => $this->otherUser->id]);

        $this->actingAs($this->user)
            ->postJson("/api/v1/reports/{$report->id}/cancel", [
                'reason' => 'Trying to cancel someone else report.',
            ])
            ->assertNotFound();
    }

    public function test_unauthenticated_user_cannot_cancel_report(): void
    {
        $report = Report::factory()->pending()->create(['user_id' => $this->user->id]);

        $this->postJson("/api/v1/reports/{$report->id}/cancel", [
            'reason' => 'No auth token provided.',
        ])->assertUnauthorized();
    }
}
