<?php

namespace App\Services\Booking;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Turns a place name into coordinates.
 *
 * Photon (OpenStreetMap) is free and needs no API key. Results are cached for
 * a day because a photographer searches the same handful of locations over
 * and over, and the public instance asks clients to be gentle.
 */
class LocationSearch
{
    /**
     * @return array<int, array{label: string, latitude: float, longitude: float}>
     */
    public function search(string $query): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 3) {
            return [];
        }

        $key = 'geocode:search:'.md5(mb_strtolower($query));

        return Cache::remember($key, now()->addDay(), function () use ($query) {
            $response = Http::timeout(5)
                ->withHeaders(['User-Agent' => config('app.name').' booking location search'])
                ->get((string) config('bookings.geocoding_url'), [
                    'q' => $query,
                    'limit' => 5,
                    'lang' => app()->getLocale(),
                ]);

            if (! $response->successful()) {
                return [];
            }

            return collect($response->json('features', []))
                ->map(fn (array $feature) => $this->fromFeature($feature))
                ->filter()
                ->values()
                ->all();
        });
    }

    /**
     * Label of the place at some coordinates, used by "use my location".
     *
     * @return array{label: string, latitude: float, longitude: float}|null
     */
    public function reverse(float $latitude, float $longitude): ?array
    {
        $key = 'geocode:reverse:'.md5($latitude.','.$longitude);

        return Cache::remember($key, now()->addDay(), function () use ($latitude, $longitude) {
            $response = Http::timeout(5)
                ->withHeaders(['User-Agent' => config('app.name').' booking location search'])
                ->get((string) config('bookings.geocoding_reverse_url'), [
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'lang' => app()->getLocale(),
                ]);

            if (! $response->successful()) {
                return null;
            }

            $feature = $response->json('features.0');

            return is_array($feature) ? $this->fromFeature($feature) : null;
        });
    }

    /**
     * @param  array<string, mixed>  $feature
     * @return array{label: string, latitude: float, longitude: float}|null
     */
    private function fromFeature(array $feature): ?array
    {
        $coordinates = $feature['geometry']['coordinates'] ?? null;

        if (! is_array($coordinates) || count($coordinates) < 2) {
            return null;
        }

        $properties = $feature['properties'] ?? [];

        $label = collect([
            $properties['name'] ?? null,
            $properties['city'] ?? null,
            $properties['country'] ?? null,
        ])->filter()->unique()->implode(', ');

        if ($label === '') {
            return null;
        }

        return [
            'label' => $label,
            'latitude' => (float) $coordinates[1],
            'longitude' => (float) $coordinates[0],
        ];
    }
}
