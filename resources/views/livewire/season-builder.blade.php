<div class="builder">
    <div class="builder-head">
        <div class="eye">Season builder · {{ $fencer->name }}</div>
        <h1>Build {{ $fencer->name }}'s season</h1>
        <p>Your anchors are locked in. Add the events that fit the goal and the budget. The plan saves as you go.</p>
    </div>

    <div class="goalbar">
        <label for="goal">Working toward</label>
        <select id="goal" wire:model.live="goal">
            <option value="">Not sure yet</option>
            @foreach ($goals as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
        <span class="hint">The goal drives which events are recommended.</span>
    </div>

    @php
        $meta = [
            'anchors' => ['Anchors', 'NACs and home-club events you should not miss. Pre-locked.'],
            'value' => ['Best value', 'In-region, multi-category, or driveable. Strong points per dollar.'],
            'optional' => ['Optional fly trips', 'Worth it when momentum or points call for it.'],
            'rest' => ['Lower priority', 'Long, single-category, or out of region.'],
        ];
    @endphp

    @foreach ($meta as $key => [$heading, $desc])
        @php $group = $sections[$key]; @endphp
        @if ($group->isNotEmpty())
            <div class="bsection">
                <h2>{{ $heading }} <span class="cnt">{{ $group->count() }}</span></h2>
                <p class="desc">{{ $desc }}</p>
                @foreach ($group as $r)
                    @php
                        $t = $r['tournament'];
                        $sel = in_array($t->id, $selectedIds, true);
                        $clash = in_array($t->id, $clashIds, true);
                    @endphp
                    <div class="brow t-{{ $r['tier'] }} {{ $sel ? 'selected' : '' }} {{ $clash ? 'clashing' : '' }}" wire:key="row-{{ $t->id }}">
                        <div class="bmain">
                            <div class="bdate">{{ $t->starts_on->format('M j') }} · {{ $t->region }}@if($t->circuits) · {{ implode('/', $t->circuits) }}@endif{{ $t->level === 'local' ? ' · CLUB' : '' }}{{ $t->country !== 'US' ? ' · '.$t->country : '' }}</div>
                            <div class="bname">{{ $t->name }}</div>
                            <div class="bmeta">
                                <span>📍 {{ $t->city }}, {{ $t->state }}</span>
                                @if ($r['distance'])<span>{{ round($r['distance']) }} mi · {{ $r['driveable'] ? 'drive' : 'fly' }}</span>@endif
                                @if (! empty($r['eligible']))<span>{{ implode(', ', $r['eligible']) }}</span>@endif
                                @if ($clash)
                                    <span class="conflict">⚠ Both this and {{ $r['conflict_with'] }} are in the plan — same weekend, pick one</span>
                                @elseif ($r['conflict_with'])
                                    <span class="conflict">⚠ clashes with {{ $r['conflict_with'] }}</span>
                                @endif
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            @if ($sel)
                                <span class="costwrap">$<input class="input costinput" type="number" min="0" step="25"
                                    wire:model.blur="costs.{{ $t->id }}" placeholder="cost"></span>
                            @endif
                            <button class="btoggle" wire:click="toggle({{ $t->id }})" wire:loading.attr="disabled">
                                {{ $sel ? '✓ In plan' : '+ Add' }}
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endforeach

    <div class="bsummary">
        <div class="inner">
            <div class="nums">
                <span class="n">{{ $tally['count'] }}<small>Events</small></span>
                <span class="n">{{ $tally['nacs'] }}<small>NACs</small></span>
                <span class="n">{{ $tally['drives'] }}<small>Drives</small></span>
                <span class="n">{{ $tally['flights'] }}<small>Flights</small></span>
                <span class="n">{{ $tally['est_cost'] ? '$'.number_format($tally['est_cost']) : '—' }}<small>Budget</small></span>
                @if (count($clashIds))
                    <span class="n" style="color:var(--red-ink);">{{ count($clashIds) }}<small style="color:var(--red-ink);">Clashes</small></span>
                @endif
            </div>
            <div class="grow"></div>
            <a class="btn btn-ghost" href="{{ route('plan.share', $plan->share_slug) }}" target="_blank">Share / export</a>
            <a class="btn btn-ghost" href="{{ route('calendar') }}">Calendar</a>
            <a class="btn btn-ghost" href="{{ route('season.results') }}">Results</a>
            <span class="saved">Saved automatically</span>
        </div>
    </div>
</div>
