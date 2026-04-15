<?php

namespace Tests\Feature;

use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldAgentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_regions_endpoint_returns_regions_with_cities(): void
    {
        $region = Region::factory()->create(['name' => 'Greater Accra']);
        $region->cities()->create(['name' => 'Accra', 'slug' => 'accra']);
        $region->cities()->create(['name' => 'Tema', 'slug' => 'tema']);

        $response = $this->getJson('/field-agents/regions');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug', 'cities' => ['*' => ['id', 'name', 'slug']]],
                ],
            ])
            ->assertJsonPath('data.0.name', 'Greater Accra')
            ->assertJsonCount(2, 'data.0.cities');
    }
}
