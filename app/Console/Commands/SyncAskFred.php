<?php

namespace App\Console\Commands;

use App\Models\Season;
use App\Services\AskFredScraper;
use App\Services\TournamentImporter;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Sleep;

class SyncAskFred extends Command
{
    protected $signature = 'thepiste:sync-askfred
        {--dry-run : Parse and report without writing to the catalog}
        {--max-pages=30 : Safety cap on listing pages fetched}
        {--from= : Only events starting after this date (default: today)}
        {--season= : Season slug to attach events to (default: active season)}';

    protected $description = 'Pull upcoming US tournaments from AskFRED into the catalog (same upsert path as CSV import)';

    public function handle(AskFredScraper $scraper, TournamentImporter $importer): int
    {
        $season = $this->option('season')
            ? Season::where('slug', $this->option('season'))->firstOrFail()
            : (Season::where('is_active', true)->first() ?? Season::firstOrFail());

        $from = $this->option('from') ?: now()->toDateString();
        $dry = (bool) $this->option('dry-run');

        $this->info(($dry ? '[DRY RUN] ' : '')."Syncing AskFRED events after {$from} into season {$season->name}…");

        $summary = ['created' => 0, 'updated' => 0, 'geocoded' => 0, 'out_of_season' => 0, 'errors' => []];
        $skippedTotals = ['non_us' => 0, 'no_dates' => 0, 'no_categories' => 0];
        $pages = 0;

        for ($page = 1; $page <= (int) $this->option('max-pages'); $page++) {
            $parsed = $scraper->parseListing($scraper->fetchPage($page, $from));
            $pages++;

            foreach ($parsed['skipped'] as $k => $n) {
                $skippedTotals[$k] += $n;
            }

            foreach ($parsed['rows'] as $row) {
                $starts = Carbon::parse($row['starts_on']);
                if ($starts->lt($season->starts_on) || $starts->gt($season->ends_on)) {
                    $summary['out_of_season']++;

                    continue;
                }

                if ($dry) {
                    $this->line(sprintf('  would import: %-58s %s · %s, %s · %s [%s]',
                        mb_substr($row['name'], 0, 58), $row['starts_on'], $row['city'], $row['state'], $row['region'], $row['contested_events']));
                    $summary['created']++;

                    continue;
                }

                try {
                    $summary[$importer->upsertRow($row, $season, $summary['geocoded'])]++;
                } catch (\InvalidArgumentException $e) {
                    $summary['errors'][] = "{$row['name']}: {$e->getMessage()}";
                }
            }

            if (! $parsed['hasNext']) {
                break;
            }

            Sleep::for(1)->seconds(); // honor AskFRED's Crawl-delay
        }

        $this->newLine();
        $this->info(sprintf(
            '%s: %d %s, %d updated, %d geocoded · skipped: %d non-US, %d no-categories, %d no-dates, %d outside season · %d pages',
            $dry ? 'Dry run' : 'Done',
            $summary['created'], $dry ? 'would import' : 'created',
            $summary['updated'], $summary['geocoded'],
            $skippedTotals['non_us'], $skippedTotals['no_categories'], $skippedTotals['no_dates'],
            $summary['out_of_season'], $pages
        ));

        foreach (array_slice($summary['errors'], 0, 10) as $err) {
            $this->warn("  ! {$err}");
        }

        return self::SUCCESS;
    }
}
