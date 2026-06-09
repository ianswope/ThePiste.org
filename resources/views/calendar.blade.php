@php
    $tierLabel = [
        'nac' => '🏆 NAC', 'home' => '⚔ HOME CLUB', 'priority' => '★ PRIORITY',
        'drive' => '🚗 DRIVE', 'fly' => '✈ FLY', 'skip' => 'PASS',
    ];
    $tierBadge = [
        'nac' => 'b-nac', 'home' => 'b-home', 'priority' => 'b-priority',
        'drive' => 'b-drive', 'fly' => 'b-fly', 'skip' => 'b-skip',
    ];
    $dateRange = function ($s, $e) {
        if ($s->isSameDay($e)) return $s->format('M j');
        if ($s->month === $e->month) return $s->format('M j').'–'.$e->format('j');
        return $s->format('M j').'–'.$e->format('M j');
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ThePiste · {{ $fencer->name }}'s {{ $season->name }} Calendar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

@if ($isDemo)
    <div class="demobanner">
        You're viewing a sample season for a demo fencer.
        <a href="{{ url('/register') }}">Build your own &rarr;</a>
    </div>
@endif

<header class="header">
    <div class="authbar">
        @auth
            @if ($fencers->count() > 1)
                <form method="POST" style="margin:0">@csrf
                    <select onchange="this.form.action=this.value;this.form.submit()" aria-label="Switch fencer">
                        @foreach ($fencers as $f)
                            <option value="{{ route('fencers.select', $f) }}" @selected($f->id === $fencer->id)>{{ $f->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
            <a class="pill" href="{{ route('season.build') }}">Build plan</a>
            <a class="pill" href="{{ route('fencers.edit', $fencer) }}">Edit profile</a>
            <a href="{{ route('fencers.create') }}">+ Fencer</a>
            <form method="POST" action="{{ url('/logout') }}">@csrf<button type="submit">Log out</button></form>
        @endauth
        @guest
            <a href="{{ url('/login') }}">Sign in</a>
            <a class="pill" href="{{ url('/register') }}">Build your season</a>
        @endguest
    </div>
    <div class="header-inner">
        <div class="header-eye"><a href="{{ url('/') }}" class="brand">ThePiste</a> · USA Fencing · {{ $season->name }}</div>
        <h1>{{ $fencer->name }}'s Season Calendar</h1>
        <div class="header-meta">
            @if ($isDemo)<span class="tag club">Sample profile</span>@endif
            <span class="tag">{{ ucfirst($fencer->weapon) }}</span>
            <span class="tag">{{ $fencer->age_group }}</span>
            <span class="tag">Rating {{ $fencer->rating }}</span>
            @if($fencer->homeClub)
                <span class="tag club">{{ $fencer->homeClub->name }} · {{ $fencer->region() }}</span>
            @endif
        </div>
        <div class="legend">
            <span class="leg"><span class="dot d-nac"></span> NAC / Non-Negotiable</span>
            <span class="leg"><span class="dot d-home"></span> Home Club</span>
            <span class="leg"><span class="dot d-priority"></span> Priority</span>
            <span class="leg"><span class="dot d-drive"></span> Drive (≤{{ $fencer->driveRadius() }} mi)</span>
            <span class="leg"><span class="dot d-fly"></span> Fly Trip</span>
            <span class="leg"><span class="dot d-skip"></span> Lower Priority</span>
        </div>
    </div>
</header>

<div class="stats">
    <div class="stat"><span class="sn">{{ $stats['total'] }}</span><span class="sl">Eligible Events</span></div>
    <div class="stat"><span class="sn">{{ $stats['nac'] }}</span><span class="sl">NACs</span></div>
    <div class="stat"><span class="sn">{{ $stats['nonneg'] }}</span><span class="sl">Non-Negotiables</span></div>
    <div class="stat"><span class="sn">{{ $stats['priority'] }}</span><span class="sl">Priority</span></div>
    <div class="stat"><span class="sn">{{ $stats['drive'] }}</span><span class="sl">Drive Trips</span></div>
    <div class="stat"><span class="sn">{{ $stats['fly'] }}</span><span class="sl">Fly Trips</span></div>
</div>

<nav class="filters" aria-label="Calendar filters">
    <span class="fl">Show</span>
    <button class="fb active" data-f="all">All Events</button>
    <button class="fb" data-f="nonneg">🎯 Non-Negotiables</button>
    <button class="fb" data-f="nac">NACs Only</button>
    <button class="fb" data-f="priority">Priority + NAC</button>
    <button class="fb" data-f="drive">Driveable</button>
    <button class="fb" data-f="fly">Fly Trips</button>
    <button class="fb" data-f="region">In-Region</button>
    <button class="fb" data-f="home">Home Club</button>
</nav>

<main class="main" id="cal">
    @foreach($months as $label => $rows)
        @php([$mname, $myear] = explode(' ', $label))
        <section class="mb" data-month="{{ $label }}">
            <div class="mh">
                <span class="mn">{{ strtoupper($mname) }}</span>
                <span class="my">{{ $myear }}</span>
                <span class="mc">{{ $rows->count() }} event{{ $rows->count() !== 1 ? 's' : '' }}</span>
            </div>
            <div class="cards">
                @foreach($rows as $r)
                    @php($t = $r['tournament'])
                    <article class="card {{ $r['tier'] }}{{ $r['non_negotiable'] ? ' is-nonneg' : '' }}"
                         style="--d: {{ $loop->index }}"
                         data-tier="{{ $r['tier'] }}"
                         data-nonneg="{{ $r['non_negotiable'] ? 1 : 0 }}"
                         data-nac="{{ $r['is_nac'] ? 1 : 0 }}"
                         data-region="{{ $r['in_region'] ? 1 : 0 }}"
                         data-home="{{ $r['is_home'] ? 1 : 0 }}">
                        <div class="ct">
                            <span class="cd">{{ $dateRange($t->starts_on, $t->ends_on) }} <span class="reg">· {{ $t->region }}</span></span>
                            <div class="cbadges">
                                @if($r['is_home'])
                                    <span class="badge b-home">⚔ HOME CLUB</span>
                                @endif
                                <span class="badge {{ $tierBadge[$r['tier']] }}">{{ $tierLabel[$r['tier']] }}</span>
                            </div>
                        </div>
                        <div class="cname">{{ $t->name }}</div>
                        <div class="cloc">📍 {{ $t->city }}, {{ $t->state }}</div>
                        <div class="chips">
                            @foreach($t->contested_events ?? [] as $cat)
                                @php($isElig = in_array($cat, $r['eligible'], true))
                                <span class="chip {{ $r['tier'] === 'nac' && $isElig ? 'nac-hl' : ($isElig ? 'elig' : 'dim') }}">{{ $cat }}</span>
                            @endforeach
                        </div>
                        <div class="cnote">
                            {{ $r['note'] }}
                            @if($r['conflict_with'])
                                <span class="cconflict">⚠ Clashes with {{ $r['conflict_with'] }}, which ranks higher this weekend.</span>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="no-match">No events match this filter in {{ $label }}.</div>
        </section>
    @endforeach

    <div class="fnote">
        <strong>How these priorities are calculated.</strong><br>
        Every event is scored for <strong>{{ $fencer->name }}</strong> specifically: which categories
        {{ $fencer->name }} can enter ({{ implode(', ', $fencer->eligibleCategories()) }}), how far each venue sits
        from home, whether it falls in {{ $fencer->region() }}, and whether it collides with a bigger event the
        same weekend. NACs and home-club events are always non-negotiable; in-region multi-category weekends become
        priorities; far single-category events drop down the list. Change the profile and the whole calendar re-sorts.
    </div>
</main>

</body>
</html>
