<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleMapsService
{
    protected string $apiKey;

    protected string $placesApiUrl;

    protected string $geocodingApiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.google_maps.api_key') ?? '';
        $this->placesApiUrl = config('services.google_maps.places_api_url', 'https://maps.googleapis.com/maps/api/place');
        $this->geocodingApiUrl = config('services.google_maps.geocoding_api_url', 'https://maps.googleapis.com/maps/api/geocode');

        if (empty($this->apiKey)) {
            Log::warning('Google Maps API key is not configured. Location services will not work.');
        }
    }

    /**
     * Autocomplete places based on search query.
     *
     * @return array{success: bool, data?: array<int, array<string, mixed>>, message?: string}
     */
    public function autocomplete(string $query, ?string $country = null, ?string $language = 'en'): array
    {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'message' => 'Google Maps API key is not configured.',
            ];
        }

        if (empty($query)) {
            return [
                'success' => false,
                'message' => 'Search query is required.',
            ];
        }

        try {
            $params = [
                'input' => $query,
                'key' => $this->apiKey,
                'language' => $language,
            ];

            // Add country restriction if provided (ISO 3166-1 Alpha-2 country code)
            if ($country) {
                $params['components'] = "country:{$country}";
            }

            $response = Http::timeout(10)
                ->get("{$this->placesApiUrl}/autocomplete/json", $params);

            if (! $response->successful()) {
                Log::error('Google Places API autocomplete failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to fetch place suggestions.',
                ];
            }

            $data = $response->json();

            if ($data['status'] !== 'OK' && $data['status'] !== 'ZERO_RESULTS') {
                Log::error('Google Places API error', [
                    'status' => $data['status'],
                    'error_message' => $data['error_message'] ?? null,
                ]);

                return [
                    'success' => false,
                    'message' => $data['error_message'] ?? 'An error occurred while searching for places.',
                ];
            }

            // Transform predictions to simpler format
            $predictions = collect($data['predictions'] ?? [])->map(function ($prediction) {
                return [
                    'place_id' => $prediction['place_id'],
                    'description' => $prediction['description'],
                    'main_text' => $prediction['structured_formatting']['main_text'] ?? null,
                    'secondary_text' => $prediction['structured_formatting']['secondary_text'] ?? null,
                    'types' => $prediction['types'] ?? [],
                ];
            })->toArray();

            return [
                'success' => true,
                'data' => $predictions,
            ];
        } catch (RequestException $e) {
            Log::error('Google Places API request failed', [
                'message' => $e->getMessage(),
                'query' => $query,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to connect to Google Places API.',
            ];
        } catch (\Exception $e) {
            Log::error('Unexpected error in autocomplete', [
                'message' => $e->getMessage(),
                'query' => $query,
            ]);

            return [
                'success' => false,
                'message' => 'An unexpected error occurred.',
            ];
        }
    }

    /**
     * Get place details by place ID.
     *
     * @return array{success: bool, data?: array<string, mixed>, message?: string}
     */
    public function getPlaceDetails(string $placeId, ?string $language = 'en'): array
    {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'message' => 'Google Maps API key is not configured.',
            ];
        }

        try {
            $response = Http::timeout(10)
                ->get("{$this->placesApiUrl}/details/json", [
                    'place_id' => $placeId,
                    'key' => $this->apiKey,
                    'language' => $language,
                    'fields' => 'address_components,formatted_address,geometry,name,place_id,types',
                ]);

            if (! $response->successful()) {
                Log::error('Google Places API details failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to fetch place details.',
                ];
            }

            $data = $response->json();

            if ($data['status'] !== 'OK') {
                Log::error('Google Places API error', [
                    'status' => $data['status'],
                    'error_message' => $data['error_message'] ?? null,
                ]);

                return [
                    'success' => false,
                    'message' => $data['error_message'] ?? 'An error occurred while fetching place details.',
                ];
            }

            $result = $data['result'] ?? [];

            return [
                'success' => true,
                'data' => $this->formatPlaceDetails($result),
            ];
        } catch (RequestException $e) {
            Log::error('Google Places API request failed', [
                'message' => $e->getMessage(),
                'place_id' => $placeId,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to connect to Google Places API.',
            ];
        }
    }

    /**
     * Reverse geocode coordinates to address.
     *
     * @return array{success: bool, data?: array<string, mixed>, message?: string}
     */
    public function reverseGeocode(float $latitude, float $longitude, ?string $language = 'en'): array
    {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'message' => 'Google Maps API key is not configured.',
            ];
        }

        try {
            $response = Http::timeout(10)
                ->get("{$this->geocodingApiUrl}/json", [
                    'latlng' => "{$latitude},{$longitude}",
                    'key' => $this->apiKey,
                    'language' => $language,
                ]);

            if (! $response->successful()) {
                Log::error('Google Geocoding API failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to fetch address from coordinates.',
                ];
            }

            $data = $response->json();

            if ($data['status'] !== 'OK' && $data['status'] !== 'ZERO_RESULTS') {
                Log::error('Google Geocoding API error', [
                    'status' => $data['status'],
                    'error_message' => $data['error_message'] ?? null,
                ]);

                return [
                    'success' => false,
                    'message' => $data['error_message'] ?? 'An error occurred while geocoding.',
                ];
            }

            if (empty($data['results'])) {
                return [
                    'success' => false,
                    'message' => 'No address found for the provided coordinates.',
                ];
            }

            // Return the first (most relevant) result
            $result = $data['results'][0];

            return [
                'success' => true,
                'data' => $this->formatPlaceDetails($result),
            ];
        } catch (RequestException $e) {
            Log::error('Google Geocoding API request failed', [
                'message' => $e->getMessage(),
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to connect to Google Geocoding API.',
            ];
        } catch (\Exception $e) {
            Log::error('Unexpected error in reverse geocode', [
                'message' => $e->getMessage(),
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);

            return [
                'success' => false,
                'message' => 'An unexpected error occurred.',
            ];
        }
    }

    /**
     * Format place details into a structured address.
     *
     * @param  array<string, mixed>  $place
     * @return array<string, mixed>
     */
    protected function formatPlaceDetails(array $place): array
    {
        $addressComponents = $place['address_components'] ?? [];

        $formatted = [
            'place_id' => $place['place_id'] ?? null,
            'formatted_address' => $place['formatted_address'] ?? null,
            'name' => $place['name'] ?? null,
            'latitude' => $place['geometry']['location']['lat'] ?? null,
            'longitude' => $place['geometry']['location']['lng'] ?? null,
            'address_line_1' => null,
            'address_line_2' => null,
            'city' => null,
            'state' => null,
            'postal_code' => null,
            'country' => null,
            'country_code' => null,
        ];

        // Extract address components
        foreach ($addressComponents as $component) {
            $types = $component['types'];

            if (in_array('street_number', $types)) {
                $formatted['address_line_1'] = $component['long_name'];
            }

            if (in_array('route', $types)) {
                $formatted['address_line_1'] = trim(($formatted['address_line_1'] ?? '').' '.$component['long_name']);
            }

            if (in_array('subpremise', $types) || in_array('premise', $types)) {
                $formatted['address_line_2'] = $component['long_name'];
            }

            if (in_array('locality', $types) || in_array('postal_town', $types)) {
                $formatted['city'] = $component['long_name'];
            }

            if (in_array('administrative_area_level_1', $types)) {
                $formatted['state'] = $component['long_name'];
            }

            if (in_array('postal_code', $types)) {
                $formatted['postal_code'] = $component['long_name'];
            }

            if (in_array('country', $types)) {
                $formatted['country'] = $component['long_name'];
                $formatted['country_code'] = $component['short_name'];
            }
        }

        return $formatted;
    }
}
