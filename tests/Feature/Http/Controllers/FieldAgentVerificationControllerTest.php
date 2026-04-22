<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\FieldAgentApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FieldAgentVerificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_enriched_verification_data()
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'field_agent']);

        $application = FieldAgentApplication::factory()->create([
            'approved_user_id' => $user->id,
            'reviewed_at' => now(),
        ]);

        // We need to make sure the route exists and is named correctly
        // Looking at the controller, it seems to be field-agent.verification
        $response = $this->actingAs($user)->get(route('field-agent.verification'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('field-agent/verification')
            ->has('application', fn (Assert $page) => $page
                ->has('created_at_formatted')
                ->has('reviewed_at_formatted')
                ->where('created_at_formatted', $application->created_at->format('M d, Y'))
                ->where('reviewed_at_formatted', $application->reviewed_at->format('M d, Y'))
                ->etc()
            )
        );
    }
}
