@php
    $axisLabels = ['backlog' => 'Backlog', 'sla' => 'SLA', 'escalation' => 'Eskalation', 'workload' => 'Workload'];
    $colorTones = [
        'red'    => ['bg' => 'bg-rose-50',    'border' => 'border-rose-300',    'fg' => 'text-rose-700',    'dot' => 'bg-rose-500',    'fill' => 'bg-rose-500',    'label' => 'Brennt'],
        'yellow' => ['bg' => 'bg-amber-50',   'border' => 'border-amber-300',   'fg' => 'text-amber-700',   'dot' => 'bg-amber-500',   'fill' => 'bg-amber-500',   'label' => 'Achtung'],
        'green'  => ['bg' => 'bg-emerald-50', 'border' => 'border-emerald-300', 'fg' => 'text-emerald-700', 'dot' => 'bg-emerald-500', 'fill' => 'bg-emerald-500', 'label' => 'Stabil'],
        'gray'   => ['bg' => 'bg-zinc-50',    'border' => 'border-zinc-300',    'fg' => 'text-zinc-600',    'dot' => 'bg-zinc-400',    'fill' => 'bg-zinc-400',    'label' => 'Keine Daten'],
    ];
    $tone = fn ($c) => $colorTones[$c ?: 'gray'] ?? $colorTones['gray'];
    $distTotal = max(1, array_sum($byColor));
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Helpdesk Health-Index" icon="heroicon-o-heart" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Helpdesk', 'href' => route('helpdesk.dashboard'), 'icon' => 'home'],
            ['label' => 'Health-Index'],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" icon="heroicon-o-funnel" width="w-72" :defaultOpen="true">
            <div class="p-4 space-y-4 bg-[var(--ui-muted-5)]">

                <section class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Über</h3>
                    <p class="text-[11px] text-[var(--ui-secondary)] leading-relaxed m-0">
                        Teamweite Health-Sicht aller Helpdesk-Boards. Daten aus dem jüngsten nächtlichen Snapshot.
                    </p>
                    @if($lastTakenOn)
                        <p class="text-[10px] text-[var(--ui-muted)] mt-1 m-0">Stand: {{ $lastTakenOn->format('d.m.Y') }}</p>
                    @endif
                </section>

                <section class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Ampel</h3>
                    <div class="space-y-1">
                        <button wire:click="$set('colorFilter', 'all')"
                                class="w-full flex items-center justify-between px-2 py-1.5 rounded text-[12px] transition-colors {{ $colorFilter === 'all' ? 'bg-[var(--ui-secondary)] text-white' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                            <span>Alle</span>
                            <span class="tabular-nums opacity-70 text-[11px]">{{ array_sum($byColor) }}</span>
                        </button>
                        @foreach(['red', 'yellow', 'green', 'gray'] as $c)
                            @php $t = $tone($c); @endphp
                            <button wire:click="$set('colorFilter', '{{ $c }}')"
                                    class="w-full flex items-center justify-between px-2 py-1.5 rounded text-[12px] transition-colors {{ $colorFilter === $c ? $t['bg'].' '.$t['fg'].' border '.$t['border'] : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                                <span class="inline-flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full {{ $t['dot'] }}"></span>
                                    <span>{{ $t['label'] }}</span>
                                </span>
                                <span class="tabular-nums opacity-70 text-[11px]">{{ $byColor[$c] ?? 0 }}</span>
                            </button>
                        @endforeach
                    </div>
                </section>

                <section class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Schwächste Achse</h3>
                    <div class="space-y-1">
                        <button wire:click="$set('axisFilter', 'all')"
                                class="w-full text-left px-2 py-1 rounded text-[11px] transition-colors {{ $axisFilter === 'all' ? 'bg-emerald-50 text-emerald-700 font-medium' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                            Alle
                        </button>
                        @foreach($axisLabels as $axisKey => $axisName)
                            <button wire:click="$set('axisFilter', '{{ $axisKey }}')"
                                    class="w-full flex items-center justify-between px-2 py-1 rounded text-[11px] transition-colors {{ $axisFilter === $axisKey ? 'bg-emerald-50 text-emerald-700 font-medium' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                                <span>{{ $axisName }}</span>
                                <span class="tabular-nums opacity-60">{{ $byAxis[$axisKey] ?? 0 }}</span>
                            </button>
                        @endforeach
                    </div>
                </section>

                <section class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">SLA-Coverage</h3>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach([
                            'all' => 'Alle ('.$totalAll.')',
                            'with_sla' => 'mit SLA ('.$slaCoverage['with'].')',
                            'without_sla' => 'ohne SLA ('.$slaCoverage['without'].')',
                        ] as $key => $label)
                            <button wire:click="$set('slaFilter', '{{ $key }}')"
                                    class="px-2.5 py-1 text-[11px] rounded-full font-medium transition-colors {{ $slaFilter === $key ? 'bg-[var(--ui-secondary)] text-white' : 'bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-10)]' }}">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </section>

                <section class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Sortierung</h3>
                    <div class="space-y-1">
                        @foreach([
                            'worst' => 'Schlimmste zuerst',
                            'best' => 'Beste zuerst',
                            'confidence' => 'Geringste Confidence zuerst',
                            'movement' => 'Letzte Bewegung zuerst',
                            'name' => 'Name (A→Z)',
                        ] as $key => $label)
                            <button wire:click="$set('sort', '{{ $key }}')"
                                    class="w-full text-left px-2 py-1 rounded text-[11px] transition-colors {{ $sort === $key ? 'bg-emerald-50 text-emerald-700 font-medium' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </section>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Bewegung" icon="heroicon-o-bolt" width="w-80" :defaultOpen="true" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-4 bg-[var(--ui-muted-5)]">
                <section class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Scope</h3>
                    <div class="grid grid-cols-2 gap-2 text-center">
                        <div>
                            <div class="text-xl font-bold tabular-nums text-[var(--ui-secondary)]">{{ $totalAll }}</div>
                            <div class="text-[10px] uppercase tracking-wider text-[var(--ui-muted)]">Boards</div>
                        </div>
                        <div>
                            <div class="text-xl font-bold tabular-nums text-[var(--ui-secondary)]">{{ $movedBoardsCount }}</div>
                            <div class="text-[10px] uppercase tracking-wider text-[var(--ui-muted)]">bewegt vs Vortag</div>
                        </div>
                    </div>
                    @if($lastTakenOn)
                        <div class="mt-3 pt-3 border-t border-[var(--ui-border)]/40 text-[10px] text-[var(--ui-muted)] text-center">
                            Snapshot {{ $lastTakenOn->format('d.m.Y') }} · Cron nächtlich 03:15
                        </div>
                    @endif
                </section>

                @if($topGainers->isNotEmpty())
                    <section class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm">
                        <h3 class="text-[10px] font-semibold uppercase tracking-wider text-emerald-700 mb-2 inline-flex items-center gap-1.5">
                            @svg('heroicon-o-arrow-trending-up', 'w-3 h-3')
                            <span>Größte Gewinner</span>
                        </h3>
                        <ul class="space-y-1.5">
                            @foreach($topGainers as $s)
                                @php $t = $tone($s->health_color); @endphp
                                <li>
                                    <a href="{{ route('helpdesk.boards.health', $s->helpdesk_board_id) }}" wire:navigate
                                       class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-[var(--ui-muted-5)] transition-colors group">
                                        <span class="w-2 h-2 rounded-full {{ $t['dot'] }} flex-shrink-0"></span>
                                        <span class="flex-1 min-w-0 text-[12px] text-[var(--ui-secondary)] truncate group-hover:text-emerald-700">{{ $s->board?->name }}</span>
                                        <span class="text-[11px] tabular-nums font-semibold text-emerald-600 flex-shrink-0">↑{{ $s->delta_health_score }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if($topLosers->isNotEmpty())
                    <section class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm">
                        <h3 class="text-[10px] font-semibold uppercase tracking-wider text-rose-700 mb-2 inline-flex items-center gap-1.5">
                            @svg('heroicon-o-arrow-trending-down', 'w-3 h-3')
                            <span>Größte Verlierer</span>
                        </h3>
                        <ul class="space-y-1.5">
                            @foreach($topLosers as $s)
                                @php $t = $tone($s->health_color); @endphp
                                <li>
                                    <a href="{{ route('helpdesk.boards.health', $s->helpdesk_board_id) }}" wire:navigate
                                       class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-[var(--ui-muted-5)] transition-colors group">
                                        <span class="w-2 h-2 rounded-full {{ $t['dot'] }} flex-shrink-0"></span>
                                        <span class="flex-1 min-w-0 text-[12px] text-[var(--ui-secondary)] truncate group-hover:text-emerald-700">{{ $s->board?->name }}</span>
                                        <span class="text-[11px] tabular-nums font-semibold text-rose-600 flex-shrink-0">↓{{ abs($s->delta_health_score) }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <div class="flex-1 min-w-0 min-h-0 flex flex-col overflow-hidden">
        {{-- Header --}}
        <div class="px-4 pt-3 pb-3 border-b border-[var(--ui-border)]/40 bg-white">
            <div class="flex items-start justify-between gap-6 mb-3">
                <div class="min-w-0">
                    <h1 class="text-base font-semibold text-[var(--ui-secondary)] truncate m-0 leading-tight inline-flex items-center gap-2">
                        @svg('heroicon-o-heart', 'w-4 h-4 text-rose-500')
                        Helpdesk Health-Index
                    </h1>
                    <p class="text-[11px] text-[var(--ui-muted)] mt-0.5 m-0">
                        Teamweite Sicht — {{ $totalAll }} Board{{ $totalAll === 1 ? '' : 's' }}, sortiert nach
                        @switch($sort)
                            @case('best') Score absteigend @break
                            @case('confidence') Confidence aufsteigend @break
                            @case('movement') letzter Bewegung @break
                            @case('name') Name @break
                            @default schwächster zuerst
                        @endswitch
                    </p>
                </div>
                <div class="flex items-center gap-4 flex-shrink-0 text-[11px]">
                    @foreach(['red' => 'Brennt', 'yellow' => 'Achtung', 'green' => 'Stabil', 'gray' => 'Keine Daten'] as $c => $label)
                        @php $t = $tone($c); @endphp
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full {{ $t['dot'] }}"></span>
                            <span class="font-semibold tabular-nums {{ $byColor[$c] > 0 ? $t['fg'] : 'text-[var(--ui-muted)]' }}">{{ $byColor[$c] }}</span>
                            <span class="text-[var(--ui-muted)]">{{ $label }}</span>
                        </span>
                    @endforeach
                </div>
            </div>

            <div class="space-y-2">
                <div class="h-3 w-full bg-zinc-100 rounded-full overflow-hidden flex">
                    @foreach(['red', 'yellow', 'green', 'gray'] as $c)
                        @php $w = round(($byColor[$c] / $distTotal) * 100, 2); $t = $tone($c); @endphp
                        @if($byColor[$c] > 0)
                            <div class="{{ $t['fill'] }} h-full" style="width: {{ $w }}%" title="{{ $t['label'] }}: {{ $byColor[$c] }}"></div>
                        @endif
                    @endforeach
                </div>
                <div class="flex items-center gap-4 text-[10px] text-[var(--ui-muted)]">
                    <span class="font-semibold uppercase tracking-wider">SLA-Coverage:</span>
                    <span><span class="font-medium text-[var(--ui-secondary)] tabular-nums">{{ $slaCoverage['with'] }}</span> mit SLA</span>
                    <span>·</span>
                    <span><span class="font-medium text-[var(--ui-secondary)] tabular-nums">{{ $slaCoverage['without'] }}</span> ohne SLA</span>
                </div>
            </div>
        </div>

        {{-- Liste --}}
        <div class="flex-1 overflow-y-auto px-4 py-3 bg-[var(--ui-muted-5)]">
            @if($snapshots->isEmpty())
                <div class="rounded-xl border border-[var(--ui-border)] bg-white p-12 text-center">
                    <div class="mx-auto w-12 h-12 rounded-full bg-zinc-100 flex items-center justify-center mb-3">
                        @svg('heroicon-o-funnel', 'w-6 h-6 text-zinc-400')
                    </div>
                    <h3 class="text-sm font-medium text-[var(--ui-secondary)] m-0">Keine Boards passen zu deinen Filtern</h3>
                </div>
            @else
                <ul class="space-y-2">
                    @foreach($snapshots as $s)
                        @php
                            $t = $tone($s->health_color);
                            $axisVal = $s->axis_scores[$s->worst_axis] ?? null;
                            $worstLabel = $axisLabels[$s->worst_axis] ?? null;
                            $delta = $s->delta_health_score;
                            $deltaArrow = $delta === null || $delta === 0 ? null : ($delta > 0 ? '↑' : '↓');
                            $deltaColor = $delta === null ? 'text-zinc-400' : ($delta > 0 ? 'text-emerald-600' : 'text-rose-600');
                        @endphp
                        <li>
                            <a href="{{ route('helpdesk.boards.health', $s->helpdesk_board_id) }}"
                               wire:navigate
                               class="group flex items-stretch rounded-xl border border-[var(--ui-border)] bg-white hover:border-emerald-600/60 hover:shadow-md transition-all overflow-hidden">

                                <div class="flex flex-col items-center justify-center w-20 flex-shrink-0 {{ $t['bg'] }} border-r {{ $t['border'] }}/60 py-3">
                                    <span class="text-2xl font-bold tabular-nums {{ $t['fg'] }} leading-none">{{ $s->health_score ?? '–' }}</span>
                                    <span class="text-[9px] uppercase tracking-wider {{ $t['fg'] }} opacity-70 mt-1">{{ $t['label'] }}</span>
                                </div>

                                <div class="flex-1 min-w-0 px-4 py-3">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-sm font-semibold text-[var(--ui-secondary)] truncate">{{ $s->board?->name }}</span>
                                        @if($s->has_sla)
                                            <span class="text-[9px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 font-medium flex-shrink-0">SLA</span>
                                        @else
                                            <span class="text-[9px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-zinc-100 text-zinc-500 font-medium flex-shrink-0">no SLA</span>
                                        @endif
                                    </div>

                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px]">
                                        @if($worstLabel)
                                            <span class="inline-flex items-center gap-1 {{ $t['fg'] }}">
                                                @svg('heroicon-o-exclamation-triangle', 'w-3 h-3')
                                                <span>Schwach: {{ $worstLabel }}{{ $axisVal !== null ? ' ('.$axisVal.')' : '' }}</span>
                                            </span>
                                        @endif
                                        @if($s->tickets_open > 0)
                                            <span class="text-[var(--ui-muted)]">
                                                <span class="tabular-nums font-medium text-[var(--ui-secondary)]">{{ $s->tickets_open }}</span> offen
                                            </span>
                                        @endif
                                        @if($s->tickets_overdue > 0)
                                            <span class="inline-flex items-center gap-1 text-rose-600">
                                                @svg('heroicon-o-clock', 'w-3 h-3')
                                                <span class="tabular-nums font-medium">{{ $s->tickets_overdue }}</span> überfällig
                                            </span>
                                        @endif
                                        @if($s->tickets_escalated > 0)
                                            <span class="inline-flex items-center gap-1 text-amber-600">
                                                @svg('heroicon-o-fire', 'w-3 h-3')
                                                <span class="tabular-nums font-medium">{{ $s->tickets_escalated }}</span> eskaliert
                                            </span>
                                        @endif
                                        @if($s->confidence_score < 50)
                                            <span class="inline-flex items-center gap-1 text-zinc-500">
                                                @svg('heroicon-o-question-mark-circle', 'w-3 h-3')
                                                <span>Conf {{ $s->confidence_score }}%</span>
                                            </span>
                                        @endif
                                        @if($s->last_movement_at)
                                            <span class="text-[var(--ui-muted)]" title="{{ $s->last_movement_at->format('d.m.Y H:i') }}">
                                                · letzte Bewegung {{ $s->last_movement_at->diffForHumans(short: true) }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex items-center gap-3 px-4 flex-shrink-0">
                                    @if($deltaArrow)
                                        <span class="inline-flex items-center gap-0.5 text-[11px] tabular-nums {{ $deltaColor }} font-medium">
                                            {{ $deltaArrow }}{{ abs($delta) }}
                                        </span>
                                    @endif
                                    @svg('heroicon-o-chevron-right', 'w-4 h-4 text-[var(--ui-muted)] group-hover:text-emerald-600 transition-colors')
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-ui-page>
