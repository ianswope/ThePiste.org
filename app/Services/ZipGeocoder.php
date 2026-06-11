<?php

namespace App\Services;

use App\Models\ZipCode;
use Illuminate\Support\Facades\Http;

/**
 * Resolves a US ZIP code to lat/lng for the drive-vs-fly math.
 * Cached in the zip_codes table; falls back to the free zippopotam.us API
 * on a miss. Returns null on any failure so callers degrade gracefully.
 */
class ZipGeocoder
{
    /** @return array{lat: float, lng: float, city: ?string, state: ?string}|null */
    public function lookup(?string $raw): ?array
    {
        $zip = substr(preg_replace('/\D/', '', (string) $raw), 0, 5);
        if (strlen($zip) !== 5) {
            return null;
        }

        if ($cached = ZipCode::find($zip)) {
            return ['lat' => $cached->lat, 'lng' => $cached->lng, 'city' => $cached->city, 'state' => $cached->state];
        }

        try {
            $res = Http::timeout(6)->acceptJson()->get("https://api.zippopotam.us/us/{$zip}");
            if (! $res->ok()) {
                return null;
            }
            $place = $res->json('places.0');
            // A 200 with missing or non-numeric coordinates must not cast to
            // (0,0) and cache a point in the Atlantic; treat it as a miss.
            if (! $place || ! is_numeric($place['latitude'] ?? null) || ! is_numeric($place['longitude'] ?? null)) {
                return null;
            }

            $row = [
                'lat' => (float) $place['latitude'],
                'lng' => (float) $place['longitude'],
                'city' => $place['place name'] ?? null,
                'state' => $place['state abbreviation'] ?? null,
            ];
            ZipCode::create(['zip' => $zip] + $row);

            return $row;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
