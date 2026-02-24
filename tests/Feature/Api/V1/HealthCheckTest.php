<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_check_returns_ok_status(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'status',
                    'timestamp',
                    'service',
                    'version',
                    'database',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'ok',
                    'database' => 'connected',
                ],
            ]);
    }
}
