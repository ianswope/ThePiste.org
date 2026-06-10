@php
    $tierLabel = [
        'nac' => 'NAC', 'home' => 'Home Club', 'priority' => 'Priority',
        'drive' => 'Drive', 'fly' => 'Fly Trip', 'skip' => 'Pass',
    ];
    $dateDays = function ($s, $e) {
        if ($s->isSameDay($e)) return $s->format('j');
        if ($s->month === $e->month) return $s->format('j').'–'.$e->format('j');
        return $s->format('j').'–'.$e->format('M j');
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
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,100..900&family=Martian+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

@if ($isDemo)
    <div class="demobanner">
        You're viewing a sample season for a demo fencer.
        <a href="{{ url('/register') }}">Build your own &rarr;</a>
    </div>
@endif

<header class="board">
    <div class="board-inner">
        <div class="board-top">
            <a class="brand" href="{{ url('/') }}">THE<span>PISTE</span></a>
            <nav class="bnav-group">
                @auth
                    @if ($fencers->count() > 1)
                        <form method="POST">@csrf
                            <select onchange="this.form.action=this.value;this.form.submit()" aria-label="Switch fencer">
                                @foreach ($fencers as $f)
                                    <option value="{{ route('fencers.select', $f) }}" @selected($f->id === $fencer->id)>{{ $f->name }}</option>
                                @endforeach
                            </select>
                        </form>
                    @endif
                    <a class="bnav go" href="{{ route('season.build') }}">Build plan</a>
                    <a class="bnav" href="{{ route('season.prep') }}">Prep</a>
                    <a class="bnav" href="{{ route('season.results') }}">Results</a>
                    <a class="bnav" href="{{ route('season.budget') }}">Budget</a>
                    <a class="bnav" href="{{ route('fencers.edit', $fencer) }}">Edit profile</a>
                    <a class="bnav bare" href="{{ route('fencers.create') }}">+ Fencer</a>
                    <form method="POST" action="{{ url('/logout') }}">@csrf<button type="submit" class="bnav bare">Log out</button></form>
                @endauth
                @guest
                    <a class="bnav bare" href="{{ url('/login') }}">Sign in</a>
                    <a class="bnav go" href="{{ url('/register') }}">Build your season</a>
                @endguest
            </nav>
        </div>

        <div class="titlebox">
            <div class="light l" aria-hidden="true"></div>
            <div class="tt">
                <div class="eyebrow">USA Fencing · Season {{ $season->name }}</div>
                <h1>{{ $fencer->name }}'s Season Calendar</h1>
                <div class="meta">
                    @if ($isDemo)Sample profile · @endif{{ $fencer->weapon }} · {{ $fencer->age_group }} · Rating <b>{{ $fencer->rating }}</b>@if($fencer->homeClub) · {{ $fencer->homeClub->name }}@endif @if($fencer->region()) · {{ $fencer->region() }}@endif
                </div>
            </div>
            <div class="light r" aria-hidden="true"></div>
        </div>

        <div class="stats">
            <div class="stat"><span class="sn">{{ $stats['total'] }}</span><span class="sl">Eligible</span></div>
            <div class="stat"><span class="sn">{{ $stats['nac'] }}</span><span class="sl">NACs</span></div>
            <div class="stat"><span class="sn">{{ $stats['nonneg'] }}</span><span class="sl">Anchors</span></div>
            <div class="stat"><span class="sn">{{ $stats['priority'] }}</span><span class="sl">Priority</span></div>
            <div class="stat"><span class="sn">{{ $stats['drive'] }}</span><span class="sl">Drives</span></div>
            <div class="stat"><span class="sn">{{ $stats['fly'] }}</span><span class="sl">Flights</span></div>
        </div>
    </div>
</header>

<nav class="filters" aria-label="Calendar filters">
    <div class="filters-inner">
        <button class="fb active" data-f="all">All Events</button>
        @if (count($planIds))
            <button class="fb" data-f="plan">My Plan</button>
        @endif
        <button class="fb" data-f="nonneg">Anchors</button>
        <button class="fb" data-f="goals">Goal Path</button>
        <button class="fb" data-f="official">Official Circuit</button>
        <button class="fb" data-f="nac">NACs</button>
        <button class="fb" data-f="priority">Priority + NAC</button>
        <button class="fb" data-f="drive">Driveable</button>
        <button class="fb" data-f="fly">Flights</button>
        <button class="fb" data-f="region">In-Region</button>
        <button class="fb" data-f="home">Home Club</button>
    </div>
</nav>

<main class="main" id="cal">
    @foreach($months as $label => $rows)
        @php([$mname, $myear] = explode(' ', $label))
        <section class="mb" data-month="{{ $label }}">
            <div class="mh">
                <h2 class="mn">{{ $mname }}</h2>
                <span class="my">{{ $myear }}</span>
                <div class="rule"></div>
                <span class="mc">{{ $rows->count() }} event{{ $rows->count() !== 1 ? 's' : '' }}</span>
            </div>
            <div class="cards">
                @foreach($rows as $r)
                    @php($t = $r['tournament'])
                    @php($inPlan = in_array($t->id, $planIds, true))
                    @php($distText = $r['distance'] !== null
                        ? round($r['distance']).' mi · '.($r['driveable'] ? 'drive' : 'flight')
                        : ($t->region ?: ''))
                    <article class="card {{ $r['tier'] }}"
                         style="--d: {{ $loop->index }}"
                         data-tier="{{ $r['tier'] }}"
                         data-nonneg="{{ $r['non_negotiable'] ? 1 : 0 }}"
                         data-nac="{{ $r['is_nac'] ? 1 : 0 }}"
                         data-region="{{ $r['in_region'] ? 1 : 0 }}"
                         data-home="{{ $r['is_home'] ? 1 : 0 }}"
                         data-plan="{{ $inPlan ? 1 : 0 }}"
                         data-local="{{ $t->level === 'local' ? 1 : 0 }}"
                         data-goals="{{ count($r['advances']) ? 1 : 0 }}">
                        <div class="tab t-{{ $r['tier'] }}">
                            <span>{{ $tierLabel[$r['tier']] }}</span>
                            <span class="mono">{{ strtoupper($distText) }}</span>
                        </div>
                        <div class="cbody">
                            <div class="toprow">
                                <div class="datebox">
                                    <div class="mon">{{ $t->starts_on->format('M') }}</div>
                                    <div class="days">{{ $dateDays($t->starts_on, $t->ends_on) }}</div>
                                    <div class="circ">{{ $t->is_nac ? 'NAC' : ($t->circuits ? implode('·', $t->circuits) : ($t->level === 'local' ? 'CLUB' : strtoupper($t->level))) }}</div>
                                </div>
                                <div class="idbox">
                                    <h3 class="cname">{{ $t->name }}</h3>
                                    <div class="cloc">{{ $t->location() }}@if($t->region) · {{ $t->region }}@endif</div>
                                    @if ($r['non_negotiable'] || $inPlan || $t->level === 'local' || ($r['is_home'] && $r['tier'] !== 'home'))
                                        <div class="flagrow">
                                            @if ($r['non_negotiable'])<span class="flag anchor">Anchor</span>@endif
                                            @if ($inPlan)<span class="flag plan">In Plan</span>@endif
                                            @if ($t->level === 'local')<span class="flag club">Club</span>@endif
                                            @if ($r['is_home'] && $r['tier'] !== 'home')<span class="flag homec">Home Club</span>@endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="chips">
                                @foreach($t->contested_events ?? [] as $cat)
                                    @php($isElig = in_array($cat, $r['eligible'], true))
                                    <span class="chip {{ $r['tier'] === 'nac' && $isElig ? 'nac-hl' : ($isElig ? 'elig' : 'dim') }}">{{ $cat }}</span>
                                @endforeach
                            </div>
                            @if (count($r['advances']))
                                <div class="advrow">
                                    @foreach ($r['advances'] as $a)
                                        <span class="adv" title="{{ $a['why'] }}">▸ {{ $a['label'] }}</span>
                                    @endforeach
                                </div>
                            @endif
                            <div class="cnote">
                                {{ $r['note'] }}
                                @if($r['conflict_with'])
                                    <span class="cconflict">⚠ Clashes with {{ $r['conflict_with'] }}, which ranks higher this weekend.</span>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="no-match">No events match this filter in {{ $label }}.</div>
        </section>
    @endforeach

    <div class="fnote">
        <strong>How the board is set.</strong><br>
        Every event is scored for <b>{{ $fencer->name }}</b> specifically: which categories
        {{ $fencer->name }} can enter ({{ implode(', ', $fencer->eligibleCategories()) }}), how far each venue sits
        from home, whether it falls in {{ $fencer->region() }}, and whether it collides with a bigger event the
        same weekend. NACs and home-club events are always anchors; in-region multi-category weekends become
        priorities; far single-category events drop off the board. Change the profile and the whole calendar re-sorts.
    </div>
</main>

</body>
</html>
