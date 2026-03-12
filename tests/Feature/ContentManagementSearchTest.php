<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Interest;
use App\Models\MusicGenre;
use App\Models\PersonalityTrait;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentManagementSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_filters_categories_by_name(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Category::factory()->create(['name' => 'Electronics']);
        Category::factory()->create(['name' => 'Fashion']);

        $response = $this->get('/dashboard/content-management?search=Elect');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('search', 'Elect')
            ->has('categories.data', 1)
            ->where('categories.data.0.name', 'Electronics')
        );
    }

    public function test_search_filters_interests_by_name(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Interest::factory()->create(['name' => 'Cooking']);
        Interest::factory()->create(['name' => 'Gaming']);

        $response = $this->get('/dashboard/content-management?search=Cook');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('interests.data', 1)
            ->where('interests.data.0.name', 'Cooking')
        );
    }

    public function test_search_filters_personality_traits_by_name(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        PersonalityTrait::factory()->create(['name' => 'Adventurous']);
        PersonalityTrait::factory()->create(['name' => 'Creative']);

        $response = $this->get('/dashboard/content-management?search=Adven');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('personalityTraits.data', 1)
            ->where('personalityTraits.data.0.name', 'Adventurous')
        );
    }

    public function test_search_filters_music_genres_by_name(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        MusicGenre::factory()->create(['name' => 'Jazz']);
        MusicGenre::factory()->create(['name' => 'Rock']);

        $response = $this->get('/dashboard/content-management?search=Jazz');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('musicGenres.data', 1)
            ->where('musicGenres.data.0.name', 'Jazz')
        );
    }

    public function test_empty_search_returns_all_results(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Category::factory()->count(3)->create();

        $response = $this->get('/dashboard/content-management?search=');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('categories.data', 3)
        );
    }

    public function test_search_param_is_returned_in_props(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->get('/dashboard/content-management?search=test');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('search', 'test')
        );
    }
}
