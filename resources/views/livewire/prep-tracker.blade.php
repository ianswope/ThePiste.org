<div class="builder">
    <div class="builder-head">
        <div class="eye">Prep · {{ $fencer->name }}</div>
        <h1>{{ $fencer->name }}'s event prep</h1>
        <p>Track each event from registration through coaching, and add it to your calendar. Marking an event registered also stops its reminder email.</p>
    </div>

    @forelse ($items as $item)
        @php
            $t = $item->tournament;
            $progress = $item->prepProgress();
            $registered = in_array($item->status, ['registered', 'attended'], true);
        @endphp
        <div class="panel prepcard" wire:key="prep-{{ $item->id }}">
            <div class="prep-head">
                <div class="prep-id">
                    <div class="prep-date">{{ $t->dateRange() }} · {{ $t->location() }}</div>
                    <div class="prep-name">{{ $t->name }}@if ($item->status === 'attended')<span class="prep-done-tag">fenced</span>@endif</div>
                </div>
                <div class="prep-ready">
                    <span class="mono">{{ $progress['done'] }}/{{ $progress['total'] }} ready</span>
                    <div class="progressbar"><span style="width: {{ round($progress['done'] / $progress['total'] * 100) }}%"></span></div>
                </div>
            </div>

            <div class="prep-grid">
                <div class="prep-cell">
                    <label>Registered</label>
                    <button type="button" class="prep-toggle {{ $registered ? 'on' : '' }}"
                        wire:click="toggleRegistered({{ $item->id }})" @disabled($item->status === 'attended')>
                        {{ $registered ? '✓ Registered' : 'Mark registered' }}
                    </button>
                </div>
                <div class="prep-cell">
                    <label for="paid-{{ $item->id }}">Fees paid</label>
                    <select id="paid-{{ $item->id }}" class="input compact" wire:change="setField({{ $item->id }}, 'paid', $event.target.value)">
                        @foreach (['no' => 'Not paid', 'partial' => 'Partial', 'yes' => 'Paid'] as $v => $lbl)
                            <option value="{{ $v }}" @selected($item->paid === $v)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="prep-cell">
                    <label for="travel-{{ $item->id }}">Travel</label>
                    <select id="travel-{{ $item->id }}" class="input compact" wire:change="setField({{ $item->id }}, 'travel_status', $event.target.value)">
                        @foreach (['pending' => 'Not yet', 'booked' => 'Booked', 'na' => 'N/A'] as $v => $lbl)
                            <option value="{{ $v }}" @selected($item->travel_status === $v)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="prep-cell">
                    <label for="lodging-{{ $item->id }}">Lodging</label>
                    <select id="lodging-{{ $item->id }}" class="input compact" wire:change="setField({{ $item->id }}, 'lodging_status', $event.target.value)">
                        @foreach (['pending' => 'Not yet', 'booked' => 'Booked', 'na' => 'N/A'] as $v => $lbl)
                            <option value="{{ $v }}" @selected($item->lodging_status === $v)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="prep-cell">
                    <label for="coaching-{{ $item->id }}">Coaching</label>
                    <select id="coaching-{{ $item->id }}" class="input compact" wire:change="setField({{ $item->id }}, 'coaching_status', $event.target.value)">
                        @foreach (['undecided' => 'Undecided', 'arranged' => 'Arranged', 'none' => 'Going without'] as $v => $lbl)
                            <option value="{{ $v }}" @selected($item->coaching_status === $v)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="prep-note">
                <label for="note-{{ $item->id }}">Notes</label>
                <textarea id="note-{{ $item->id }}" class="input" rows="2"
                    placeholder="Anything to remember for this event: carpool, hotel, who's going, what to ask the coach."
                    wire:change="setNote({{ $item->id }}, $event.target.value)">{{ $item->notes }}</textarea>
            </div>

            <div class="prep-foot">
                <span class="prep-cal-label">Add to calendar</span>
                <a class="btn btn-ghost btn-sm" href="{{ $t->googleCalendarUrl() }}" target="_blank" rel="noopener">Google</a>
                <a class="btn btn-ghost btn-sm" href="{{ route('event.ics', $t) }}">Apple / .ics</a>
            </div>
        </div>
    @empty
        <div class="panel"><p class="help">No events in your plan yet. <a href="{{ route('season.build') }}">Build your season</a> and they'll show up here to prep.</p></div>
    @endforelse

    <div style="display:flex; gap:10px; margin-top:20px; flex-wrap:wrap;">
        <a class="btn btn-ghost" href="{{ route('calendar') }}">Calendar</a>
        <a class="btn btn-ghost" href="{{ route('season.build') }}">Season builder</a>
        <a class="btn btn-ghost" href="{{ route('season.budget') }}">Budget</a>
        <a class="btn btn-ghost" href="{{ route('season.results') }}">Results</a>
    </div>
</div>
