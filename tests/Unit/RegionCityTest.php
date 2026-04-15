<?php

namespace Tests\Unit;

use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegionCityTest extends TestCase
{
    use RefreshDatabase;

    public function test_region_can_be_created_from_factory(): void
    {
        $region = Region::factory()->create();

        $this->assertDatabaseHas('regions', ['id' => $region->id]);
        $this->assertNotEmpty($region->slug);
    }
}
