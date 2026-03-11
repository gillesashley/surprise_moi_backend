<?php

namespace Tests\Feature\Rider;

use App\Models\Rider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RiderOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_rider_can_submit_documents(): void
    {
        Storage::fake('s3');
        $rider = Rider::factory()->pending()->create();
        $token = $rider->createToken('rider-app')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/rider/v1/onboarding/documents', [
                'ghana_card_front' => UploadedFile::fake()->create('front.jpg', 100, 'image/jpeg'),
                'ghana_card_back' => UploadedFile::fake()->create('back.jpg', 100, 'image/jpeg'),
                'drivers_license' => UploadedFile::fake()->create('license.jpg', 100, 'image/jpeg'),
                'vehicle_photo' => UploadedFile::fake()->create('vehicle.jpg', 100, 'image/jpeg'),
                'vehicle_type' => 'Honda CG 125',
                'license_plate' => 'GR-1234-21',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('riders', [
            'id' => $rider->id,
            'status' => 'under_review',
        ]);
    }

    public function test_rider_can_check_onboarding_status(): void
    {
        $rider = Rider::factory()->underReview()->create();
        $token = $rider->createToken('rider-app')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/rider/v1/onboarding/status');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => ['status' => 'under_review'],
            ]);
    }

    public function test_approved_rider_cannot_submit_documents(): void
    {
        $rider = Rider::factory()->approved()->create();
        $token = $rider->createToken('rider-app')->plainTextToken;

        Storage::fake('s3');
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/rider/v1/onboarding/documents', [
                'ghana_card_front' => UploadedFile::fake()->create('front.jpg', 100, 'image/jpeg'),
                'ghana_card_back' => UploadedFile::fake()->create('back.jpg', 100, 'image/jpeg'),
                'drivers_license' => UploadedFile::fake()->create('license.jpg', 100, 'image/jpeg'),
                'vehicle_photo' => UploadedFile::fake()->create('vehicle.jpg', 100, 'image/jpeg'),
                'vehicle_type' => 'Honda CG 125',
                'license_plate' => 'GR-1234-21',
            ]);

        $response->assertStatus(403);
    }

    public function test_rejected_rider_can_resubmit_documents(): void
    {
        Storage::fake('s3');
        $rider = Rider::factory()->rejected()->create();
        $token = $rider->createToken('rider-app')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/rider/v1/onboarding/documents', [
                'ghana_card_front' => UploadedFile::fake()->create('front.jpg', 100, 'image/jpeg'),
                'ghana_card_back' => UploadedFile::fake()->create('back.jpg', 100, 'image/jpeg'),
                'drivers_license' => UploadedFile::fake()->create('license.jpg', 100, 'image/jpeg'),
                'vehicle_photo' => UploadedFile::fake()->create('vehicle.jpg', 100, 'image/jpeg'),
                'vehicle_type' => 'Honda CG 125',
                'license_plate' => 'GR-1234-21',
            ]);

        $response->assertOk();
    }

    public function test_non_rejected_rider_cannot_resubmit(): void
    {
        $rider = Rider::factory()->pending()->create();
        $token = $rider->createToken('rider-app')->plainTextToken;

        Storage::fake('s3');
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/rider/v1/onboarding/documents', [
                'ghana_card_front' => UploadedFile::fake()->create('front.jpg', 100, 'image/jpeg'),
                'ghana_card_back' => UploadedFile::fake()->create('back.jpg', 100, 'image/jpeg'),
                'drivers_license' => UploadedFile::fake()->create('license.jpg', 100, 'image/jpeg'),
                'vehicle_photo' => UploadedFile::fake()->create('vehicle.jpg', 100, 'image/jpeg'),
                'vehicle_type' => 'Honda CG 125',
                'license_plate' => 'GR-1234-21',
            ]);

        $response->assertStatus(403);
    }
}
