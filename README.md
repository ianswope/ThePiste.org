# ThePiste

**[thepiste.org](https://thepiste.org)** is a personalized season planner for competitive USA Fencing families.

Every fencing season starts with the same scramble: 50+ tournaments on the calendar, and a parent trying to work out which ones matter for *their* kid. Which events can she actually enter? Which are worth a flight, which are a Saturday drive, which collide on the same weekend? Which ones move her toward a B rating, and which are just expensive mileage?

ThePiste answers those questions automatically. Enter a fencer's profile (weapon, age category, rating, home ZIP, home club) and set their goals, and the entire tournament catalog is scored into a prioritized, personalized calendar: what to anchor, what to add for value, what to skip without guilt, and why.

## How it works

### The personalization engine

The tournament catalog is objective (names, dates, places, contested categories). Everything subjective is computed per fencer at request time by `app/Services/TierService.php`:

- **Eligibility.** Weapon + age category + rating determine which contested categories a fencer can enter (`config/fencing.php`). Events with no eligible categories disappear.
- **Distance.** Haversine from home ZIP to each venue decides drive vs fly against the family's drive radius.
- **Tier.** Rules assign every event a tier: `nac` (national, non-negotiable), `home` (your club's event), `priority` (in-region, multi-category), `drive`, `fly`, or `skip`. Club-level events never outrank official circuit events.
- **Conflicts.** Same-weekend events are detected; the higher tier wins, the loser is flagged with the trade-off.
- **Notes.** Every event gets a generated plain-language note (distance, region points, categories). Marquee events carry curated notes instead.

Tier is computed, never stored. Change the profile and the whole calendar re-sorts.

### Goals drive the recommendations

Goals are structured records, not labels (`app/Services/GoalScorer.php`):

- **Rating** ("Earn a B in foil") lights up events where a letter is credibly earnable: NACs, ROCs, strong D1A/DV2 fields. Club opens never qualify.
- **Qualify** ("Qualify for Junior Olympics") is path-aware: it labels RJCC circuit events, named qualifiers, and the championship itself. It never claims you have qualified; qualification rules shift season to season.
- **Standing** ("Build JNR regional standing") boosts in-region circuit events in that category.
- **Develop** ("Fence 8 events this season") favors low-pressure, driveable mileage.

Events that advance a goal are marked across the calendar and builder with the reason, and goal contribution breaks same-weekend ties within a tier.

### The loop

1. **Build a profile** for each fencer (multiple per account).
2. **Set goals** in the season builder; anchors are pre-selected, value events are suggested.
3. **Build the plan**: toggle events, watch drives, flights, clashes, and estimated budget tally live.
4. **Share it**: read-only link for coach or co-parent, `.ics` download, calendar subscription, print view.
5. **Log results** after each event. Earned ratings update the profile automatically and per-goal progress meters fill in.

### Self-maintaining catalog

Tournament data syncs from AskFRED daily, with a full audit three times a week (`app/Console/Commands/SyncAskFred.php`). Upserts reconcile by external id and slug so date changes update in place, curated notes survive syncs, and look-alike events are adopted rather than duplicated. Admins can also import CSVs through the Filament panel at `/admin`.

### Claude connector (MCP)

A remote MCP server (`app/Mcp/ThePisteServer.php`, Streamable HTTP at `/mcp`, Sanctum bearer auth) lets Claude manage a season conversationally: list fencers, set goals, get the scored outlook, build and edit the plan, log results, and check progress.

## Stack

- Laravel 12, Livewire 3, Alpine, Tailwind v4, Vite
- Filament v3 admin panel
- MySQL 8 in production, SQLite for local dev, Redis for cache
- Resend for transactional mail
- PHPUnit feature tests, Pint formatting

## Local development

```bash
git clone git@github.com:ianswope/ThePiste.org.git thepiste
cd thepiste
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate:fresh --seed   # full Region 2 2026-27 season + demo fencer
php artisan serve                  # plus `npm run dev` in a second terminal
```

The seed includes a demo fencer, so `/demo` shows a fully personalized calendar immediately. Default admin login is created by the seeder; change the password before exposing anything.

```bash
php artisan test        # run the suite
./vendor/bin/pint       # format
```

## Repo map

| Path | What |
|------|------|
| `app/Services/TierService.php` | The engine: eligibility, distance, tiers, conflicts |
| `app/Services/GoalScorer.php` | Goal-aware event scoring |
| `app/Services/TournamentImporter.php` | Single ingestion path (CSV + AskFRED sync) |
| `app/Services/AskFredScraper.php` | AskFRED listing parser |
| `app/Livewire/SeasonBuilder.php` | Guided plan builder + goals manager |
| `app/Livewire/ResultsTracker.php` | Results logging + goal progress |
| `app/Mcp/` | Claude connector (server + tools) |
| `app/Filament/` | Admin resources |
| `config/fencing.php` | Eligibility matrix, tier ranks, goal/qualification config |
| `design/` | Design direction mockups (the live app follows `mockup-c-scoreboard`) |

## Deploy

Push to `main`, then run `./deploy.sh` on the server: pull, install, build, pre-migrate database dump, migrate, cache, reload. Nightly database backups and a static maintenance page are set up in `ops/`.

## Status

Live at [thepiste.org](https://thepiste.org) with the full Region 2 2026-27 season. Other regions arrive via the same sync; the engine already handles any region.
