<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Meine Tickets" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                {{-- Aktionen --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Aktionen</h3>
                    <div class="space-y-2">
                        <x-ui-button
                            variant="secondary-outline"
                            size="sm"
                            wire:click="createTicket(null)"
                            class="w-full"
                        >
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-plus-circle', 'w-4 h-4')
                                Neues Ticket
                            </span>
                        </x-ui-button>
                        <x-ui-button
                            variant="secondary-outline"
                            size="sm"
                            wire:click="createTicketGroup"
                            class="w-full"
                        >
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-square-2-stack', 'w-4 h-4')
                                Spalte hinzufügen
                            </span>
                        </x-ui-button>
                    </div>
                </div>

                {{-- Monatliche Performance --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Monatliche Performance</h3>
                    <div class="p-4 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm text-[var(--ui-muted)]">Erstellt</span>
                            <span class="text-sm font-semibold text-[var(--ui-warning)]">{{ $createdPoints }} SP</span>
                        </div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm text-[var(--ui-muted)]">Erledigt</span>
                            <span class="text-sm font-semibold text-[var(--ui-success)]">{{ $donePoints }} SP</span>
                        </div>
                        <div class="border-t border-[var(--ui-border)]/40 pt-2 mt-2">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-[var(--ui-secondary)]">Performance-Score</span>
                                @if($monthlyPerformanceScore !== null)
                                    <span class="text-sm font-bold {{ $monthlyPerformanceScore >= 1 ? 'text-[var(--ui-success)]' : 'text-[var(--ui-warning)]' }}">
                                        {{ number_format($monthlyPerformanceScore * 100, 0) }}%
                                    </span>
                                @else
                                    <span class="text-sm text-[var(--ui-muted)]">-</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Statistiken --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Statistiken</h3>
                    <div class="grid grid-cols-2 gap-2">
                        <x-ui-dashboard-tile title="Story Points (offen)" :count="$groups->filter(fn($g) => !($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks)->sum(fn($t) => $t->story_points?->points() ?? 0)" icon="chart-bar" variant="warning" size="sm" />
                        <x-ui-dashboard-tile title="Story Points (erledigt)" :count="($groups->first(fn($g)=>($g->isDoneGroup ?? false))?->tasks ?? collect())->sum(fn($t) => $t->story_points?->points() ?? 0)" icon="check-circle" variant="success" size="sm" />
                        <x-ui-dashboard-tile title="Offen" :count="$groups->filter(fn($g) => !($g->isDoneGroup ?? false))->sum(fn($g) => $g->tasks->count())" icon="clock" variant="warning" size="sm" />
                        <x-ui-dashboard-tile title="Gesamt" :count="$groups->flatMap(fn($g) => $g->tasks)->count()" icon="document-text" variant="secondary" size="sm" />
                        <x-ui-dashboard-tile title="Erledigt" :count="($groups->first(fn($g)=>($g->isDoneGroup ?? false))?->tasks ?? collect())->count()" icon="check-circle" variant="success" size="sm" />
                        <x-ui-dashboard-tile title="Ohne Fälligkeit" :count="$groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => !$t->due_date)->count()" icon="calendar" variant="neutral" size="sm" />
                        <x-ui-dashboard-tile title="Überfällig" :count="$groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => $t->due_date && $t->due_date->isPast() && !$t->is_done)->count()" icon="exclamation-circle" variant="danger" size="sm" />
                    </div>
                </div>

                {{-- Erledigte Tickets --}}
                @php $completedTickets = $groups->filter(fn($g) => $g->isDoneGroup ?? false)->flatMap(fn($g) => $g->tasks); @endphp
                @if($completedTickets->count() > 0)
                    <div>
                        <h4 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Erledigte Tickets ({{ $completedTickets->count() }})</h4>
                        <div class="space-y-1 max-h-60 overflow-y-auto">
                            @foreach($completedTickets->take(10) as $ticket)
                                <a href="{{ route('helpdesk.tickets.show', $ticket) }}" class="block p-2 rounded text-sm border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)] hover:bg-[var(--ui-primary-5)] transition" wire:navigate>
                                    <div class="flex items-center gap-2">
                                        <x-heroicon-o-check-circle class="w-4 h-4 text-[var(--ui-success)]"/>
                                        <span class="truncate">{{ $ticket->title }}</span>
                                    </div>
                                </a>
                            @endforeach
                            @if($completedTickets->count() > 10)
                                <div class="text-xs text-[var(--ui-muted)] italic text-center">+{{ $completedTickets->count() - 10 }} weitere</div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="text-sm text-[var(--ui-muted)] italic">Noch keine erledigten Tickets</div>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-4">
                <div class="text-sm text-[var(--ui-muted)]">Letzte Aktivitäten</div>
                <div class="space-y-3 text-sm">
                    <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                        <div class="font-medium text-[var(--ui-secondary)] truncate">Meine Tickets geladen</div>
                        <div class="text-[var(--ui-muted)]">gerade eben</div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-kanban-container sortable="updateTicketGroupOrder" sortable-group="updateTicketOrder" wire:key="my-tickets-kanban-container">

        {{-- INBOX (nicht sortierbar als Gruppe) --}}
        @php $inbox = $groups->first(fn($g) => ($g->isInbox ?? false)); @endphp
        @if($inbox)
            <x-ui-kanban-column :title="($inbox->label ?? 'Posteingang')" :sortable-id="null" :scrollable="true" :muted="true">
                <x-slot name="headerActions">
                    <button
                        wire:click="createTicket(null)"
                        class="text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
                        title="Neues Ticket in INBOX erstellen">
                        @svg('heroicon-o-plus-circle', 'w-4 h-4')
                    </button>
                </x-slot>
                @foreach(($inbox->tasks ?? []) as $ticket)
                    @include('helpdesk::livewire.ticket-preview-card', ['ticket' => $ticket])
                @endforeach
            </x-ui-kanban-column>
        @endif

        {{-- Mittlere Spalten (sortierbar) --}}
        @foreach($groups->filter(fn ($g) => !($g->isDoneGroup ?? false) && !($g->isInbox ?? false)) as $column)
            <x-ui-kanban-column :title="($column->label ?? $column->name ?? 'Spalte')" :sortable-id="$column->id" :scrollable="true">
                <x-slot name="headerActions">
                    <button
                        wire:click="createTicket('{{ $column->id }}')"
                        class="text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
                        title="Neues Ticket in dieser Gruppe erstellen">
                        @svg('heroicon-o-plus-circle', 'w-4 h-4')
                    </button>
                    <button
                        @click="$dispatch('open-modal-ticket-group-settings', { ticketGroupId: {{ $column->id }} })"
                        class="text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
                        title="Gruppen-Einstellungen"
                    >
                        @svg('heroicon-o-cog-6-tooth', 'w-4 h-4')
                    </button>
                </x-slot>
                @foreach(($column->tasks ?? []) as $ticket)
                    @include('helpdesk::livewire.ticket-preview-card', ['ticket' => $ticket])
                @endforeach
            </x-ui-kanban-column>
        @endforeach

        {{-- Erledigt (nicht sortierbar) --}}
        @php $done = $groups->first(fn($g) => ($g->isDoneGroup ?? false)); @endphp
        @if($done)
            <x-ui-kanban-column :title="($done->label ?? 'Erledigt')" :sortable-id="null" :scrollable="true" :muted="true">
                @foreach(($done->tasks ?? []) as $ticket)
                    @include('helpdesk::livewire.ticket-preview-card', ['ticket' => $ticket])
                @endforeach
            </x-ui-kanban-column>
        @endif

    </x-ui-kanban-container>

    <livewire:helpdesk.ticket-group-settings-modal/>

</x-ui-page>
