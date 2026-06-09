# ThePiste (thepiste.org)

## What this is

A personalized USA Fencing season planner for competitive fencers and their families. A parent enters a fencer's profile (weapon, age group, rating, home zip, home club, goal) and gets a prioritized, filterable tournament calendar: which events matter, why, drive vs fly, and weekend conflicts. Grew out of a hand-built calendar prototype for one fencer; the product generalizes it to any fencer via a computed personalization engine.

Sibling project to **escrimepro.com** (Ian's multi-tenant fencing club platform). Separate app, separate repo, separate DB. Schemas are kept escrimepro-compatible in case the two link up later, but they stand alone for now.

## Stack

- Laravel 12 + Filament v3 (admin) + Livewire + Alpine + Tailwind v4 + Vite
- MySQL 8 (prod) / SQLite (local dev), Redis, nginx, php8.4-fpm on a DigitalOcean droplet
- Server: `root@thepiste.org` (same box as escrimepro.com / promoeqp.com, host `promoeqp.com`, `159.203.98.141`)
- GitHub: `ianswope/thepiste`
- Matches the house style of promoeqp/escrimepro exactly.

## Deploy

GitHub-based, like promoeqp: commit + push to `main`, then on the server `git pull` and run the deploy steps.

```bash
ssh root@thepiste.org 'cd /var/www/thepiste && ./deploy.sh'
```

`deploy.sh` (on server): `git pull` → `composer install --no-dev` → `npm ci && npm run build` → `php artisan migrate --force` → config/route/view cache → reload php8.4-fpm. Server git remote uses the `github-thepiste` SSH alias with key `/root/.ssh/thepiste_deploy`.

## Architecture

- **Public calendar** (`/`) — `CalendarController` → `TierService` → `calendar.blade.php`. Blade + Tailwind + a small vanilla-JS filter (instant, client-side).
- **Personalization engine** — `app/Services/TierService.php`. Per fencer: eligibility (`config/fencing.php` matrix), haversine drive/fly distance, tier (nac/home/priority/drive/fly/skip), same-weekend conflict flags, generated-or-curated strategic notes. This is the core; the calendar is just its presentation.
- **Admin** (`/admin`) — Filament v3 panel (gated to `super_admin`/`club_admin` via `User::canAccessPanel`). Manage seasons, clubs, tournaments; CSV import.
- **Catalog ingestion** — the catalog is global (shared by all users); only admins import. Single path: `app/Services/TournamentCsvImporter.php` (upsert by slug = name + start date, so re-imports update in place; per-row errors; pipe-separated list fields). Filament Tournament list has "Import CSV" + "CSV template" actions. Geocoding: `PlaceGeocoder` (city/state → existing tournaments → `geo_places` cache → Nominatim) and `ZipGeocoder` (profile ZIPs → `zip_codes` cache → zippopotam.us). A future AskFRED auto-sync should feed the same importer path.
- **Data model** — `seasons`, `clubs`, `tournaments` (catalog); `users` (role: super_admin|club_admin|parent|fencer), `fencers` (managed by a user/parent, optional home club). Results + travel/trips + season plans added with their features.

## Ops

- **Backups**: nightly 03:30 cron runs `ops/backup-mysql.sh` (all app DBs on the box, gzip + integrity check, 14-day rotation in `/var/backups/mysql/`). `deploy.sh` takes a pre-migrate safety dump. DO droplet weekly backups are enabled (droplet `PromoEQPWebServer`, id 564359388). Restore: `gunzip < FILE.sql.gz | mysql thepiste`.
- **Error pages**: branded Laravel views in `resources/views/errors/` (404/403/500/503, self-contained CSS). `public/maintenance.html` is a static "back soon" page nginx serves on 502/503/504 (`error_page` in the vhost, internal-only) — covers php-fpm being down.
- **Uptime**: DO uptime check "thepiste.org" (id f7a197a7-e9e5-484a-93de-3ed73d18b8ef, us_east + us_west).
- **Mail is NOT configured** (`MAIL_MAILER=log`) — password resets land in the log. Wire Resend/Postmark before real users.

## MCP connector

Remote MCP server (laravel/mcp, Streamable HTTP) at `https://thepiste.org/mcp`, authed with a Sanctum bearer token (`User->createToken('claude-desktop')`). Server: `app/Mcp/ThePisteServer.php`; tools in `app/Mcp/Tools/` (list-fencers, set-goal, get-season-outlook, get-plan, manage-plan, log-result, get-progress). Registered in `routes/ai.php`. Claude Desktop connects via the `mcp-remote` bridge with an `Authorization: Bearer` header.

## Conventions / writing rules

- No em dashes. No AI cliches ("Certainly!", "Great question!"). No sycophancy. Be direct, do not narrate.
- Match Pint formatting (`./vendor/bin/pint`). Tests via PHPUnit (`php artisan test`).
- Tier is **computed, never stored** — it depends on the fencer. Keep the tournament catalog objective (facts only); `curated_note` overrides generated notes only for marquee events.

## Status

- Phase 1 (foundation): scaffold, schema, full Region 2 2026-27 seed (51 events), computed calendar UI, Filament admin, live deploy.
- Next: auth/login + roles, fencer profile builder (replaces the demo fencer), results tracking, travel planning, season-plan sharing/export.
- Local dev: `php artisan serve` + `npm run dev`. Seed: `php artisan migrate:fresh --seed`. Admin login seeded as `ian@promoeqp.com` (change the password in prod).
