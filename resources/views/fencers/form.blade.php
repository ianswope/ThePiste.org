@extends('layouts.app')

@php $editing = $fencer->exists; @endphp

@section('content')
<div class="page">
    <div class="auth-eye" style="margin-bottom:6px;">{{ $editing ? 'Edit profile' : 'New fencer' }}</div>
    <h1 style="font-size:clamp(24px,4vw,32px);font-weight:700;letter-spacing:-.02em;margin:0 0 6px;">
        {{ $editing ? $fencer->name : "Let's build a season" }}
    </h1>
    <p style="color:var(--muted);margin:0 0 22px;">Tell us about the fencer. The calendar personalizes to this profile and the goal you set.</p>

    @if ($errors->any())
        <div class="err-box">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ $editing ? route('fencers.update', $fencer) : route('fencers.store') }}" class="panel">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="form-grid">
            <div class="field full">
                <label for="name">Fencer name</label>
                <input class="input" id="name" name="name" value="{{ old('name', $fencer->name) }}" required autofocus>
            </div>

            <div class="field">
                <label>Competes in</label>
                <div class="seg">
                    @foreach (['women' => 'Women', 'men' => 'Men', 'mixed' => 'Mixed'] as $val => $lbl)
                        <label><input type="radio" name="gender" value="{{ $val }}" @checked(old('gender', $fencer->gender) === $val)><span>{{ $lbl }}</span></label>
                    @endforeach
                </div>
            </div>

            <div class="field">
                <label>Handedness</label>
                <div class="seg">
                    @foreach (['right' => 'Right', 'left' => 'Left'] as $val => $lbl)
                        <label><input type="radio" name="handedness" value="{{ $val }}" @checked(old('handedness', $fencer->handedness) === $val)><span>{{ $lbl }}</span></label>
                    @endforeach
                </div>
            </div>

            <div class="field">
                <label for="age_group">Age category</label>
                <select class="input" id="age_group" name="age_group" required>
                    @foreach ($ageGroups as $ag)
                        <option value="{{ $ag }}" @selected(old('age_group', $fencer->age_group) === $ag)>{{ $ag }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="birth_year">Birth year <span style="color:var(--faint)">(optional)</span></label>
                <input class="input" id="birth_year" name="birth_year" type="number" min="1930" max="2026" value="{{ old('birth_year', $fencer->birth_year) }}">
            </div>
        </div>

        <div class="section-h">Weapons &amp; ratings</div>
        @php
            $primary = old('primary_weapon', optional($currentWeapons->firstWhere('is_primary', true))->weapon ?? $currentWeapons->keys()->first());
        @endphp
        @foreach ($weaponsList as $w)
            @php $cw = $currentWeapons->get($w); @endphp
            <div class="weapon-row">
                <label class="checkline"><input type="checkbox" name="compete_{{ $w }}" value="1" @checked(old("compete_{$w}", (bool) $cw))> <span class="wname">{{ $w }}</span></label>
                <label class="checkline" style="color:var(--muted);font-size:12.5px;"><input type="radio" name="primary_weapon" value="{{ $w }}" @checked($primary === $w)> primary</label>
                <select class="input" name="rating_{{ $w }}">
                    @foreach ($ratings as $r)
                        <option value="{{ $r }}" @selected(old("rating_{$w}", optional($cw)->rating ?? 'U') === $r)>{{ $r === 'U' ? 'U (unrated)' : $r }}</option>
                    @endforeach
                </select>
            </div>
        @endforeach
        <p class="help">Ratings are per weapon. Tick each weapon the fencer competes and set its rating.</p>

        <div class="section-h">Home base &amp; club</div>
        <div class="form-grid">
            <div class="field">
                <label for="home_zip">Home ZIP</label>
                <input class="input" id="home_zip" name="home_zip" value="{{ old('home_zip', $fencer->home_zip) }}" inputmode="numeric" placeholder="e.g. 60515">
                <p class="help">Used to compute drive vs fly for every event.</p>
            </div>
            <div class="field">
                <label for="home_club_id">Home club</label>
                <select class="input" id="home_club_id" name="home_club_id">
                    <option value="">None / not listed</option>
                    @foreach ($clubs as $club)
                        <option value="{{ $club->id }}" @selected((int) old('home_club_id', $fencer->home_club_id) === $club->id)>{{ $club->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="drive_radius_miles">Max drive distance (miles)</label>
                <input class="input" id="drive_radius_miles" name="drive_radius_miles" type="number" min="50" max="4000" step="25" value="{{ old('drive_radius_miles', $fencer->drive_radius_miles ?: 450) }}" required>
                <p class="help">Beyond this, events are flagged as fly trips.</p>
            </div>
            <div class="field">
                <label for="usa_fencing_id">USA Fencing ID <span style="color:var(--faint)">(optional)</span></label>
                <input class="input" id="usa_fencing_id" name="usa_fencing_id" value="{{ old('usa_fencing_id', $fencer->usa_fencing_id) }}">
                <p class="help">Find ratings &amp; history on <a href="https://fencingtracker.com" target="_blank" rel="noopener" style="color:var(--green-ink);">FencingTracker</a> or the <a href="https://member.usafencing.org/search/members" target="_blank" rel="noopener" style="color:var(--green-ink);">USA Fencing member search</a>.</p>
            </div>
        </div>

        <div class="section-h">Goal</div>
        <div class="field full">
            <label for="goal">This season's goal</label>
            <select class="input" id="goal" name="goal">
                <option value="">Not sure yet</option>
                @foreach ($goals as $key => $label)
                    <option value="{{ $key }}" @selected(old('goal', $fencer->goal) === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <p class="help">The goal drives which events the planner prioritizes.</p>
        </div>
        <div class="field full">
            <label class="checkline">
                <input type="checkbox" name="include_fie" value="1" @checked(old('include_fie', $fencer->include_fie))>
                Show FIE international events (World Cups, European circuit) on the calendar
            </label>
        </div>

        <div style="margin-top:26px;display:flex;gap:10px;flex-wrap:wrap;">
            <button class="btn btn-primary" type="submit">{{ $editing ? 'Save changes' : 'Build my calendar' }}</button>
            <a class="btn btn-ghost" href="{{ url('/') }}">Cancel</a>
        </div>
    </form>
</div>
@endsection
