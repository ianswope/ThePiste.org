# ThePiste.org — Product Requirements Document
**Version:** 1.0  
**Date:** June 2026  
**Author:** Ian Swope  
**Domain:** thepiste.org  
**Repo:** ~/repos/thepiste  
**Stack:** Next.js · NestJS · PostgreSQL  

---

## Context for Claude Code

This PRD is the kickoff document for building ThePiste.org in Claude Code. Before writing any code, read the following files from the about-me folder in the Cowork workspace:

- `about-me.md`
- `writing-rules.md`
- `memory.md`

The UI reference design is the file `farren_fencing_calendar_2026_27.html` — a hand-built interactive fencing tournament calendar created during planning. The visual language, color system (navy `#0B1F3A`, gold `#C9A84C`, cream `#F5F0E8`), typography (Space Grotesk + Space Mono), card layout, tier system, and filter architecture should all inform the product design. Do not start from scratch visually — extend what exists.

---

## Problem Statement

USA Fencing families spend hours every season manually cross-referencing regional tournament calendars against their fencer's age group, weapon, eligibility, home region, goals, and travel budget. The official USA Fencing calendar is a flat list with no filtering, no personalization, and no strategic guidance. Parents — especially those new to the sport — have no way to understand which events actually matter for their fencer's rating, regional standing, or national points accumulation.

There is no tool that takes a fencer's profile and produces a personalized, prioritized season plan. This problem is felt across every region, every weapon, and every competitive level. ThePiste.org solves it.

---

## Goals

1. **A parent with a new competitive fencer can build a full season calendar in under 5 minutes** — from profile setup through a prioritized, exportable schedule.
2. **Fencers and parents can immediately understand which events matter most** for their specific rating goal (e.g., earning a B) and why.
3. **The tool surfaces travel logistics automatically** — drive vs. fly threshold, distance from home zip, and conflict detection across weekends.
4. **The calendar is shareable and exportable** — a family can send their season plan to a coach, export to Google Calendar, or print it.
5. **The product is useful to families across all six USA Fencing regions**, not just Region 2 / the Midwest.

---

## Non-Goals (v1)

- **No team or club management features.** ThePiste.org v1 is a tool for individual fencers and their families. Club dashboards, coach portals, and multi-fencer roster management are explicitly deferred to v2.
- **No results or scoring tracking.** Recording tournament results, tracking points accumulation in real time, or integrating with AskFRED/FencingTime is out of scope for v1. The product plans the season; it does not track performance during it.
- **No mobile app.** v1 is a responsive web app. Native iOS/Android is a v2 consideration.
- **No payment or premium tier.** v1 is free and ungated. Monetization strategy is deferred until there is meaningful usage data.
- **No real-time USA Fencing data sync.** v1 uses a curated, manually maintained tournament dataset seeded from the 2026–27 regional calendar. Automated scraping or API integration with USA Fencing is a v2 feature.

---

## User Personas

### Primary: The Fencing Parent
A parent of a competitive junior or cadet fencer, ages 13–18. Likely new-ish to the sport (1–3 years in). Understands their fencer's weapon and age group but is still learning the circuit structure — what NACs are, what a B rating means, how regional vs. national points work. Has a real budget constraint and wants to make smart travel decisions. Does not want to read a 40-page rulebook to figure out where to go on a Saturday.

### Secondary: The Fencer (Teen)
A junior or cadet fencer who wants ownership over their own season. Likely more sport-literate than their parent on eligibility rules. Wants to see their schedule, understand what's at stake at each event, and share it with their coach.

### Tertiary: The Coach / Club Director
A club coach who wants to recommend a season plan to families. May use ThePiste.org to build a suggested schedule and share it with parents at the start of the season. Does not need admin tools in v1 — just needs the output to be shareable and printable.

---

## User Stories

### Profile Setup

- As a fencing parent, I want to enter my fencer's weapon, age group, current rating, home zip code, and home club so that the calendar can be personalized to their eligibility and location.
- As a fencing parent, I want to select my fencer's competitive goal (e.g., "earn a B rating", "qualify for Junior Olympics", "build regional standing") so that the tool can prioritize events that serve that goal.
- As a fencing parent, I want to set a maximum drive distance from my home zip so that events beyond that threshold are automatically flagged as fly trips.
- As a fencing parent, I want to mark my fencer's home club so that club-hosted events are visually distinguished and treated as highest-priority.

### Calendar & Filtering

