<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $fencer->name }}'s {{ $season->name }} Season Plan · ThePiste</title>
    <meta name="robots" content="noindex">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,100..900&family=Martian+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style media="print">
        .no-print { display: none !important; }
        body { background: #fff !important; }
        .brow { break-inside: avoid; box-shadow: none !important; }
    </style>
</head>
<body>

<div class="builder" style="padding-bottom:48px;">
    <div class="builder-head">
        <div class="eye">Season plan · {{ $season->name }}</div>
        <h1>{{ $fencer->name }}'s fencing season</h1>
        <p>
            {{ ucfirst($fencer->weapon) }} · {{ $fencer->age_group }} · Rating {{ $fencer->rating }}
            @if ($fencer->homeClub) · {{ $fencer->homeClub->name }} @endif
        </p>
    </div>

    <div class="panel no-print" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:22px;padding:14px 18px;">
        <a class="btn btn-primary" href="{{ route('plan.ics', $plan->share_slug) }}">Download .ics</a>
        <a class="btn btn-ghost" href="webcal://{{ request()->getHost() }}{{ route('plan.ics', $plan->share_slug, false) }}">Subscribe in calendar app</a>
        <button class="btn btn-ghost" onclick="window.print()">Print / PDF</button>
        <span class="help" style="margin:0;">Anyone with this link can view (read-only).</span>
    </div>

    <div class="stat-tiles" style="margin:0 0 26px;">
        <div class="tile"><span class="tn">{{ $tallies['events'] }}</span><span class="tl">Events</span></div>
        <div class="tile"><span class="tn">{{ $tallies['nacs'] }}</span><span class="tl">NACs</span></div>
        <div class="tile"><span class="tn">{{ $tallies['drives'] }}</span><span class="tl">Drives</span></div>
        <div class="tile"><span class="tn">{{ $tallies['flights'] }}</span><span class="tl">Flights</span></div>
        <div class="tile"><span class="tn">{{ $tallies['est_cost'] ? '$'.number_format($tallies['est_cost']) : '–' }}</span><span class="tl">Est. budget</span></div>
    </div>

    @foreach ($months as $label => $rows)
        <div class="bsection">
            <h2>{{ $label }}</h2>
            @foreach ($rows as $r)
                @php($t = $r['tournament'])
                <div class="brow t-{{ $r['tier'] }}">
                    <div class="bmain">
                        <div class="bdate">{{ $t->dateRange(true) }} · {{ $t->region }}</div>
                        <div class="bname">{{ $t->name }}</div>
                        <div class="bmeta">
                            <span>{{ $t->location() }}</span>
                            @if ($r['distance'])<span>{{ round($r['distance']) }} mi · {{ $r['driveable'] ? 'drive' : 'fly' }}</span>@endif
                            @if (! empty($r['eligible']))<span>{{ implode(', ', $r['eligible']) }}</span>@endif
                            @if ($r['est_cost'])<span>~${{ number_format($r['est_cost']) }}</span>@endif
                        </div>
                    </div>
                    <span class="badge b-{{ $r['tier'] }}" style="align-self:center;">{{ strtoupper($r['tier']) }}</span>
                </div>
            @endforeach
        </div>
    @endforeach

    <div class="fnote no-print">
        <strong>Made with ThePiste.</strong> A personalized USA Fencing season planner: eligibility,
        drive-vs-fly, weekend conflicts, and goal tracking, computed for your fencer.
        <a href="{{ url('/') }}" style="color:var(--green-ink);">Build your own season →</a>
    </div>
</div>

</body>
</html>
