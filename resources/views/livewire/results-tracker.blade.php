<div class="builder">
    <div class="builder-head">
        <div class="eye">Results · {{ $fencer->name }}</div>
        <h1>{{ $fencer->name }}'s results &amp; progress</h1>
        <p>Log how each event went. Earned ratings update the profile automatically, and the season adds up toward the goal.</p>
    </div>

    @if (session('rating_upgraded'))
        <div class="ok-box">🎉 {{ session('rating_upgraded') }}</div>
    @endif

    <div class="panel" style="margin-bottom:24px;">
        <div class="row-between" style="margin-bottom:14px;">
            <strong style="font-size:15px;">{{ count($goalCards) ? 'Season goals' : 'No goals set yet' }}</strong>
            <span style="font-family:'Martian Mono',monospace;font-size:13px;color:var(--muted);">{{ ucfirst($fencer->weapon) }} · current {{ $fencer->rating }}</span>
        </div>

        @foreach ($goalCards as $gc)
            <div class="goalrow" wire:key="goalcard-{{ $gc['id'] }}">
                <span class="adv">▸</span>
                <span class="gname">{{ $gc['label'] }}</span>
                <span class="gdetail">{{ $gc['detail'] }}</span>
                @if ($gc['progress'] !== null && $gc['type'] !== 'rating')
                    <div class="progressbar gmini"><span style="width: {{ round($gc['progress'] * 100) }}%"></span></div>
                @endif
            </div>
        @endforeach

        @if ($progress !== null)
            <div class="ladder">
                @foreach ($ladder as $r)
                    @php
                        $currentLetter = strtoupper(substr($fencer->rating, 0, 1));
                        $isCurrent = $r === $currentLetter;
                        $isTarget = $r === $fencer->targetRating();
                        $reached = array_search($r, $ladder, true) <= (array_search($currentLetter, $ladder, true) ?: 0);
                    @endphp
                    <span class="lrung {{ $reached ? 'reached' : '' }} {{ $isCurrent ? 'current' : '' }} {{ $isTarget ? 'target' : '' }}">{{ $r }}</span>
                @endforeach
            </div>
            <div class="progressbar"><span style="width: {{ round($progress * 100) }}%"></span></div>
            <p class="help" style="margin-top:8px;">{{ round($progress * 100) }}% of the way from U to {{ $fencer->targetRating() }} on the rating ladder.</p>
        @elseif (! count($goalCards))
            <p class="help">Set goals in the season builder and progress shows up here after each logged result.</p>
        @endif

        <div class="stat-tiles">
            <div class="tile"><span class="tn">{{ $stats['events'] }}</span><span class="tl">Events</span></div>
            <div class="tile"><span class="tn">{{ $stats['wins'] }}</span><span class="tl">Wins</span></div>
            <div class="tile"><span class="tn">{{ $stats['podiums'] }}</span><span class="tl">Podiums</span></div>
            <div class="tile"><span class="tn">{{ $stats['top8'] }}</span><span class="tl">Top 8</span></div>
            <div class="tile"><span class="tn">{{ $stats['points'] }}</span><span class="tl">Points</span></div>
        </div>
    </div>

    <div class="panel" style="margin-bottom:24px;">
        <div class="section-h" style="margin-top:0;">Log a result</div>
        <form wire:submit="save">
            <div class="form-grid">
                <div class="field full">
                    <label for="tournament_id">Tournament <span style="color:var(--faint)">(optional — pick from the calendar)</span></label>
                    <select class="input" id="tournament_id" wire:model="tournament_id">
                        <option value="">Not on the calendar / other</option>
                        @foreach ($tournaments as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="event_name">Event</label>
                    <input class="input" id="event_name" wire:model="event_name" placeholder="e.g. Junior Women's Foil" required>
                    @error('event_name')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="fenced_on">Date</label>
                    <input class="input" type="date" id="fenced_on" wire:model="fenced_on" required>
                </div>
                <div class="field">
                    <label for="weapon">Weapon</label>
                    <select class="input" id="weapon" wire:model="weapon">
                        @foreach ($fencer->weapons as $w)
                            <option value="{{ $w->weapon }}">{{ ucfirst($w->weapon) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="category">Category</label>
                    <select class="input" id="category" wire:model="category">
                        <option value="">—</option>
                        @foreach (['Y10','Y12','Y14','CDT','JNR','D1A','DV2','OPEN','VET'] as $cat)
                            <option value="{{ $cat }}">{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="place">Finish (place)</label>
                    <input class="input" type="number" min="1" id="place" wire:model="place" placeholder="e.g. 3" required>
                    @error('place')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="field_size">Field size <span style="color:var(--faint)">(optional)</span></label>
                    <input class="input" type="number" min="1" id="field_size" wire:model="field_size" placeholder="e.g. 42">
                </div>
                <div class="field">
                    <label for="rating_earned">Rating earned <span style="color:var(--faint)">(optional)</span></label>
                    <input class="input" id="rating_earned" wire:model="rating_earned" placeholder="e.g. C26">
                </div>
                <div class="field">
                    <label for="points">Points earned <span style="color:var(--faint)">(optional)</span></label>
                    <input class="input" type="number" step="0.1" min="0" id="points" wire:model="points">
                </div>
                <div class="field full">
                    <label for="notes">Notes <span style="color:var(--faint)">(optional)</span></label>
                    <input class="input" id="notes" wire:model="notes" placeholder="What worked, what to train next">
                </div>
            </div>
            <div style="margin-top:14px;">
                <button class="btn btn-primary" type="submit">Save result</button>
            </div>
        </form>
    </div>

    <div class="bsection">
        <h2>Logged results <span class="cnt">{{ $results->count() }}</span></h2>
        @forelse ($results as $r)
            <div class="rrow" wire:key="result-{{ $r->id }}">
                <div class="rplace {{ $r->isPodium() ? 'podium' : '' }}">{{ $r->place }}<small>{{ $r->field_size ? '/'.$r->field_size : '' }}</small></div>
                <div class="rmain">
                    <div class="bdate">{{ $r->fenced_on->format('M j, Y') }} @if($r->category) · {{ $r->category }} @endif · {{ ucfirst($r->weapon) }}</div>
                    <div class="bname">{{ $r->event_name }}</div>
                    <div class="bmeta">
                        @if ($r->tournament)<span>{{ $r->tournament->name }}</span>@endif
                        @if ($r->rating_earned)<span style="color:var(--green-ink);font-weight:600;">earned {{ $r->rating_earned }}</span>@endif
                        @if ($r->points)<span>{{ $r->points }} pts</span>@endif
                        @if ($r->notes)<span>{{ $r->notes }}</span>@endif
                    </div>
                </div>
                <button class="btoggle" wire:click="delete({{ $r->id }})" wire:confirm="Delete this result?">Delete</button>
            </div>
        @empty
            <p class="help">No results yet. After the first tournament, log how it went here.</p>
        @endforelse
    </div>
</div>
