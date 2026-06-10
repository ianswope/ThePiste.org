<div class="builder wide">
    <div class="builder-head">
        <div class="eye">Budget · {{ $fencer->name }}</div>
        <h1>{{ $fencer->name }}'s season budget</h1>
        <p>Estimate each trip while planning, then overwrite with real numbers as you book and pay. Skipped events stay on the schedule but drop out of the totals.</p>
    </div>

    <div class="panel" style="margin-bottom:24px;">
        <div class="row-between" style="margin-bottom:14px; flex-wrap:wrap; gap:12px;">
            <label style="display:flex; align-items:center; gap:10px; margin:0;">
                <span style="font-size:9.5px; letter-spacing:.14em; text-transform:uppercase; color:var(--muted); font-weight:700;">Season budget</span>
                <span style="position:relative; display:inline-block;">
                    <span style="position:absolute; left:11px; top:50%; transform:translateY(-50%); color:var(--faint); font-family:'Martian Mono',monospace; font-size:13.5px;">$</span>
                    <input class="input money" style="width:130px; padding-left:24px;" type="number" min="0" max="999999.99" step="100"
                           wire:model.blur="budget" placeholder="12000">
                </span>
            </label>
            <div class="layer-toggle" role="tablist" aria-label="Which numbers to edit">
                <button type="button" class="{{ $layer === 'est' ? 'on' : '' }}" wire:click="setLayer('est')">Estimates</button>
                <button type="button" class="{{ $layer === 'actual' ? 'on' : '' }}" wire:click="setLayer('actual')">Actuals</button>
            </div>
        </div>

        <div class="stat-tiles" style="margin-top:0;">
            <div class="tile"><span class="tn">${{ number_format($summary['projected']) }}</span><span class="tl">Projected</span></div>
            <div class="tile">
                @if ($summary['surplus'] !== null)
                    <span class="tn" style="color:{{ $summary['surplus'] < 0 ? 'var(--red-ink)' : 'var(--green-ink)' }}">${{ number_format(abs($summary['surplus'])) }}</span>
                    <span class="tl">{{ $summary['surplus'] < 0 ? 'Over budget' : 'Budget left' }}</span>
                @else
                    <span class="tn">—</span><span class="tl">Budget left</span>
                @endif
            </div>
            <div class="tile"><span class="tn">${{ number_format($summary['paid']) }}</span><span class="tl">Paid</span></div>
            <div class="tile"><span class="tn">${{ number_format($summary['to_pay']) }}</span><span class="tl">Still to pay</span></div>
            <div class="tile"><span class="tn">${{ number_format($summary['avg']) }}</span><span class="tl">Avg / event</span></div>
            <div class="tile"><span class="tn">{{ $summary['done'] }}/{{ $summary['total'] }}</span><span class="tl">Done</span></div>
        </div>

        <div class="cat-totals">
            @foreach ($categories as $key => $label)
                <span><em>{{ $label }}</em> ${{ number_format($summary['by_category'][$key]) }}</span>
            @endforeach
            @if ($summary['unitemized'] > 0)
                <span><em>Ballpark</em> ${{ number_format($summary['unitemized']) }}</span>
            @endif
        </div>
    </div>

    <div class="panel" style="overflow-x:auto;">
        <div class="row-between" style="margin-bottom:10px;">
            <strong style="font-size:15px;">Trip costs <span style="color:var(--faint); font-weight:400;">— editing {{ $layer === 'est' ? 'estimates' : 'actuals' }}</span></strong>
        </div>

        @if ($items->isEmpty())
            <p class="help">Nothing on the plan yet. <a href="{{ route('season.build') }}">Build the season</a> first and the events show up here.</p>
        @else
            <table class="budget-table">
                <thead>
                    <tr>
                        <th style="text-align:left;">Event</th>
                        @foreach ($categories as $label)
                            <th>{{ $label }}</th>
                        @endforeach
                        <th>Total</th>
                        <th>Status</th>
                        <th>Paid</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        @php $t = $item->tournament; @endphp
                        <tr wire:key="budget-item-{{ $item->id }}" class="{{ $item->status === 'skipped' ? 'skipped' : '' }}">
                            <td style="text-align:left;">
                                <div class="bt-name">{{ $t->name }}</div>
                                <div class="bt-meta">{{ $t->starts_on->format('M j') }}{{ $t->ends_on->ne($t->starts_on) ? '–'.$t->ends_on->format($t->ends_on->month === $t->starts_on->month ? 'j' : 'M j') : '' }} · {{ $t->location() }}</div>
                            </td>
                            @foreach ($categories as $key => $label)
                                @php $est = $item->expenses->firstWhere('category', $key)?->est_amount; @endphp
                                <td>
                                    <input class="input money" type="number" min="0" max="999999.99" step="0.01" inputmode="decimal"
                                           wire:model.blur="amounts.{{ $item->id }}.{{ $key }}"
                                           placeholder="{{ $layer === 'actual' && $est !== null ? number_format($est, 2, '.', '') : '' }}"
                                           aria-label="{{ $t->name }} {{ $label }} {{ $layer === 'est' ? 'estimate' : 'actual' }}">
                                </td>
                            @endforeach
                            <td class="bt-total">{{ $item->effectiveTotal() > 0 ? '$'.number_format($item->effectiveTotal(), 2) : '—' }}</td>
                            <td>
                                <select class="input compact" wire:model.change="statuses.{{ $item->id }}" aria-label="{{ $t->name }} status">
                                    @foreach (\App\Models\PlanItem::STATUSES as $s)
                                        <option value="{{ $s }}">{{ ucfirst($s) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select class="input compact paid-{{ $paids[$item->id] ?? 'no' }}" wire:model.change="paids.{{ $item->id }}" aria-label="{{ $t->name }} paid">
                                    @foreach (\App\Models\PlanItem::PAID_STATES as $p)
                                        <option value="{{ $p }}">{{ ucfirst($p) }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="help" style="margin-top:10px;">Leave a cell blank when a cost doesn't apply. In Actuals, the grey hint is your estimate. Mark an event Registered and the registration reminder emails stand down.</p>
        @endif
    </div>

    <div style="display:flex; gap:10px; margin-top:20px;">
        <a class="btn btn-ghost" href="{{ route('calendar') }}">Calendar</a>
        <a class="btn btn-ghost" href="{{ route('season.build') }}">Season builder</a>
        <a class="btn btn-ghost" href="{{ route('season.results') }}">Results</a>
    </div>
</div>
