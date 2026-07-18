<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="{{ $helpdeskBoard->name }}" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Helpdesk', 'href' => route('helpdesk.dashboard'), 'icon' => 'lifebuoy'],
            ['label' => $helpdeskBoard->name],
        ]">
            <x-slot name="left">
                {{-- Health-Pille — Klick-Einstieg zur Board-Health-Detail-Sicht --}}
                @if($latestSnapshot)
                    @php
                        $hc = $latestSnapshot->health_color ?? 'gray';
                        $hs = $latestSnapshot->health_score;
                        $healthTones = [
                            'green'  => ['border' => 'border-emerald-300', 'bg' => 'bg-emerald-50',  'hover' => 'hover:bg-emerald-100', 'fg' => 'text-emerald-700', 'dot' => 'bg-emerald-500', 'label' => 'Stabil'],
                            'yellow' => ['border' => 'border-amber-300',   'bg' => 'bg-amber-50',    'hover' => 'hover:bg-amber-100',   'fg' => 'text-amber-700',   'dot' => 'bg-amber-500',   'label' => 'Achtung'],
                            'red'    => ['border' => 'border-rose-300',    'bg' => 'bg-rose-50',     'hover' => 'hover:bg-rose-100',    'fg' => 'text-rose-700',    'dot' => 'bg-rose-500',    'label' => 'Brennt'],
                            'gray'   => ['border' => 'border-zinc-300',    'bg' => 'bg-zinc-50',     'hover' => 'hover:bg-zinc-100',    'fg' => 'text-zinc-600',    'dot' => 'bg-zinc-400',    'label' => 'Keine Daten'],
                        ];
                        $t = $healthTones[$hc] ?? $healthTones['gray'];
                        $delta = $latestSnapshot->delta_health_score;
                        $trendArrow = $delta === null || $delta === 0 ? null : ($delta > 0 ? '↑' : '↓');
                        $worstAxisLabel = match($latestSnapshot->worst_axis) {
                            'backlog' => 'Backlog', 'sla' => 'SLA', 'escalation' => 'Eskalation', 'workload' => 'Workload', default => null,
                        };
                    @endphp
                    <a href="{{ route('helpdesk.boards.health', $helpdeskBoard) }}"
                       wire:navigate
                       title="Snapshot {{ optional($latestSnapshot->taken_on)->format('d.m.Y') }} · Health {{ $hs ?? '–' }} ({{ $hc }}) · Confidence {{ $latestSnapshot->confidence_score }}%"
                       class="group inline-flex items-stretch h-9 rounded-lg border {{ $t['border'] }} {{ $t['bg'] }} {{ $t['hover'] }} text-[12px] {{ $t['fg'] }} font-medium overflow-hidden shadow-sm transition-all hover:shadow-md">
                        <span class="flex items-center gap-2 px-3 border-r {{ $t['border'] }}/70">
                            <span class="w-2 h-2 rounded-full {{ $t['dot'] }} animate-pulse"></span>
                            <span class="text-base font-bold tabular-nums leading-none">{{ $hs ?? '–' }}</span>
                        </span>
                        <span class="flex items-center gap-1.5 px-3">
                            <span class="text-[10px] uppercase tracking-wider opacity-70">{{ $worstAxisLabel ?? $t['label'] }}</span>
                            @if($trendArrow)
                                <span class="text-[11px] tabular-nums opacity-80">{{ $trendArrow }}{{ abs($delta) }}</span>
                            @endif
                            @svg('heroicon-o-arrow-top-right-on-square', 'w-3 h-3 opacity-50 group-hover:opacity-100 transition-opacity')
                        </span>
                    </a>
                @else
                    <a href="{{ route('helpdesk.boards.health', $helpdeskBoard) }}"
                       wire:navigate
                       title="Noch kein Snapshot vorhanden"
                       class="inline-flex items-center gap-1.5 px-3 h-9 rounded-lg border border-dashed border-gray-300 bg-white hover:bg-gray-50 text-[12px] text-gray-500 hover:text-gray-700 transition-colors">
                        @svg('heroicon-o-heart', 'w-4 h-4')
                        <span class="font-medium">Health</span>
                        @svg('heroicon-o-arrow-right', 'w-3 h-3 opacity-50')
                    </a>
                @endif

                <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-colors text-[13px]" x-data @click="$dispatch('open-modal-board-settings', { boardId: {{ $helpdeskBoard->id }} })">
                    @svg('heroicon-o-cog-6-tooth', 'w-4 h-4')
                    <span>Einstellungen</span>
                </button>
            </x-slot>
            @can('update', $helpdeskBoard)
                <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-colors text-[13px]" wire:click="createBoardSlot">
                    @svg('heroicon-o-square-2-stack', 'w-4 h-4')
                    <span>Spalte</span>
                </button>
            @endcan

            {{-- CalDAV: dieses Board als eigene Liste in Apple Erinnerungen zeigen (nur bei aktivem Abo) --}}
            @if($this->hasHelpdeskCaldavSubscription())
                <button type="button" wire:click="toggleCaldavExposure"
                    title="Dieses Board als eigene Liste in meiner Aufgaben-App (Erinnerungen) zeigen"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md {{ $this->caldavExposed() ? 'text-gray-700 bg-gray-100' : 'text-gray-400 hover:text-gray-700 hover:bg-gray-100' }} transition-colors text-[13px]">
                    @svg($this->caldavExposed() ? 'heroicon-s-bell-alert' : 'heroicon-o-bell', 'w-4 h-4')
                    <span>{{ $this->caldavExposed() ? 'In App ✓' : 'In App' }}</span>
                </button>
            @endif
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Board-Übersicht" width="w-80" :defaultOpen="true" side="left">
            <div class="p-4 space-y-5">
                {{-- Board-Info --}}
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 mb-2">{{ $helpdeskBoard->name }}</h3>
                    <div class="text-[13px] text-gray-500">{{ $helpdeskBoard->description ?? 'Keine Beschreibung' }}</div>
                </div>

                {{-- Ansicht --}}
                <div>
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-3">Ansicht</div>
                    <div class="space-y-2">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                wire:click="toggleShowDone"
                                @if($showDone) checked @endif
                                class="w-4 h-4 rounded border-gray-300 text-[#049b5c] focus:ring-[#049b5c] focus:ring-offset-0"
                            >
                            <span class="text-[13px] text-gray-700">Erledigte Tickets anzeigen</span>
                        </label>
                    </div>
                </div>

                {{-- Statistiken --}}
                <div>
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-3">Statistiken</div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="bg-white rounded-lg border border-gray-200 p-2.5">
                            <div class="text-[10px] font-medium text-gray-400 uppercase tracking-wide mb-0.5">SP (offen)</div>
                            <div class="text-base font-bold text-amber-600 tabular-nums">{{ $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks)->sum(fn($t) => $t->story_points?->points() ?? 0) }}</div>
                        </div>
                        <div class="bg-white rounded-lg border border-gray-200 p-2.5">
                            <div class="text-[10px] font-medium text-gray-400 uppercase tracking-wide mb-0.5">SP (erledigt)</div>
                            <div class="text-base font-bold text-green-600 tabular-nums">{{ $groups->filter(fn($g) => $g->isDoneGroup ?? false)->flatMap(fn($g) => $g->tasks)->sum(fn($t) => $t->story_points?->points() ?? 0) }}</div>
                        </div>
                        <div class="bg-white rounded-lg border border-gray-200 p-2.5">
                            <div class="text-[10px] font-medium text-gray-400 uppercase tracking-wide mb-0.5">Offen</div>
                            <div class="text-base font-bold text-amber-600 tabular-nums">{{ $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->sum(fn($g) => $g->tasks->count()) }}</div>
                        </div>
                        <div class="bg-white rounded-lg border border-gray-200 p-2.5">
                            <div class="text-[10px] font-medium text-gray-400 uppercase tracking-wide mb-0.5">Gesamt</div>
                            <div class="text-base font-bold text-gray-900 tabular-nums">{{ $groups->flatMap(fn($g) => $g->tasks)->count() }}</div>
                        </div>
                        <div class="bg-white rounded-lg border border-gray-200 p-2.5">
                            <div class="text-[10px] font-medium text-gray-400 uppercase tracking-wide mb-0.5">Erledigt</div>
                            <div class="text-base font-bold text-green-600 tabular-nums">{{ $groups->filter(fn($g) => $g->isDoneGroup ?? false)->sum(fn($g) => $g->tasks->count()) }}</div>
                        </div>
                        <div class="bg-white rounded-lg border border-gray-200 p-2.5">
                            <div class="text-[10px] font-medium text-gray-400 uppercase tracking-wide mb-0.5">Ohne Fälligk.</div>
                            <div class="text-base font-bold text-gray-900 tabular-nums">{{ $groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => !$t->due_date)->count() }}</div>
                        </div>
                        <div class="bg-white rounded-lg border border-gray-200 p-2.5">
                            <div class="text-[10px] font-medium text-gray-400 uppercase tracking-wide mb-0.5">Frösche</div>
                            <div class="text-base font-bold text-red-600 tabular-nums">0</div>
                        </div>
                        <div class="bg-white rounded-lg border border-gray-200 p-2.5">
                            <div class="text-[10px] font-medium text-gray-400 uppercase tracking-wide mb-0.5">Überfällig</div>
                            <div class="text-base font-bold text-red-600 tabular-nums">{{ $groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => $t->due_date && $t->due_date->isPast() && !$t->is_done)->count() }}</div>
                        </div>
                    </div>
                </div>

                {{-- Erledigte Tickets --}}
                @php $completedTickets = $groups->filter(fn($g) => $g->isDoneGroup ?? false)->flatMap(fn($g) => $g->tasks); @endphp
                @if($completedTickets->count() > 0)
                    <div>
                        <h4 class="text-[13px] font-medium text-gray-900 mb-3">Erledigte Tickets ({{ $completedTickets->count() }})</h4>
                        <div class="space-y-1 max-h-60 overflow-y-auto">
                            @foreach($completedTickets->take(10) as $ticket)
                                <a href="{{ route('helpdesk.tickets.show', $ticket) }}" class="block p-2 rounded-lg text-[13px] border border-gray-200 bg-gray-50 hover:bg-emerald-50/50 transition" wire:navigate>
                                    <div class="flex items-center gap-2">
                                        <x-heroicon-o-check-circle class="w-4 h-4 text-green-500"/>
                                        <span class="truncate">{{ $ticket->title }}</span>
                                    </div>
                                </a>
                            @endforeach
                            @if($completedTickets->count() > 10)
                                <div class="text-xs text-gray-400 italic text-center">+{{ $completedTickets->count() - 10 }} weitere</div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="text-[13px] text-gray-400 italic">Noch keine erledigten Tickets</div>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-4">
                <div class="text-[13px] text-gray-400">Letzte Aktivitäten</div>
                <div class="space-y-3 text-[13px]">
                    @foreach(($activities ?? []) as $activity)
                        <div class="p-2 rounded-lg border border-gray-200 bg-gray-50">
                            <div class="font-medium text-gray-900 truncate">{{ $activity['title'] ?? 'Aktivität' }}</div>
                            <div class="text-gray-400">{{ $activity['time'] ?? '' }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <!-- Kanban-Board (Planner-kompatibel) -->
    <x-ui-kanban-container class="h-full" sortable="updateTicketGroupOrder" sortable-group="updateTicketOrder">
        {{-- Mittlere Spalten (scrollable) --}}
        @foreach($groups->filter(fn ($g) => !($g->isDoneGroup ?? false)) as $column)
            @php $isBacklog = $column->isBacklog ?? false; @endphp
            <x-ui-kanban-column :sortable-id="$column->id" :scrollable="true">
                <x-slot name="title">
                    <span class="flex items-center gap-1.5">
                        {{ $column->label ?? $column->name ?? 'Spalte' }}
                        @if($isBacklog && ($unreadCount ?? 0) > 0)
                            <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold leading-none text-white bg-red-500 rounded-full">{{ $unreadCount }}</span>
                        @endif
                    </span>
                </x-slot>
                <x-slot name="headerActions">
                    @can('update', $helpdeskBoard)
                        @if(!$isBacklog)
                            <button
                                wire:click="createTicket('{{ $column->id }}')"
                                class="text-gray-400 hover:text-gray-700 transition-colors"
                                title="Neues Ticket">
                                @svg('heroicon-o-plus-circle', 'w-4 h-4')
                            </button>
                            <button
                                @click="$dispatch('open-modal-board-slot-settings', { boardSlotId: {{ $column->id }} })"
                                class="text-gray-400 hover:text-gray-700 transition-colors"
                                title="Einstellungen">
                                @svg('heroicon-o-cog-6-tooth', 'w-4 h-4')
                            </button>
                        @else
                            <button
                                wire:click="createTicket(null)"
                                class="text-gray-400 hover:text-gray-700 transition-colors"
                                title="Ticket in Backlog erstellen">
                                @svg('heroicon-o-plus-circle', 'w-4 h-4')
                            </button>
                        @endif
                    @endcan
                </x-slot>

                @foreach($column->tasks as $ticket)
                    @include('helpdesk::livewire.ticket-preview-card', ['ticket' => $ticket])
                @endforeach
            </x-ui-kanban-column>
        @endforeach

        {{-- ERLEDIGT Spalte (muted, nicht sortierbar als Gruppe) - nur anzeigen wenn $showDone aktiv --}}
        @if($showDone)
            @php $doneGroup = $groups->first(fn($g) => ($g->isDoneGroup ?? false)); @endphp
            @if($doneGroup)
                <x-ui-kanban-column :title="($doneGroup->label ?? 'Erledigt')" :sortable-id="null" :scrollable="true" :muted="true">
                    @foreach($doneGroup->tasks as $ticket)
                        @include('helpdesk::livewire.ticket-preview-card', ['ticket' => $ticket])
                    @endforeach
                </x-ui-kanban-column>
            @endif
        @endif
    </x-ui-kanban-container>

    <livewire:helpdesk.board-settings-modal wire:key="helpdesk-board-settings-modal"/>
    <livewire:helpdesk.board-slot-settings-modal wire:key="helpdesk-board-slot-settings-modal"/>

</x-ui-page>
