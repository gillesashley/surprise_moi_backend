<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\FieldAgentApplication;
use App\Models\Region;
use App\Models\User;
use App\Notifications\FieldAgentApplicationReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FieldAgentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Region $region;

    private City $city;

    protected function setUp(): void
    {
        parent::setUp();
        $this->region = Region::factory()->create(['name' => 'Greater Accra']);
        $this->city = City::factory()->create(['region_id' => $this->region->id, 'name' => 'Accra']);
        Storage::fake('r2');
        Notification::fake();
    }

    public function test_registration_page_loads_for_guests(): void
    {
        $this->get('/field-agents/register')->assertOk();
    }

    public function test_regions_endpoint_returns_regions_with_cities(): void
    {
        $response = $this->getJson('/field-agents/regions');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['*' => ['id', 'name', 'slug', 'cities']]])
            ->assertJsonPath('data.0.name', 'Greater Accra');
    }

    public function test_valid_submission_creates_application_and_stores_files(): void
    {
        $payload = $this->validPayload();

        $response = $this->post('/field-agents/register', $payload);

        $response->assertRedirect('/field-agents/register/submitted');

        $app = FieldAgentApplication::where('email', 'kofi@example.com')->first();
        $this->assertNotNull($app);
        $this->assertSame('pending', $app->status->value);
        $this->assertTrue(Hash::check('SuperSecret123', $app->password));
        Storage::disk('r2')->assertExists($app->ghana_card_image_path);
        Storage::disk('r2')->assertExists($app->ghana_card_back_image_path);
        Storage::disk('r2')->assertExists($app->selfie_path);

        Notification::assertSentTo($app, FieldAgentApplicationReceivedNotification::class);
    }

    public function test_password_is_hashed_not_plaintext(): void
    {
        $this->post('/field-agents/register', $this->validPayload());
        $app = FieldAgentApplication::firstOrFail();

        $this->assertNotSame('SuperSecret123', $app->password);
    }

    public function test_honeypot_blocks_silently(): void
    {
        $payload = $this->validPayload(['website' => 'http://spam.example.com']);

        $response = $this->post('/field-agents/register', $payload);

        $response->assertSessionHasErrors('website');
        $this->assertSame(0, FieldAgentApplication::count());
    }

    public function test_rate_limit_blocks_after_20_submissions(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->post('/field-agents/register', $this->validPayload([
                'email' => "user{$i}@ex.com",
                'ghana_card_number' => 'GHA-'.str_pad((string) $i, 9, '0', STR_PAD_LEFT).'-1',
            ]));
        }

        $response = $this->post('/field-agents/register', $this->validPayload([
            'email' => 'overflow@ex.com',
            'ghana_card_number' => 'GHA-999999999-9',
        ]));

        $response->assertStatus(429);
    }

    public function test_validation_rejects_each_missing_required_field(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        foreach (['first_name', 'last_name', 'email', 'contact_number', 'region_id', 'city_id', 'location', 'ghana_card_number', 'ghana_card_image', 'ghana_card_back_image', 'selfie', 'password'] as $field) {
            $payload = $this->validPayload();
            unset($payload[$field]);

            $this->post('/field-agents/register', $payload)->assertSessionHasErrors($field);
        }
    }

    public function test_validation_rejects_invalid_ghana_card_format(): void
    {
        $this->post('/field-agents/register', $this->validPayload(['ghana_card_number' => 'ABC-123']))
            ->assertSessionHasErrors('ghana_card_number');
    }

    public function test_validation_rejects_invalid_phone_format(): void
    {
        $this->post('/field-agents/register', $this->validPayload(['contact_number' => '12345']))
            ->assertSessionHasErrors('contact_number');
    }

    public function test_validation_rejects_non_image_upload(): void
    {
        $payload = $this->validPayload(['ghana_card_image' => UploadedFile::fake()->create('doc.pdf', 200, 'application/pdf')]);

        $this->post('/field-agents/register', $payload)->assertSessionHasErrors('ghana_card_image');
    }

    public function test_validation_rejects_oversized_upload(): void
    {
        $payload = $this->validPayload(['selfie' => $this->fakeJpeg('big.jpg', 6000)]);

        $this->post('/field-agents/register', $payload)->assertSessionHasErrors('selfie');
    }

    public function test_validation_rejects_city_from_wrong_region(): void
    {
        $otherRegion = Region::factory()->create();
        $foreignCity = City::factory()->create(['region_id' => $otherRegion->id]);

        $this->post('/field-agents/register', $this->validPayload(['city_id' => $foreignCity->id]))
            ->assertSessionHasErrors('city_id');
    }

    public function test_validation_rejects_email_that_exists_on_users_table(): void
    {
        User::factory()->create(['email' => 'kofi@example.com']);

        $this->post('/field-agents/register', $this->validPayload())->assertSessionHasErrors('email');
    }

    public function test_validation_rejects_duplicate_pending_email(): void
    {
        FieldAgentApplication::factory()->pending()->create(['email' => 'kofi@example.com']);

        $this->post('/field-agents/register', $this->validPayload())->assertSessionHasErrors('email');
    }

    public function test_rejected_application_does_not_block_resubmission(): void
    {
        FieldAgentApplication::factory()->rejected()->create(['email' => 'kofi@example.com']);

        $this->post('/field-agents/register', $this->validPayload())->assertRedirect('/field-agents/register/submitted');
    }

    public function test_duplicate_ghana_card_against_non_rejected_blocked(): void
    {
        FieldAgentApplication::factory()->pending()->create(['ghana_card_number' => 'GHA-123456789-1']);

        $this->post('/field-agents/register', $this->validPayload(['ghana_card_number' => 'GHA-123456789-1']))
            ->assertSessionHasErrors('ghana_card_number');
    }

    public function test_is_team_defaults_to_false_when_omitted(): void
    {
        $this->post('/field-agents/register', $this->validPayload());

        $app = FieldAgentApplication::firstOrFail();
        $this->assertFalse($app->is_team);
    }

    public function test_is_team_persists_when_true(): void
    {
        $this->post('/field-agents/register', $this->validPayload(['is_team' => '1']));

        $app = FieldAgentApplication::firstOrFail();
        $this->assertTrue($app->is_team);
    }

    public function test_is_team_rejects_non_boolean_values(): void
    {
        $this->post('/field-agents/register', $this->validPayload(['is_team' => 'banana']))
            ->assertSessionHasErrors('is_team');
    }

    /**
     * Build a real minimal JPEG UploadedFile without requiring GD.
     * The base64 payload below is a valid 1x1 JPEG; we optionally pad
     * to hit a target KB size for oversized-file tests.
     */
    private function fakeJpeg(string $name, ?int $sizeKb = null): UploadedFile
    {
        $jpegBytes = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD3+iiigD//2Q==');
        $path = tempnam(sys_get_temp_dir(), 'famg_').'.jpg';
        file_put_contents($path, $jpegBytes);
        if ($sizeKb !== null && filesize($path) < $sizeKb * 1024) {
            file_put_contents($path, str_repeat("\x00", ($sizeKb * 1024) - filesize($path)), FILE_APPEND);
        }

        return new UploadedFile($path, $name, 'image/jpeg', null, true);
    }

    /** @param  array<string, mixed>  $overrides */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Kofi',
            'last_name' => 'Mensah',
            'email' => 'kofi@example.com',
            'contact_number' => '0551234567',
            'region_id' => $this->region->id,
            'city_id' => $this->city->id,
            'location' => 'Osu, near Koala',
            'ghana_card_number' => 'GHA-987654321-2',
            'ghana_card_image' => $this->fakeJpeg('ghana_card_front.jpg'),
            'ghana_card_back_image' => $this->fakeJpeg('ghana_card_back.jpg'),
            'selfie' => $this->fakeJpeg('selfie.jpg'),
            'password' => 'SuperSecret123',
            'password_confirmation' => 'SuperSecret123',
            'website' => '',
        ], $overrides);
    }
}
