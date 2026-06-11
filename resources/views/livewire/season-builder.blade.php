<div class="builder">
    <div class="builder-head">
        <div class="eye">Season builder · {{ $fencer->name }}</div>
        <h1>Build {{ $fencer->name }}'s season</h1>
        <p>Your anchors are locked in. Add the events that fit the goal and the budget. The plan saves as you go.</p>
    </div>

    <div class="goalpanel">
        <div class="goalpanel-head">
            <span class="gp-title">Season goals</span>
            <span class="hint">Events that advance a goal get marked <span class="adv">▸</span> across the calendar and win weekend ties.</span>
        </div>
        <div class="goalchips">
            @forelse ($goals as $g)
                <span class="goalchip" wire:key="goal-{{ $g->id }}">
                    {{ $g->label() }}
                    <button type="button" class="gx" wire:click="removeGoal({{ $g->id }})" aria-label="Remove goal">&times;</button>
                </span>
            @empty
                <span class="hint">No goals yet. Add one and the whole calendar starts pointing at it.</span>
            @endforelse
        </div>

        @if ($goalType === '')
            <button type="button" class="btoggle" wire:click="$set('goalType', 'rating')">+ Add a goal</button>
        @else
            <div class="goalform">
                <select class="input" wire:model.live="goalType" aria-label="Goal type">
                    @foreach ($goalTypes as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>

                @if ($goalType === 'rating')
                    <select class="input" wire:model="goalRating" aria-label="Target rating">
                        @foreach (['E', 'D', 'C', 'B', 'A'] as $r)
                            <option value="{{ $r }}">{{ $r }}</option>
                        @endforeach
                    </select>
                    <select class="input" wire:model="goalWeapon" aria-label="Weapon">
                        @foreach ($weaponsList as $w)
                            <option value="{{ $w }}">{{ ucfirst($w) }}</option>
                        @endforeach
                    </select>
                @elseif ($goalType === 'qualify')
                    <select class="input" wire:model="goalTarget" aria-label="Championship">
                        @foreach ($qualifyTargets as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                @elseif ($goalType === 'standing')
                    <select class="input" wire:model="goalCategory" aria-label="Category">
                        <option value="">Any category</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat }}">{{ $cat }}</option>
                        @endforeach
                    </select>
                @elseif ($goalType === 'develop')
                    <span class="costwrap"><input class="input costinput" type="number" min="1" max="60" wire:model="goalEvents" aria-label="Number of events"> events</span>
                @endif

                <button type="button" class="btn btn-primary" style="padding:9px 15px;font-size:13.5px;" wire:click="addGoal">Add</button>
                <button type="button" class="btoggle" wire:click="$set('goalType', '')">Cancel</button>
            </div>
        @endif
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
                                <span>{{ $t->location() }}</span>
                                @if ($r['distance'])<span>{{ round($r['distance']) }} mi · {{ $r['driveable'] ? 'drive' : 'fly' }}</span>@endif
                                @if (! empty($r['eligible']))<span>{{ implode(', ', $r['eligible']) }}</span>@endif
                                @foreach ($r['advances'] as $a)
                                    <span class="adv" title="{{ $a['why'] }}">▸ {{ $a['label'] }}</span>
                                @endforeach
                                @if ($clash)
                                    <span class="conflict">⚠ Both this and {{ $r['conflict_with'] }} are in the plan: same weekend, pick one</span>
                                @elseif ($r['conflict_with'])
                                    <span class="conflict">⚠ clashes with {{ $r['conflict_with'] }}</span>
                                @endif
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            @if ($sel)
                                @php $pi = $planItems[$t->id] ?? null; @endphp
                                @if ($pi && $pi->expenses->isNotEmpty())
                                    <a class="costwrap itemized" href="{{ route('season.budget') }}" title="Itemized on the budget page">~${{ number_format($pi->effectiveTotal()) }}</a>
                                @else
                                    <span class="costwrap">$<input class="input costinput" type="number" min="0" step="25"
                                        wire:model.blur="costs.{{ $t->id }}" placeholder="cost"></span>
                                @endif
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
                <span class="n">{{ $tally['est_cost'] ? '$'.number_format($tally['est_cost']) : '–' }}<small>Budget</small></span>
                @if (count($clashIds))
                    <span class="n alert">{{ count($clashIds) }}<small>Clashes</small></span>
                @endif
            </div>
            <div class="grow"></div>
            <a class="btn btn-ghost" href="{{ route('plan.share', $plan->share_slug) }}" target="_blank">Share / export</a>
            <a class="btn btn-ghost" href="{{ route('calendar') }}">Calendar</a>
            <a class="btn btn-ghost" href="{{ route('season.prep') }}">Prep</a>
            <a class="btn btn-ghost" href="{{ route('season.results') }}">Results</a>
            <a class="btn btn-ghost" href="{{ route('season.budget') }}">Budget</a>
            <span class="saved">Saved automatically</span>
        </div>
    </div>
</div>
