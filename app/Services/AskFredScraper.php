<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Pulls upcoming tournaments from AskFRED's server-rendered listing pages
 * (robots.txt allows /tournaments; we honor its Crawl-delay of 1s and send an
 * identified User-Agent). Each listing card carries everything we need: the
 * detail UUID, a Google-Calendar link with exact dates + full street address,
 * and the per-event table (e.g. "Cadet Women's Foil") used to derive the
 * contested categories. Output rows feed TournamentImporter::upsertRow().
 */
class AskFredScraper
{
    public const BASE = 'https://www.askfred.net/tournaments';

    private const UA = 'ThePiste/1.0 (+https://thepiste.org; ian@promoeqp.com)';

    /** Event-name tokens -> our category codes. Order matters (specific first). */
    private const CATEGORY_TOKENS = [
        'Y10' => 'Y10', 'Y12' => 'Y12', 'Y14' => 'Y14',
        'Cadet' => 'CDT', 'Junior' => 'JNR',
        'Div1A' => 'D1A', 'Division 1A' => 'D1A',
        'Div2' => 'DV2', 'Division II' => 'DV2',
        'Vet' => 'VET', 'Senior' => 'OPEN',
    ];

    /** @return string page HTML */
    public function fetchPage(int $page, ?string $after = null): string
    {
        $query = ['page' => $page];
        if ($after) {
            $query['date_by'] = 'after';
            $query['date'] = $after;
        }

        $res = Http::timeout(20)
            ->withHeaders(['User-Agent' => self::UA])
            ->get(self::BASE, $query);

        if (! $res->ok()) {
            throw new \RuntimeException("AskFRED page {$page} returned HTTP {$res->status()}");
        }

        return $res->body();
    }

    /**
     * @return array{rows: array<int, array<string,string>>, hasNext: bool, skipped: array<string,int>}
     */
    public function parseListing(string $html): array
    {
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        $xp = new \DOMXPath($dom);

        $rows = [];
        $skipped = ['non_us' => 0, 'no_dates' => 0, 'no_categories' => 0];

        foreach ($xp->query('//div[contains(@class,"card") and contains(@class,"my-2")]') as $card) {
            $link = $xp->query('.//h5//a[starts-with(@href,"/tournaments/")]', $card)->item(0);
            if (! $link) {
                continue; // the filter-form card, pagination, etc.
            }

            $name = trim(preg_replace('/\s+/', ' ', $link->textContent));
            $uuid = basename(parse_url($link->getAttribute('href'), PHP_URL_PATH));

            // Dates + address come from the Google-Calendar link's query string.
            $gcal = $xp->query('.//a[contains(@href,"calendar.google.com")]', $card)->item(0);
            $dates = $this->parseGcal($gcal?->getAttribute('href'));
            if ($dates === null) {
                $skipped['no_dates']++;

                continue;
            }
            [$starts, $ends, $location] = $dates;

            $place = $this->parseUsLocation($location);
            if ($place === null) {
                $skipped['non_us']++;

                continue;
            }
            [$city, $state] = $place;

            $categories = $this->categoriesFromEvents($xp, $card);
            if ($categories === []) {
                $skipped['no_categories']++;

                continue;
            }

            // National events only — division/regional "JO Qualifier" events are not NACs.
            $isNac = preg_match('/\b(NAC|North American Cup|Junior Olympic|National Championship)/i', $name)
                && ! preg_match('/qualif/i', $name);
            preg_match_all('/\b(ROC|RJCC|RYC|SYC|RCC|RPC)\b/i', $name, $circuits);
            $circuitList = array_unique(array_map('strtoupper', $circuits[1]));

            $rows[] = [
                'name' => $name,
                'starts_on' => $starts,
                'ends_on' => $ends,
                'city' => $city,
                'state' => $state,
                'region' => $isNac ? 'NATIONAL' : (config('fencing.state_regions')[$state] ?? 'R?'),
                'is_nac' => $isNac ? 'yes' : '',
                // Official circuit events carry designators; everything else is club-level.
                'level' => $isNac ? 'national' : ($circuitList !== [] ? 'regional' : 'local'),
                'circuits' => implode('|', $circuitList),
                'contested_events' => implode('|', $categories),
                'host_club' => '',
                'curated_note' => '',
                'source_url' => 'https://www.askfred.net/tournaments/'.$uuid,
                'lat' => '',
                'lng' => '',
                'external_id' => $uuid,
            ];
        }

        $hasNext = $xp->query('//a[@rel="next"]')->length > 0;

        return ['rows' => $rows, 'hasNext' => $hasNext, 'skipped' => $skipped];
    }

