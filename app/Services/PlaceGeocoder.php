<?php

namespace App\Services;

use App\Models\GeoPlace;
use App\Models\Tournament;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Resolves "City, ST" to lat/lng for imported tournaments.
 *
 * Lookup order: existing tournaments with the same venue (free), the
 * geo_places cache, then OpenStreetMap Nominatim (cached on success).
 * Returns null on failure so imports degrade gracefully — the event still
 * lands, it just can't contribute to drive/fly math until located.
 */
class PlaceGeocoder
{
    /** @return array{lat: float, lng: float}|null */
    public function lookup(?string $city, ?string $state): ?array
    {
        $city = trim((string) $city);
        $state = strtoupper(trim((string) $state));
        if ($city === '' || strlen($state) !== 2) {
            return null;
        }

        // Reuse coordinates we already trust from the catalog.
        $known = Tournament::where('city', $city)->where('state', $state)
            ->whereNotNull('lat')->whereNotNull('lng')->first();
        if ($known) {
            return ['lat' => $known->lat, 'lng' => $known->lng];
        }

        $cached = GeoPlace::where('city', $city)->where('state', $state)->first();
        if ($cached) {
            return ['lat' => $cached->lat, 'lng' => $cached->lng];
        }

        try {
            // Nominatim usage policy: at most 1 request/second. Bulk imports
            // hit this path once per *new* city, so pace each API call.
            Sleep::for(1100)->milliseconds();

            $res = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'ThePiste/1.0 (thepiste.org; ian@promoeqp.com)'])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => "{$city}, {$state}, USA",
                    'format' => 'json',
                    'limit' => 1,
                ]);

            $hit = $res->ok() ? $res->json('0') : null;
            // Guard against a 200 with missing/non-numeric coordinates casting
            // to (0,0) and caching a bad point.
            if (! $hit || ! is_numeric($hit['lat'] ?? null) || ! is_numeric($hit['lon'] ?? null)) {
                return null;
            }

            $row = ['lat' => (float) $hit['lat'], 'lng' => (float) $hit['lon']];
            GeoPlace::create(['city' => $city, 'state' => $state] + $row);

            return $row;
        } catch (\Throwable) {
            return null;
        }
    }
}
