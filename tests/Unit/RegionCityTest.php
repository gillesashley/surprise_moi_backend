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

    public function test_region_has_many_cities(): void
    {
        $region = Region::factory()->create();
        \App\Models\City::factory()->count(3)->create(['region_id' => $region->id]);

        $this->assertCount(3, $region->cities);
    }

    public function test_city_belongs_to_region(): void
    {
        $city = \App\Models\City::factory()->create();

        $this->assertInstanceOf(Region::class, $city->region);
    }
}