    /** @return array{0: string, 1: string, 2: string}|null [starts_on, ends_on, location] */
    private function parseGcal(?string $href): ?array
    {
        if (! $href) {
            return null;
        }
        parse_str((string) parse_url(html_entity_decode($href), PHP_URL_QUERY), $q);
        if (empty($q['dates']) || ! str_contains($q['dates'], '/')) {
            return null;
        }

        [$start, $end] = explode('/', $q['dates'], 2);
        try {
            $starts = Carbon::createFromFormat('Ymd', substr($start, 0, 8))->startOfDay();
            // Google all-day ranges are end-exclusive.
            $ends = Carbon::createFromFormat('Ymd', substr($end, 0, 8))->subDay()->startOfDay();
        } catch (\Throwable) {
            return null;
        }
        if ($ends->lt($starts)) {
            $ends = $starts->copy();
        }

        return [$starts->toDateString(), $ends->toDateString(), (string) ($q['location'] ?? '')];
    }

    /** Full state names appear in some venue addresses ("Santa Clara, California 95054"). */
    private const STATE_NAMES = [
        'ALABAMA' => 'AL', 'ALASKA' => 'AK', 'ARIZONA' => 'AZ', 'ARKANSAS' => 'AR', 'CALIFORNIA' => 'CA',
        'COLORADO' => 'CO', 'CONNECTICUT' => 'CT', 'DELAWARE' => 'DE', 'DISTRICT OF COLUMBIA' => 'DC',
        'FLORIDA' => 'FL', 'GEORGIA' => 'GA', 'HAWAII' => 'HI', 'IDAHO' => 'ID', 'ILLINOIS' => 'IL',
        'INDIANA' => 'IN', 'IOWA' => 'IA', 'KANSAS' => 'KS', 'KENTUCKY' => 'KY', 'LOUISIANA' => 'LA',
        'MAINE' => 'ME', 'MARYLAND' => 'MD', 'MASSACHUSETTS' => 'MA', 'MICHIGAN' => 'MI', 'MINNESOTA' => 'MN',
        'MISSISSIPPI' => 'MS', 'MISSOURI' => 'MO', 'MONTANA' => 'MT', 'NEBRASKA' => 'NE', 'NEVADA' => 'NV',
        'NEW HAMPSHIRE' => 'NH', 'NEW JERSEY' => 'NJ', 'NEW MEXICO' => 'NM', 'NEW YORK' => 'NY',
        'NORTH CAROLINA' => 'NC', 'NORTH DAKOTA' => 'ND', 'OHIO' => 'OH', 'OKLAHOMA' => 'OK', 'OREGON' => 'OR',
        'PENNSYLVANIA' => 'PA', 'RHODE ISLAND' => 'RI', 'SOUTH CAROLINA' => 'SC', 'SOUTH DAKOTA' => 'SD',
        'TENNESSEE' => 'TN', 'TEXAS' => 'TX', 'UTAH' => 'UT', 'VERMONT' => 'VT', 'VIRGINIA' => 'VA',
        'WASHINGTON' => 'WA', 'WEST VIRGINIA' => 'WV', 'WISCONSIN' => 'WI', 'WYOMING' => 'WY',
    ];

    /** @return array{0: string, 1: string}|null [city, state] — null for non-US addresses */
    private function parseUsLocation(string $location): ?array
    {
        $location = trim($location);

        // "City, ST 12345[-6789][ US|USA]" — a 5-digit zip after a 2-letter code
        // is the US signal (Canadian postals are alphanumeric).
        if (preg_match('/(?:^|,)\s*([^,]+?),\s*([A-Z]{2})\s+(\d{5})(?:-\d{4})?(?:\s+(?:US|USA))?\s*$/', $location, $m)) {
            return [trim($m[1]), $m[2]];
        }

        // "City, California 95054[ US]" — full state name spelled out.
        if (preg_match('/(?:^|,)\s*([^,]+?),\s*([A-Za-z][A-Za-z ]+?)\s+(\d{5})(?:-\d{4})?(?:\s+(?:US|USA))?\s*$/', $location, $m)) {
            $code = self::STATE_NAMES[strtoupper(trim($m[2]))] ?? null;
            if ($code !== null) {
                return [trim($m[1]), $code];
            }
        }

        return null;
    }

    /** @return string[] our category codes derived from the card's event table */
    private function categoriesFromEvents(\DOMXPath $xp, \DOMNode $card): array
    {
        $categories = [];
        foreach ($xp->query('.//table//a', $card) as $event) {
            $label = trim($event->textContent);
            $matched = null;
            foreach (self::CATEGORY_TOKENS as $token => $code) {
                if (stripos($label, $token) !== false) {
                    $matched = $code;
                    break;
                }
            }
            // Unprefixed adult events ("Women's Epee", "Mixed Open Foil") are open events.
            if ($matched === null && preg_match('/^(Men|Women|Mixed|Open)/i', $label)) {
                $matched = 'OPEN';
            }
            if ($matched !== null) {
                $categories[$matched] = true;
            }
        }

        return array_keys($categories);
    }
}
