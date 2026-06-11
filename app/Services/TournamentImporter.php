<?php

namespace App\Services;

use App\Models\Club;
use App\Models\Season;
use App\Models\Tournament;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * The single ingestion path for the tournament catalog. Upserts by slug
 * (name + start date) so re-importing updates in place; rows that fail
 * validation are reported, not fatal. Fed by the admin CSV upload and the
 * AskFRED sync alike. Missing lat/lng is geocoded via PlaceGeocoder.
 */
class TournamentImporter
{
    public const COLUMNS = [
        'name', 'starts_on', 'ends_on', 'city', 'state', 'region',
        'is_nac', 'circuits', 'contested_events', 'host_club',
        'curated_note', 'source_url', 'lat', 'lng',
        // optional: stable id at the source (set by the AskFRED sync) — rows
        // matching an existing external_id update in place even if dates moved
        'external_id',
        // optional: sanctioning level (regional|national|fie_cadet|fie_junior|fie_senior)
        // and ISO country code; default regional / US
        'level', 'country',
    ];

    private const REQUIRED = ['name', 'starts_on', 'ends_on', 'city', 'region', 'contested_events'];

    public function __construct(private PlaceGeocoder $geocoder) {}

    /**
     * @return array{created: int, updated: int, geocoded: int, errors: string[]}
     */
    public function importCsv(string $csv, Season $season): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'geocoded' => 0, 'errors' => []];

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $csv);
        rewind($stream);

        $header = fgetcsv($stream);
        if ($header === false) {
            fclose($stream);
            $summary['errors'][] = 'File is empty.';

            return $summary;
        }

        // Normalize headers (strip BOM, lowercase, underscores).
        $header = array_map(fn ($h) => Str::of((string) $h)->replace("\u{FEFF}", '')->trim()->lower()->replace([' ', '-'], '_')->toString(), $header);

        $missing = array_diff(self::REQUIRED, $header);
        if ($missing !== []) {
            fclose($stream);
            $summary['errors'][] = 'Missing required columns: '.implode(', ', $missing).'.';

            return $summary;
        }

        $line = 1;
        while (($values = fgetcsv($stream)) !== false) {
            $line++;
            if ($values === [null] || $values === ['']) {
                continue; // blank line
            }

            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = isset($values[$i]) ? trim((string) $values[$i]) : '';
            }

            try {
                $result = $this->upsertRow($row, $season, $summary['geocoded']);
                $summary[$result]++;
            } catch (\InvalidArgumentException $e) {
                $summary['errors'][] = "Line {$line}: {$e->getMessage()}";
            }
        }
        fclose($stream);

        return $summary;
    }

    /**
     * Validate and upsert one catalog row (string fields, COLUMNS keys).
     *
     * @return 'created'|'updated'
     *
     * @throws \InvalidArgumentException on a bad row
     */
    public function upsertRow(array $row, Season $season, int &$geocoded): string
    {
        foreach (self::REQUIRED as $key) {
            if (($row[$key] ?? '') === '') {
                throw new \InvalidArgumentException("missing {$key}");
            }
        }

        try {
            $starts = Carbon::parse($row['starts_on'])->startOfDay();
            $ends = Carbon::parse($row['ends_on'])->startOfDay();
        } catch (\Throwable) {
            throw new \InvalidArgumentException('unparseable date (use YYYY-MM-DD)');
        }
        if ($ends->lt($starts)) {
            throw new \InvalidArgumentException('ends_on is before starts_on');
        }

        $country = strtoupper($row['country'] ?? '') ?: 'US';
        $state = strtoupper($row['state'] ?? '');
        if ($country === 'US' && strlen($state) !== 2) {
            throw new \InvalidArgumentException('state must be a 2-letter code for US events');
        }

        $events = $this->splitList($row['contested_events']);
        if ($events === []) {
            throw new \InvalidArgumentException('contested_events is empty');
        }

        $lat = is_numeric($row['lat'] ?? '') ? (float) $row['lat'] : null;
        $lng = is_numeric($row['lng'] ?? '') ? (float) $row['lng'] : null;
        // The geocoder is US-only; international rows supply lat/lng in the CSV.
        if (($lat === null || $lng === null) && $country === 'US') {
            if ($geo = $this->geocoder->lookup($row['city'], $state)) {
                [$lat, $lng] = [$geo['lat'], $geo['lng']];
                $geocoded++;
            }
        }

        $hostClub = null;
        if (($row['host_club'] ?? '') !== '') {
            $needle = $row['host_club'];
            $hostClub = Club::whereRaw('LOWER(name) = ?', [mb_strtolower($needle)])
                ->orWhere('slug', Str::slug($needle))
                ->first();
            if (! $hostClub) {
                throw new \InvalidArgumentException("unknown host_club \"{$needle}\" (add the club first)");
            }
        }

        $slug = Str::slug($row['name']).'-'.$starts->toDateString();
        $externalId = trim($row['external_id'] ?? '');

        $attrs = [
            'season_id' => $season->id,
            'host_club_id' => $hostClub?->id,
            'name' => $row['name'],
            'slug' => $slug,
            'starts_on' => $starts,
            'ends_on' => $ends,
            'city' => $row['city'],
            'state' => $state ?: null,
            'region' => strtoupper($row['region']),
            'lat' => $lat,
            'lng' => $lng,
            'is_nac' => $this->truthy($row['is_nac'] ?? ''),
            'level' => array_key_exists($row['level'] ?? '', Tournament::LEVELS)
                ? $row['level'] : 'regional',
            'country' => $country,
            'circuits' => $this->splitList($row['circuits'] ?? '') ?: null,
            'contested_events' => $events,
            'curated_note' => ($row['curated_note'] ?? '') !== '' ? $row['curated_note'] : null,
            'source_url' => ($row['source_url'] ?? '') !== '' ? $row['source_url'] : null,
        ];
        if ($externalId !== '') {
            $attrs['external_id'] = $externalId;
            $attrs['last_seen_at'] = now();
        }

        // The source id wins: a rescheduled event keeps its identity (and gets
        // a fresh slug) instead of duplicating under the new date.
        if ($externalId !== '' && ($existing = Tournament::where('external_id', $externalId)->first())) {
            $existing->update($this->preservingCuration($attrs, $existing));

            return 'updated';
        }

        // Adoption: a curated row (no source id) for the same date/state with a
        // matching-enough name is the same real tournament — merge into it
        // instead of creating a duplicate that "clashes with itself".
        if ($externalId !== '' && ($adopt = $this->findAdoptable($starts, $state, $row['name']))) {
            $adopt->update($this->preservingCuration($attrs, $adopt));

            return 'updated';
        }

        $exists = Tournament::where('slug', $slug)->exists();
        Tournament::updateOrCreate(['slug' => $slug], $attrs);

        return $exists ? 'updated' : 'created';
    }

    /** Sync rows never erase hand-curated notes or home-club links. */
    private function preservingCuration(array $attrs, Tournament $existing): array
    {
        $attrs['curated_note'] = $attrs['curated_note'] ?? $existing->curated_note;
        $attrs['host_club_id'] = $attrs['host_club_id'] ?? $existing->host_club_id;

        return $attrs;
    }

    /** A curated (no external id) row that looks like the same real event. */
    private function findAdoptable(Carbon $starts, string $state, string $name): ?Tournament
    {
        return Tournament::whereNull('external_id')
            ->whereDate('starts_on', $starts)
            ->where('state', $state ?: null)
            ->get()
            ->first(fn (Tournament $c) => self::namesLookAlike($c->name, $name));
    }

    /** Whether two tournament names plausibly refer to the same event. */
    public static function namesLookAlike(string $a, string $b): bool
    {
        $na = self::normalizeName($a);
        $nb = self::normalizeName($b);
        if ($na === '' || $nb === '') {
            return false;
        }
        if (str_contains($na, $nb) || str_contains($nb, $na)) {
            return true;
        }
        similar_text($na, $nb, $pct);

        return $pct >= 60.0;
    }

    /** Lowercase, strip punctuation and circuit/category boilerplate that varies by listing. */
    private static function normalizeName(string $n): string
    {
        $n = preg_replace('/[^a-z0-9]+/', ' ', strtolower($n));
        $n = preg_replace('/\b(roc|rjcc|ryc|syc|rcc|rpc|d1a|div\s*1a|dv2|div\s*2|div\s*3|vet|y8|y10|y12|y14|cdt|jnr|cup|and|the)\b/', ' ', $n);

        return trim(preg_replace('/\s+/', ' ', $n));
    }

    /** Split "JNR|CDT|D1A" (also accepts ; separators) into a clean array. */
    private function splitList(string $raw): array
    {
        return collect(preg_split('/[|;]/', $raw))
            ->map(fn ($v) => strtoupper(trim($v)))
            ->filter()
            ->values()
            ->all();
    }

    private function truthy(string $raw): bool
    {
        return in_array(strtolower($raw), ['1', 'true', 'yes', 'y'], true);
    }
}
