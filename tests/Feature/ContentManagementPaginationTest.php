<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentManagementPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_management_page_loads_for_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->get('/dashboard/content-management');

        $response->assertOk();
    }

    public function test_content_management_pagination_with_tab_and_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->get('/dashboard/content-management?tab=categories&categories_page=1');

        $response->assertOk();
    }

    public function test_content_management_pagination_interests(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->get('/dashboard/content-management?tab=interests&interests_page=1');

        $response->assertOk();
    }

    public function test_content_management_pagination_traits(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->get('/dashboard/content-management?tab=traits&traits_page=1');

        $response->assertOk();
    }

    public function test_content_management_pagination_music(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->get('/dashboard/content-management?tab=music&music_page=1');

        $response->assertOk();
    }

    public function test_content_management_pagination_bespoke(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->get('/dashboard/content-management?tab=bespoke&bespoke_page=1');

        $response->assertOk();
    }

    public function test_content_management_without_dashboard_prefix_returns_404(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->get('/content-management');

        $response->assertNotFound();
    }
}