- As a fencing parent, I want to see a full season calendar filtered to only the events my fencer is eligible to enter so that I'm not overwhelmed by irrelevant tournaments.
- As a fencing parent, I want each event to show which of my fencer's eligible categories are contested so that I can see at a glance how many events she can enter in one trip.
- As a fencing parent, I want to filter the calendar by tier (Non-Negotiable, Priority, Drive, Fly, Pass) so that I can focus on building the core schedule first.
- As a fencing parent, I want to see a "Non-Negotiables" view that shows NACs, home club events, and the highest-value regional events in one place so that I can lock those dates first.
- As a fencing parent, I want conflict detection that flags when two events fall on the same weekend so that I'm never accidentally double-booked.

### Strategic Guidance

- As a fencing parent, I want each event to include a plain-English strategic note explaining why it matters (or doesn't) for my fencer's goals so that I can make informed decisions without being a fencing expert.
- As a fencing parent, I want to understand the difference between regional points events and NACs so that I know which events have the most impact on national ranking.
- As a fencing parent, I want to see a season summary that shows how many events I've selected, estimated drive trips vs. fly trips, and which goal-relevant categories are covered so that I can sanity-check the plan before committing.

### Sharing & Export

- As a fencing parent, I want to export my season schedule to Google Calendar or as an .ics file so that tournament dates are automatically on our family calendar.
- As a fencing parent, I want to generate a shareable link to my fencer's season plan so that I can send it to their coach or the other parent.
- As a fencing parent, I want to print or export a clean PDF version of the schedule so that I can post it on the fridge.

### Data & Admin (Internal)

- As a site admin (Ian), I want to manage the tournament dataset through a simple admin interface so that I can update events, add new seasons, and correct errors without touching the database directly.
- As a site admin, I want to add new seasons of tournament data from a CSV import matching the USA Fencing regional calendar format so that annual updates are fast.

---

## Requirements

### P0 — Must Have (MVP)

**Fencer Profile**
- [ ] User can create a profile with: weapon (foil / épée / sabre), age group (Y10, Y12, Y14, Cadet, Junior, Div1A, Div2, Vet), current rating (U, E, D, C, B, A), home zip code, home club name, and season goal
- [ ] Profile is persisted (local storage for v1, database-backed preferred)
- [ ] Eligibility rules are encoded per age group and rating (e.g., a Junior can fence JNR, CDT, D1A, DV2; a Cadet cannot fence JNR)

**Tournament Data**
- [ ] Full 2026–27 regional calendar seeded into the database (use the existing HTML calendar as source of truth)
- [ ] Each tournament record includes: name, dates, location (city, state, lat/lng), region, circuit(s), contested events, tier (NAC / must / drive / fly / skip), home club flag, strategic note
- [ ] NAC events are flagged separately from regional events with confirmed dates and locations

**Calendar View**
- [ ] Month-by-month calendar view matching the reference HTML design
- [ ] Events filtered to only those containing at least one of the fencer's eligible categories
- [ ] Each card shows: dates, location, region, eligible event chips (highlighted), tier badge, strategic note
- [ ] Home club events visually distinguished with HOME CLUB badge and distinct card treatment
- [ ] NAC events visually distinguished as the highest tier

**Filtering**
- [ ] Filter buttons: All Events, Non-Negotiables, Priority + NAC, Drive Only, Fly Only, Home Events, FCC/Club Events
- [ ] Non-Negotiables filter surfaces: all NACs + home club events + top regional value events
- [ ] Drive/Fly threshold calculated from home zip using straight-line distance with a configurable drive radius (default 450 miles / ~7 hrs)
- [ ] Conflict detection: events on the same weekend are flagged, with a visual indicator and a note on the lower-priority event

**Stats Bar**
- [ ] Summary counts: Total eligible events, NACs, Priority events, Drive trips, Fly trips

### P1 — Nice to Have (Fast Follow)

- [ ] Season plan builder: user can "select" events and build a personal schedule separate from the full calendar
- [ ] iCal / Google Calendar export of selected events
- [ ] Shareable URL for a fencer's season plan (read-only link)
- [ ] Printable / PDF export of the season plan
- [ ] Drive time estimate (using MapBox or Google Maps Distance Matrix API) rather than straight-line distance
- [ ] Multiple fencer profiles per account (for families with more than one competitive fencer)
- [ ] "Why this event?" expandable explanation for each strategic tier assignment
- [ ] Admin CSV import for annual tournament data updates

### P2 — Future Considerations (Design For, Don't Build Yet)

- [ ] User accounts with persistent cloud storage (vs. local storage)
- [ ] Coach/club portal: ability to publish a recommended season plan to families
- [ ] Multi-season archive (prior year calendars for reference)
- [ ] Results integration: connect to AskFRED or FencingTime to track points accumulation in real time
- [ ] Automated data sync from USA Fencing's regional calendar Airtable or website
- [ ] Mobile app (React Native)
- [ ] Community features: regional parent forums, carpool coordination

---

## Data Model (Starting Point)

```
Fencer
  id, name, weapon, age_group, rating, home_zip, home_club_id, goal, created_at

Tournament
  id, name, start_date, end_date, city, state, lat, lng, region,
  circuits[], contested_events[], tier, is_nac, is_home_club,
  strategic_note, created_at

Club
  id, name, city, state, home_tournament_ids[]

SeasonPlan (P1)
  id, fencer_id, selected_tournament_ids[], share_token, created_at
```

---

## Tech Stack

| Layer | Choice | Notes |
|-------|--------|-------|
| Frontend | Next.js (App Router) | Consistent with BRND v2 planning |
| Backend | NestJS | REST API, consistent with BRND v2 |
| Database | PostgreSQL | Via Supabase for easy hosting |
| Styling | Tailwind CSS | Utility-first, mobile-responsive |
| Maps | Mapbox GL JS | Distance calculation + optional map view |
| Hosting | Vercel (frontend) + Railway or Render (backend) | Fast deploy, generous free tiers |
| Domain | thepiste.org | Registered via Hover |
| Auth | None for v1 | Local storage profile; add NextAuth in v2 |

---

## Visual Design Reference

The reference design is `farren_fencing_calendar_2026_27.html`. The following are non-negotiable carry-overs into the product:

**Color tokens:**
- `--navy: #0B1F3A` — primary background, header, card accents
- `--gold: #C9A84C` — priority highlights, home club badge, NAC accents
- `--cream: #F5F0E8` — page background
- `--drive: #1A6B3C` — drive trip tier
- `--fly: #7B3F00` — fly trip tier
- `--nac: #5B0D8A` — NAC tier (most prominent)

**Typography:**
- Display / UI: Space Grotesk
- Monospace / data: Space Mono (dates, region codes, event chips)

**Card anatomy:** left accent bar (4px, tier color) · date + region (mono, muted) · tier badge · home club badge · tournament name · location · event chips (eligible events highlighted in navy/gold) · strategic note

---

## Success Metrics

### Leading (measure at 30 days post-launch)
- Profile completion rate: % of visitors who complete a fencer profile > 60%
- Calendar engagement: avg. time spent on calendar view > 3 minutes
- Filter usage: % of sessions that use at least one filter > 50%

### Lagging (measure at 90 days)
- Return visits: % of users who return within 30 days of first visit > 40%
- Shares: # of shareable plan links generated (P1 feature)
- Organic referrals: % of new users arriving via direct/referral (word of mouth in fencing community)

---

## Open Questions

| Question | Owner | Blocking? |
|----------|-------|-----------|
| Should drive threshold be user-configurable (slider) or fixed at ~450 mi? | Ian | No — default to 450 mi, make it configurable in P1 |
| How do we handle eligibility edge cases — e.g., a fencer who has just crossed a rating threshold mid-season? | Ian + fencing rules | No — v1 uses static eligibility at profile creation |
| Should the strategic notes be static (hardcoded per tournament) or dynamically generated based on the fencer's profile and goals? | Ian | Yes for v1 — static notes; dynamic generation is a compelling v2 AI feature |
| What's the right seed dataset format for annual updates — pull from Airtable CSV, scrape USA Fencing site, or manual entry? | Ian | No — manual CSV import for v1 |
| Do we need user accounts for v1 or is local storage sufficient for early usage? | Ian | No — local storage is fine for v1; avoids auth complexity at launch |

---

## Phase Plan

### Phase 1 — Foundation (Weeks 1–2)
- Project scaffold: Next.js + NestJS + PostgreSQL
- Database schema + seed data from 2026–27 calendar
- Static tournament calendar page (port the HTML reference to React)
- Basic profile setup (local storage)
- Eligibility filtering by profile

### Phase 2 — Core Product (Weeks 3–4)
- Full filter system (all 7 filters from reference)
- Drive/fly threshold calculation from zip
- Conflict detection
- Stats bar
- NAC + home club visual treatment
- Mobile responsive

### Phase 3 — Sharing & Polish (Weeks 5–6)
- Season plan builder (select events)
- iCal / Google Calendar export
- Shareable URL (read-only plan link)
- PDF print view
- Admin CSV import for tournament data
- Deploy to Vercel + Railway, point thepiste.org

---

## Notes for Claude Code

- Ian builds and codes directly — do not produce specs, wireframes, or planning documents during the build session. Write the code.
- Prefer building complete, working features over scaffolding with TODOs.
- The HTML reference calendar is the design source of truth — match it closely before diverging.
- When in doubt about scope, refer back to Phase 1 above and stay in lane.
- Ian is a Mac user comfortable with bash/terminal workflows.
- Repo lives at `~/repos/thepiste`. GitHub remote to be set up at project start.
