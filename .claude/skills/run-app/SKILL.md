---
name: run-app
description: Run and visually verify ThePiste locally - launch the Laravel dev server with seeded data, log in, drive pages headlessly, and screenshot them. Use whenever a change needs to be seen working in the real app (new pages, Livewire components, CSS/Blade changes, email-adjacent UI), when asked to screenshot or demo the app, or before deploying UI work. Covers the sqlite reseed, the demo-fencer login fixture, and the Playwright setup this repo otherwise lacks.
---

# Run and verify ThePiste locally

Everything below was verified working on this machine. Total cold-start time is about a minute (plus a one-time ~90 MB Chromium download).

## 1. Fresh data + a logged-in user who owns a plan

Local dev is sqlite (`DB_CONNECTION=sqlite` in `.env`); reseeding is cheap and the documented flow. The seeded admin (`ian@promoeqp.com` / `changeme-piste`) owns **no fencers** — the demo fencer Farren is intentionally unattached so she can drive the public demo page. Authed pages (`/season`, `/season/build`, `/season/budget`, `/season/results`) redirect to the fencer-creation form for a user with no fencers, so attach Farren and give her a plan:

```bash
php artisan migrate:fresh --seed --quiet
php artisan tinker --execute='
$u = App\Models\User::where("email","ian@promoeqp.com")->first();
$f = App\Models\Fencer::where("name","Farren")->first();
$f->update(["user_id" => $u->id]);
$season = App\Models\Season::where("is_active", true)->first();
$plan = $f->seasonPlans()->firstOrCreate(["season_id" => $season->id]);
foreach (App\Models\Tournament::orderBy("starts_on")->limit(6)->get() as $t) $plan->items()->firstOrCreate(["tournament_id" => $t->id]);
$plan->update(["budget" => 12000]);
echo "ready: ".$plan->items()->count()." items\n";'
```

This is throwaway local state; the next `migrate:fresh --seed` resets it.

## 2. Build assets, start the server

There is no `npm run dev` watcher in this flow — the `@vite` directive serves from the build manifest, so **rebuild after any CSS/Blade/JS change or the screenshot shows stale styles**:

```bash
npm run build
php artisan serve --port=8010   # run in background
for i in {1..30}; do curl -sf http://127.0.0.1:8010 >/dev/null && break; sleep 1; done
```

Port 8010 avoids colliding with a user-run `artisan serve` on 8000. Stop it afterwards with `pkill -f "artisan serve --port=8010"`.

## 3. Playwright (the repo has no JS test deps)

`package.json` here has no Playwright, and there is no global install. Set it up in a scratch dir — and note macOS `timeout` doesn't exist, hence the `for` loop above:

```bash
mkdir -p /tmp/pwtest && cd /tmp/pwtest
npm init -y >/dev/null && npm i playwright >/dev/null
npx playwright install chromium   # no-op if ~/Library/Caches/ms-playwright already has it
```

Node scripts must be **run from `/tmp/pwtest`** (CJS `require` resolves from the script's location, not cwd — copy scripts there).

## 4. Log in, drive, screenshot

Use the bundled driver — it logs in as the seeded admin, navigates, optionally runs page actions, saves a full-page screenshot, and exits non-zero if the page logged console errors:

```bash
cp <this-skill-dir>/scripts/shot.cjs /tmp/pwtest/
cd /tmp/pwtest && node shot.cjs /season/budget /tmp/budget.png
```

To interact first (fill inputs, click), pass an actions module:

```js
// /tmp/pwtest/actions.cjs
module.exports = async (page) => {
  const input = page.locator('input[aria-label*="Fees estimate"]').first();
  await input.fill('198.04');
  await input.blur();                 // Livewire wire:model.blur saves on blur
  await page.waitForTimeout(400);     // wait out the Livewire roundtrip
};
```

```bash
node shot.cjs /season/budget /tmp/budget.png actions.cjs
```

**Livewire gotchas:** inputs bound with `wire:model.blur` persist on blur, so `fill()` alone saves nothing — blur then wait ~400ms for the network roundtrip before screenshotting. Selects bound with `wire:model.change` save on `selectOption()` but still need the wait.

## 5. Look at the screenshot

Actually read the PNG. Things that have slipped past tests before: a table wider than its container cutting off columns, stale CSS from a skipped `npm run build`. A blank or half-rendered frame means the page threw — check the driver's `console errors` output and `storage/logs/laravel.log`.
