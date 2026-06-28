@php
    use Carbon\Carbon;

    $axisLabels = [
        'backlog'    => 'Backlog',
        'sla'        => 'SLA-Einhaltung',
        'escalation' => 'Eskalation',
        'workload'   => 'Workload',
    ];
    $axisExplain = [
        'backlog'    => 'Druck durch offene Tickets, Alter des aeltesten Tickets, unzugewiesene Tickets.',
        'sla'        => 'Anteil Tickets die das Resolution-Time-Limit der Board-SLA reissen.',
        'escalation' => 'Anzahl eskalierter Tickets, kritische Eskalationen, Lifetime-Sum.',
        'workload'   => 'Balance der offenen Tickets pro Agent (max/avg-Ratio).',
    ];
    $axisIcons = [
        'backlog'    => 'heroicon-o-inbox-stack',
        'sla'        => 'heroicon-o-clock',
        'escalation' => 'heroicon-o-fire',
        'workload'   => 'heroicon-o-user-group',
    ];

    $colorTokens = [
        'green'  => ['ring' => 'ring-emerald-200', 'fg' => 'text-emerald-700', 'bg' => 'bg-emerald-50', 'border' => 'border-emerald-200', 'dot' => 'bg-emerald-500', 'fill' => 'bg-emerald-500'],
        'yellow' => ['ring' => 'ring-amber-200',   'fg' => 'text-amber-700',   'bg' => 'bg-amber-50',   'border' => 'border-amber-200',   'dot' => 'bg-amber-500',   'fill' => 'bg-amber-500'],
        'red'    => ['ring' => 'ring-rose-200',    'fg' => 'text-rose-700',    'bg' => 'bg-rose-50',    'border' => 'border-rose-200',    'dot' => 'bg-rose-500',    'fill' => 'bg-rose-500'],
        'gray'   => ['ring' => 'ring-zinc-200',    'fg' => 'text-zinc-500',    'bg' => 'bg-zinc-50',    'border' => 'border-zinc-200',    'dot' => 'bg-zinc-300',    'fill' => 'bg-zinc-300'],
    ];
    $tone = fn ($c) => $colorTokens[$c ?: 'gray'] ?? $colorTokens['gray'];
    $scoreToColor = fn ($v) => $v === null ? 'gray' : ($v >= 70 ? 'green' : ($v >= 40 ? 'yellow' : 'red'));

    $axisScores = $latest?->axis_scores ?? [];
    $trendValues = $trend->pluck('health_score')->filter(fn ($v) => $v !== null)->values()->all();

    $missingLayers = [];
    if ($latest?->confidence_reason && str_starts_with($latest->confidence_reason, 'missing:')) {
        $missingLayers = array_map('trim', explode(',', substr($latest->confidence_reason, strlen('missing:'))));
    }
    $missingLabel = [
        'sla'           => 'Board-SLA',
        'tickets'       => 'Tickets vorhanden',
        'assignees'     => 'User_in_charge gesetzt',
        'service_hours' => 'Service-Hours-Definition',
    ];

    $healthTone = $tone($latest?->health_color);
    $confColor = $latest && $latest->confidence_score >= 75 ? 'green' : ($latest && $latest->confidence_score >= 50 ? 'yellow' : 'red');
    $confTone = $tone($confColor);
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$board->name" icon="heroicon-o-heart" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Helpdesk', 'href' => route('helpdesk.dashboard'), 'icon' => 'home'],
            ['label' => $board->name, 'href' => route('helpdesk.boards.show', $board)],
            ['label' => 'Health'],
        ]">
            <x-ui-button variant="secondary" size="sm" wire:click="refreshSnapshot" title="Snapshot jetzt neu rechnen">
                @svg('heroicon-o-arrow-path', 'w-4 h-4')
                <span>Neu rechnen</span>
            </x-ui-button>
            <a href="{{ route('helpdesk.boards.show', $board) }}"
               wire:navigate
               class="inline-flex items-center gap-1.5 px-3 h-7 rounded-md text-[11px] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors">
                @svg('heroicon-o-arrow-uturn-left', 'w-3.5 h-3.5')
                <span>Zurueck zum Board</span>
            </a>
        </x-ui-page-actionbar>
    </x-slot>

    {{-- ════════ LEFT SIDEBAR: Übersicht ════════ --}}
    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" icon="heroicon-o-information-circle" width="w-72" :minWidth="240" :maxWidth="380" :defaultOpen="true" storeKey="sidebarOpen" side="left">
            <div class="p-4 space-y-5">

                @if($latest)
                    <section class="space-y-1.5">
                        <div class="text-[10px] uppercase tracking-wider text-[var(--ui-muted)] font-semibold">Snapshot</div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-[var(--ui-muted)]">Stand</span>
                            <span class="text-[var(--ui-secondary)] font-medium tabular-nums">{{ $latest->taken_on?->format('d.m.Y') }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-[var(--ui-muted)]">Erstellt</span>
                            <span class="text-[var(--ui-secondary)] tabular-nums">{{ $latest->taken_at?->format('H:i') }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-[var(--ui-muted)]">Trigger</span>
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] uppercase tracking-wider">{{ $latest->trigger }}</span>
                        </div>
                        @if($latest->last_movement_at)
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-[var(--ui-muted)]">Letzte Bewegung</span>
                                <span class="text-[var(--ui-secondary)]" title="{{ $latest->last_movement_at->format('d.m.Y H:i') }}">{{ $latest->last_movement_at->diffForHumans(short: true) }}</span>
                            </div>
                        @endif
                    </section>
                @endif

                <section class="space-y-2">
                    <div class="text-[10px] uppercase tracking-wider text-[var(--ui-muted)] font-semibold">Trend-Zeitraum</div>
                    <div class="inline-flex rounded-md border border-[var(--ui-border)] overflow-hidden w-full">
                        @foreach([7, 30, 90, 180] as $i => $opt)
                            <button type="button" wire:click="setTrendDays({{ $opt }})"
                                    class="flex-1 px-2 h-8 text-[11px] font-medium transition-colors {{ $i > 0 ? 'border-l border-[var(--ui-border)]' : '' }} {{ $trendDays === $opt ? 'bg-emerald-600 text-white' : 'bg-transparent text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                                {{ $opt }}d
                            </button>
                        @endforeach
                    </div>
                </section>

                @if($latest)
                    <section class="space-y-2">
                        <div class="text-[10px] uppercase tracking-wider text-[var(--ui-muted)] font-semibold">Datenpflege</div>
                        <div class="rounded-lg p-3 {{ $confTone['bg'] }} {{ $confTone['border'] }} border">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[11px] {{ $confTone['fg'] }} font-medium uppercase tracking-wider">Confidence</span>
                                <span class="text-lg font-bold tabular-nums {{ $confTone['fg'] }}">{{ $latest->confidence_score }}%</span>
                            </div>
                            <div class="h-1.5 w-full bg-white/60 rounded-full overflow-hidden">
                                <div class="h-full {{ $confTone['fill'] }}" style="width: {{ $latest->confidence_score }}%"></div>
                            </div>
                        </div>
                        @if(!empty($missingLayers))
                            <ul class="space-y-1 pt-1">
                                @foreach($missingLayers as $m)
                                    <li class="flex items-center gap-2 text-[11px] text-[var(--ui-muted)]">
                                        @svg('heroicon-o-x-circle', 'w-3.5 h-3.5 text-rose-400')
                                        <span>{{ $missingLabel[$m] ?? $m }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="flex items-center gap-2 text-[11px] text-emerald-700">
                                @svg('heroicon-o-check-circle', 'w-3.5 h-3.5')
                                <span>Alle Datenebenen gepflegt</span>
                            </div>
                        @endif
                    </section>
                @endif

                <section class="space-y-2">
                    <div class="text-[10px] uppercase tracking-wider text-[var(--ui-muted)] font-semibold">Direkt zu</div>
                    <a href="{{ route('helpdesk.boards.show', $board) }}" wire:navigate
                       class="flex items-center gap-2 px-2.5 py-1.5 rounded text-[12px] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors">
                        @svg('heroicon-o-clipboard-document-list', 'w-4 h-4 text-[var(--ui-muted)]')
                        <span>Board</span>
                    </a>
                </section>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- ════════ RIGHT SIDEBAR: Bewegung ════════ --}}
    <x-slot name="activity">
        <x-ui-page-sidebar title="Bewegung" icon="heroicon-o-bolt" width="w-72" :defaultOpen="true" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-4">
                @if($latest)
                    <section class="space-y-2">
                        <div class="text-[10px] uppercase tracking-wider text-[var(--ui-muted)] font-semibold">Veränderung zum Vortag</div>
                        @php
                            $delta = $latest->delta_health_score;
                            $deltaArrow = $delta === null || $delta === 0 ? null : ($delta > 0 ? '↑' : '↓');
                            $deltaColor = $delta === null ? 'text-zinc-400' : ($delta > 0 ? 'text-emerald-600' : 'text-rose-600');
                        @endphp
                        <div class="flex items-center justify-between text-xs py-1 border-b border-[var(--ui-border)]/30">
                            <span class="text-[var(--ui-muted)]">Health-Score</span>
                            <span class="tabular-nums {{ $deltaColor }} font-medium">
                                {{ $deltaArrow ? $deltaArrow . ' ' . abs($delta) : '–' }}
                            </span>
                        </div>
                    </section>

                    @if($trend->count() > 1)
                        <section class="space-y-2">
                            <div class="text-[10px] uppercase tracking-wider text-[var(--ui-muted)] font-semibold">Letzte Snapshots</div>
                            <ul class="space-y-1">
                                @foreach($trend->take(-7)->reverse() as $point)
                                    @php $pt = $tone($point->health_color); @endphp
                                    <li class="flex items-center justify-between text-xs py-1">
                                        <span class="flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $pt['dot'] }}"></span>
                                            <span class="text-[var(--ui-muted)] tabular-nums text-[11px]">{{ $point->taken_on?->format('d.m.') }}</span>
                                        </span>
                                        <span class="tabular-nums font-medium {{ $pt['fg'] }}">{{ $point->health_score ?? '–' }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    @if($latest->last_movement_at)
                        <section class="rounded-lg bg-[var(--ui-muted-5)] p-3">
                            <div class="text-[10px] uppercase tracking-wider text-[var(--ui-muted)] font-semibold mb-1">Letzte Aktivität</div>
                            <div class="text-[12px] text-[var(--ui-secondary)]">{{ $latest->last_movement_at->diffForHumans() }}</div>
                            <div class="text-[10px] text-[var(--ui-muted)] mt-0.5">{{ $latest->last_movement_at->format('d.m.Y H:i') }}</div>
                        </section>
                    @endif
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- ════════ MAIN BODY ════════ --}}
    <div class="flex-1 min-h-0 overflow-y-auto bg-[var(--ui-muted-5)]">
        <div class="max-w-6xl mx-auto p-6 space-y-5">

        @if(!$latest)
            <div class="rounded-xl border border-[var(--ui-border)] bg-white p-16 text-center">
                <div class="mx-auto w-14 h-14 rounded-full bg-zinc-100 flex items-center justify-center mb-3">
                    @svg('heroicon-o-heart', 'w-7 h-7 text-zinc-400')
                </div>
                <h3 class="text-base font-semibold text-[var(--ui-secondary)] m-0">Noch kein Snapshot vorhanden</h3>
                <p class="text-sm text-[var(--ui-muted)] mt-2 mb-5 max-w-md mx-auto">Snapshots werden nächtlich um 03:15 erstellt. Manueller Auslöser unten.</p>
                <x-ui-button variant="primary" size="md" wire:click="refreshSnapshot">
                    @svg('heroicon-o-arrow-path', 'w-4 h-4')
                    <span>Snapshot jetzt erstellen</span>
                </x-ui-button>
            </div>
        @else

        {{-- HERO --}}
        <section class="rounded-2xl border border-[var(--ui-border)] bg-white overflow-hidden shadow-sm">
            <div class="p-6 {{ $healthTone['bg'] }}/40 border-b border-[var(--ui-border)]">
                <div class="flex items-start gap-6">
                    <div class="flex-shrink-0">
                        <div class="relative w-28 h-28">
                            <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90">
                                <circle cx="50" cy="50" r="44" fill="none" stroke="currentColor" stroke-width="6" class="text-white"/>
                                @if($latest->health_score !== null)
                                    @php $circumference = 2 * pi() * 44; $offset = $circumference * (1 - $latest->health_score / 100); @endphp
                                    <circle cx="50" cy="50" r="44" fill="none" stroke="currentColor" stroke-width="6"
                                            stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $offset }}" stroke-linecap="round"
                                            class="{{ str_replace('bg-', 'text-', $healthTone['fill']) }} transition-all duration-500" />
                                @endif
                            </svg>
                            <div class="absolute inset-0 flex flex-col items-center justify-center">
                                <span class="text-3xl font-bold tabular-nums text-[var(--ui-secondary)] leading-none">{{ $latest->health_score ?? '–' }}</span>
                                <span class="text-[9px] uppercase tracking-wider text-[var(--ui-muted)] mt-1">Health</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full {{ $healthTone['bg'] }} {{ $healthTone['fg'] }} border {{ $healthTone['border'] }} text-[11px] font-semibold uppercase tracking-wider">
                                <span class="w-1.5 h-1.5 rounded-full {{ $healthTone['dot'] }}"></span>
                                {{ $latest->health_color ?? 'unbekannt' }}
                            </span>
                            @if($latest->delta_health_score !== null && $latest->delta_health_score !== 0)
                                @php
                                    $isUp = $latest->delta_health_score > 0;
                                    $deltaChip = $isUp ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200';
                                @endphp
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-[10px] tabular-nums font-medium {{ $deltaChip }}">
                                    {{ $isUp ? '↑' : '↓' }} {{ abs($latest->delta_health_score) }} vs Vortag
                                </span>
                            @endif
                        </div>

                        @if($latest->worst_axis && isset($axisLabels[$latest->worst_axis]))
                            <h2 class="text-lg font-semibold text-[var(--ui-secondary)] m-0 mb-1">
                                Schwächste Achse: <span class="{{ $tone($scoreToColor($axisScores[$latest->worst_axis] ?? 0))['fg'] }}">{{ $axisLabels[$latest->worst_axis] }}</span>
                            </h2>
                            <p class="text-sm text-[var(--ui-muted)] m-0">{{ $axisExplain[$latest->worst_axis] }}</p>
                        @else
                            <h2 class="text-lg font-semibold text-[var(--ui-secondary)] m-0">
                                @if($latest->health_color === 'green')
                                    Alles im grünen Bereich
                                @elseif($latest->health_color === 'gray')
                                    Zu wenig Daten für eine belastbare Aussage
                                @else
                                    Lage solide
                                @endif
                            </h2>
                            <p class="text-sm text-[var(--ui-muted)] m-0 mt-1">
                                @if($latest->health_color === 'gray')
                                    Confidence-Score liegt unter 50% — siehe links welche Datenebenen fehlen.
                                @else
                                    Keine Achse zeigt akuten Handlungsbedarf.
                                @endif
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- 4 Achsen-Karten --}}
            <div class="grid grid-cols-1 md:grid-cols-4 divide-y md:divide-y-0 md:divide-x divide-[var(--ui-border)]">
                @foreach(['backlog', 'sla', 'escalation', 'workload'] as $axisKey)
                    @php
                        $axisVal = $axisScores[$axisKey] ?? null;
                        $axisColor = $scoreToColor($axisVal);
                        $aT = $tone($axisColor);
                        $isWorst = $latest->worst_axis === $axisKey;
                    @endphp
                    <div class="p-5 {{ $isWorst ? $aT['bg'].'/50' : '' }} relative">
                        @if($isWorst)
                            <span class="absolute top-3 right-3 text-[9px] uppercase tracking-wider font-bold {{ $aT['fg'] }}">Schwach</span>
                        @endif
                        <div class="flex items-center gap-2 mb-3">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg {{ $aT['bg'] }} {{ $aT['fg'] }}">
                                @svg($axisIcons[$axisKey], 'w-4 h-4')
                            </span>
                            <span class="text-sm font-semibold text-[var(--ui-secondary)]">{{ $axisLabels[$axisKey] }}</span>
                        </div>
                        <div class="flex items-baseline gap-2 mb-2">
                            <span class="text-3xl font-bold tabular-nums {{ $aT['fg'] }}">{{ $axisVal ?? '–' }}</span>
                            <span class="text-xs text-[var(--ui-muted)]">/100</span>
                        </div>
                        <div class="h-1.5 w-full bg-zinc-100 rounded-full overflow-hidden mb-2">
                            <div class="h-full {{ $aT['fill'] }}" style="width: {{ $axisVal ?? 0 }}%"></div>
                        </div>
                        <p class="text-[11px] text-[var(--ui-muted)] leading-snug m-0">{{ $axisExplain[$axisKey] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- TREND CHART --}}
        <section class="rounded-2xl border border-[var(--ui-border)] bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-semibold text-[var(--ui-secondary)] m-0">Health-Verlauf</h3>
                    <p class="text-[11px] text-[var(--ui-muted)] m-0 mt-0.5">{{ $trendDays }} Tage · {{ Carbon::parse($trendFrom)->format('d.m.') }} – {{ Carbon::parse($trendTo)->format('d.m.Y') }}</p>
                </div>
                @if(count($trendValues) >= 2)
                    @php
                        $vMin = min($trendValues);
                        $vMax = max($trendValues);
                        $first = $trendValues[0];
                        $last = end($trendValues);
                        $totalDelta = $last - $first;
                    @endphp
                    <div class="text-right">
                        <div class="text-xs text-[var(--ui-muted)]">Veränderung gesamt</div>
                        <div class="text-lg font-bold tabular-nums {{ $totalDelta > 0 ? 'text-emerald-600' : ($totalDelta < 0 ? 'text-rose-600' : 'text-zinc-500') }}">
                            {{ $totalDelta > 0 ? '+' : '' }}{{ $totalDelta }}
                        </div>
                    </div>
                @endif
            </div>

            @if(count($trendValues) < 2)
                <div class="text-center py-12 text-xs text-[var(--ui-muted)]">
                    @svg('heroicon-o-chart-bar', 'w-8 h-8 mx-auto mb-2 opacity-40')
                    <div>Noch zu wenig Snapshots für einen Trend ({{ count($trendValues) }} Stützpunkt(e)).</div>
                </div>
            @else
                @php
                    $vRange = max(1, $vMax - $vMin);
                    $w = 800; $h = 140; $padY = 14;
                    $n = count($trendValues);
                    $pointsLine = '';
                    $pointsArea = "0,{$h}";
                    foreach ($trendValues as $i => $v) {
                        $x = ($i / max(1, $n - 1)) * $w;
                        $y = $h - $padY - (($v - $vMin) / $vRange) * ($h - 2 * $padY);
                        $pointsLine .= ($i === 0 ? '' : ' ') . round($x, 1) . ',' . round($y, 1);
                        $pointsArea .= ' ' . round($x, 1) . ',' . round($y, 1);
                    }
                    $pointsArea .= ' ' . $w . ',' . $h;
                    $lastX = ($n - 1) / max(1, $n - 1) * $w;
                    $lastY = $h - $padY - (($last - $vMin) / $vRange) * ($h - 2 * $padY);
                @endphp
                <div class="relative">
                    <svg viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none" class="w-full h-32">
                        <defs>
                            <linearGradient id="hbTrendFill" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="currentColor" stop-opacity="0.15" class="text-emerald-500"/>
                                <stop offset="100%" stop-color="currentColor" stop-opacity="0" class="text-emerald-500"/>
                            </linearGradient>
                        </defs>
                        <line x1="0" y1="{{ $padY }}" x2="{{ $w }}" y2="{{ $padY }}" stroke="currentColor" stroke-width="0.5" class="text-zinc-200"/>
                        <line x1="0" y1="{{ $h/2 }}" x2="{{ $w }}" y2="{{ $h/2 }}" stroke="currentColor" stroke-width="0.5" class="text-zinc-200" stroke-dasharray="2,3"/>
                        <line x1="0" y1="{{ $h - $padY }}" x2="{{ $w }}" y2="{{ $h - $padY }}" stroke="currentColor" stroke-width="0.5" class="text-zinc-200"/>
                        <polygon points="{{ $pointsArea }}" fill="url(#hbTrendFill)" />
                        <polyline points="{{ $pointsLine }}" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" class="text-emerald-500" />
                        <circle cx="{{ $lastX }}" cy="{{ $lastY }}" r="4" fill="white" stroke="currentColor" stroke-width="2" class="text-emerald-500"/>
                    </svg>
                </div>
                <div class="flex items-center justify-between text-[10px] text-[var(--ui-muted)] mt-2">
                    <span class="tabular-nums">{{ Carbon::parse($trend->first()?->taken_on)->format('d.m.') }}</span>
                    <span class="tabular-nums">min {{ $vMin }} · max {{ $vMax }}</span>
                    <span class="tabular-nums">{{ Carbon::parse($trend->last()?->taken_on)->format('d.m.') }}</span>
                </div>
            @endif
        </section>

        {{-- KEY NUMBERS --}}
        <section class="grid grid-cols-2 md:grid-cols-5 gap-3">
            @php
                $kn = [
                    ['label' => 'Tickets offen', 'value' => $latest->tickets_open, 'sub' => 'von ' . $latest->tickets_total, 'meta' => $latest->tickets_overdue > 0 ? $latest->tickets_overdue.' überfällig' : null, 'metaColor' => 'text-rose-600', 'icon' => 'heroicon-o-clipboard-document-list'],
                    ['label' => 'Eskaliert', 'value' => $latest->tickets_escalated, 'sub' => null, 'meta' => $latest->tickets_critical > 0 ? $latest->tickets_critical.' kritisch' : null, 'metaColor' => 'text-rose-600', 'icon' => 'heroicon-o-fire'],
                    ['label' => 'SLA-Verletzungen', 'value' => $latest->tickets_breaching_resolution, 'sub' => $latest->has_sla ? 'aktiv' : 'keine SLA', 'meta' => null, 'metaColor' => '', 'icon' => 'heroicon-o-clock'],
                    ['label' => 'Story Points', 'value' => $latest->story_points_done.' / '.$latest->story_points_total, 'sub' => $latest->story_points_total > 0 ? round(($latest->story_points_done/$latest->story_points_total)*100).'%' : null, 'meta' => null, 'metaColor' => '', 'icon' => 'heroicon-o-puzzle-piece'],
                    ['label' => 'Agents', 'value' => $latest->active_users_count, 'sub' => $latest->unassigned_tickets > 0 ? $latest->unassigned_tickets.' unassigned' : null, 'meta' => null, 'metaColor' => 'text-amber-600', 'icon' => 'heroicon-o-user-group'],
                ];
            @endphp
            @foreach($kn as $k)
                <div class="rounded-xl border border-[var(--ui-border)] bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between mb-2">
                        <span class="text-[10px] uppercase tracking-wider text-[var(--ui-muted)] font-semibold">{{ $k['label'] }}</span>
                        @svg($k['icon'], 'w-3.5 h-3.5 text-[var(--ui-muted)] opacity-50')
                    </div>
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-2xl font-bold tabular-nums text-[var(--ui-secondary)]">{{ $k['value'] }}</span>
                        @if($k['sub'])<span class="text-xs text-[var(--ui-muted)]">{{ $k['sub'] }}</span>@endif
                    </div>
                    @if($k['meta'])
                        <div class="text-[11px] {{ $k['metaColor'] }} mt-1">{{ $k['meta'] }}</div>
                    @endif
                </div>
            @endforeach
        </section>

        {{-- TOP-TICKETS + WORKLOAD --}}
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="rounded-2xl border border-[var(--ui-border)] bg-white shadow-sm">
                <div class="px-5 py-3 border-b border-[var(--ui-border)] flex items-center gap-2">
                    @svg('heroicon-o-fire', 'w-4 h-4 text-rose-500')
                    <h3 class="text-sm font-semibold text-[var(--ui-secondary)] m-0">Top-5 Tickets</h3>
                    <span class="ml-auto text-[11px] text-[var(--ui-muted)]">Eskalation → Overdue → Alter</span>
                </div>
                @if($latest->topTickets->isEmpty())
                    <div class="p-8 text-center text-xs text-[var(--ui-muted)]">
                        @svg('heroicon-o-check-circle', 'w-8 h-8 mx-auto mb-2 text-emerald-400 opacity-60')
                        <div>Keine offenen Tickets.</div>
                    </div>
                @else
                    <ul class="divide-y divide-[var(--ui-border)]/40">
                        @foreach($latest->topTickets as $t)
                            <li class="px-5 py-3 hover:bg-[var(--ui-muted-5)]/50 transition-colors">
                                <div class="flex items-start gap-3">
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-[var(--ui-muted-5)] text-[10px] font-bold tabular-nums text-[var(--ui-secondary)] flex-shrink-0">{{ $t->rank }}</span>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm text-[var(--ui-secondary)] truncate">{{ $t->ticket_title }}</div>
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px] mt-1">
                                            @if($t->escalation_level && $t->escalation_level !== 'none')
                                                <span class="inline-flex items-center gap-1 text-rose-600 font-medium">
                                                    @svg('heroicon-o-fire', 'w-3 h-3')
                                                    <span>{{ strtoupper($t->escalation_level) }}</span>
                                                </span>
                                            @endif
                                            @if($t->due_date)
                                                <span class="inline-flex items-center gap-1 {{ $t->is_overdue ? 'text-rose-600 font-medium' : 'text-[var(--ui-muted)]' }}">
                                                    @svg($t->is_overdue ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-calendar', 'w-3 h-3')
                                                    <span>{{ $t->is_overdue ? 'überfällig ' : 'fällig ' }}{{ $t->due_date->format('d.m.Y') }}</span>
                                                </span>
                                            @endif
                                            @if($t->user_in_charge_name)
                                                <span class="text-[var(--ui-muted)]">· {{ $t->user_in_charge_name }}</span>
                                            @endif
                                            @if($t->story_points)
                                                <span class="text-[var(--ui-muted)]">· {{ strtoupper($t->story_points) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="rounded-2xl border border-[var(--ui-border)] bg-white shadow-sm">
                <div class="px-5 py-3 border-b border-[var(--ui-border)] flex items-center gap-2">
                    @svg('heroicon-o-user-group', 'w-4 h-4 text-indigo-500')
                    <h3 class="text-sm font-semibold text-[var(--ui-secondary)] m-0">Workload</h3>
                    <span class="ml-auto text-[11px] text-[var(--ui-muted)] tabular-nums">{{ $latest->people->count() }} Agent{{ $latest->people->count() === 1 ? '' : 's' }}</span>
                </div>
                @if($latest->people->isEmpty())
                    <div class="p-8 text-center text-xs text-[var(--ui-muted)]">
                        @svg('heroicon-o-user', 'w-8 h-8 mx-auto mb-2 opacity-40')
                        <div>Niemand hat aktuell offene Tickets.</div>
                    </div>
                @else
                    @php $maxOpen = max(1, $latest->people->max('open_tickets')); @endphp
                    <ul class="divide-y divide-[var(--ui-border)]/40">
                        @foreach($latest->people as $p)
                            <li class="px-5 py-3">
                                <div class="flex items-center justify-between text-sm mb-2">
                                    <span class="text-[var(--ui-secondary)] font-medium truncate">{{ $p->user_name }}</span>
                                    <div class="flex items-center gap-2 text-[11px] tabular-nums">
                                        <span class="text-[var(--ui-muted)]">{{ $p->open_tickets }} offen</span>
                                        @if($p->overdue_tickets > 0)
                                            <span class="inline-flex items-center gap-0.5 text-rose-600 font-medium">
                                                @svg('heroicon-o-exclamation-triangle', 'w-3 h-3')
                                                {{ $p->overdue_tickets }}
                                            </span>
                                        @endif
                                        @if($p->escalated_tickets > 0)
                                            <span class="inline-flex items-center gap-0.5 text-amber-600 font-medium">
                                                @svg('heroicon-o-fire', 'w-3 h-3')
                                                {{ $p->escalated_tickets }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <div class="h-1.5 w-full bg-zinc-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-indigo-500" style="width: {{ round(($p->open_tickets / $maxOpen) * 100) }}%"></div>
                                </div>
                                @if($p->sp_open > 0)
                                    <div class="text-[10px] text-[var(--ui-muted)] mt-1 tabular-nums">{{ $p->sp_open }} SP offen · {{ $p->sp_done }} SP erledigt</div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        {{-- SLOTS --}}
        @if($latest->slots->isNotEmpty())
            <section class="rounded-2xl border border-[var(--ui-border)] bg-white p-5 shadow-sm">
                <div class="flex items-center gap-2 mb-4">
                    @svg('heroicon-o-view-columns', 'w-4 h-4 text-[var(--ui-muted)]')
                    <h3 class="text-sm font-semibold text-[var(--ui-secondary)] m-0">Slot-Verteilung</h3>
                </div>
                @php $maxTotal = max(1, $latest->slots->max('total_tickets')); @endphp
                <ul class="space-y-3">
                    @foreach($latest->slots as $slot)
                        <li>
                            <div class="flex items-center justify-between text-sm mb-1.5">
                                <span class="text-[var(--ui-secondary)] truncate font-medium">{{ $slot->slot_name }}</span>
                                <span class="tabular-nums text-xs text-[var(--ui-muted)]">
                                    <span class="text-emerald-600 font-medium">{{ $slot->done_tickets }}</span> done · <span class="text-[var(--ui-secondary)] font-medium">{{ $slot->open_tickets }}</span> offen
                                </span>
                            </div>
                            <div class="h-2 w-full bg-zinc-100 rounded-full overflow-hidden flex">
                                <div class="h-full bg-emerald-500" style="width: {{ round(($slot->done_tickets / $maxTotal) * 100) }}%"></div>
                                <div class="h-full bg-indigo-500/70" style="width: {{ round(($slot->open_tickets / $maxTotal) * 100) }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @endif
        </div>
    </div>
</x-ui-page>
