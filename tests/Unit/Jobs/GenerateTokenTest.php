<?php

namespace Tests\Unit\Jobs;

use App\Jobs\GenerateToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GenerateTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_token_has_correct_defaults(): void
    {
        $job = new GenerateToken(
            userId: 1,
            tokenType: GenerateToken::TYPE_PASSWORD_RESET,
            metadata: ['email' => 'test@example.com']
        );

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(60, $job->timeout);
        $this->assertEquals([60, 180, 300], $job->backoff);
    }

    public function test_generate_token_uses_tokens_queue(): void
    {
        $job = new GenerateToken(
            userId: 1,
            tokenType: GenerateToken::TYPE_API_TOKEN
        );

        $this->assertEquals('tokens', $job->queue);
    }

    public function test_generate_password_reset_token_creates_record(): void
    {
        $user = \App\Models\User::factory()->create([
            'email' => 'test@example.com'
        ]);

        $job = new GenerateToken(
            userId: $user->id,
            tokenType: GenerateToken::TYPE_PASSWORD_RESET,
            metadata: ['email' => $user->email]
        );

        $job->executeJob();

        $this->assertNotNull($job->getGeneratedToken());
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $user->email,
        ]);
    }

    public function test_generate_token_types_are_defined(): void
    {
        $this->assertEquals('password_reset', GenerateToken::TYPE_PASSWORD_RESET);
        $this->assertEquals('api_token', GenerateToken::TYPE_API_TOKEN);
        $this->assertEquals('email_verification', GenerateToken::TYPE_EMAIL_VERIFICATION);
        $this->assertEquals('refresh_token', GenerateToken::TYPE_REFRESH_TOKEN);
    }

    public function test_mask_token_shows_first_and_last_chars(): void
    {
        $job = new GenerateToken(
            userId: 1,
            tokenType: GenerateToken::TYPE_PASSWORD_RESET
        );

        $masked = $job->maskToken('abc123def456');

        $this->assertEquals('abc***456', $masked);
    }

    public function test_mask_token_returns_asterisks_for_short_token(): void
    {
        $job = new GenerateToken(
            userId: 1,
            tokenType: GenerateToken::TYPE_PASSWORD_RESET
        );

        $masked = $job->maskToken('abc');

        $this->assertEquals('***', $masked);
    }
}
