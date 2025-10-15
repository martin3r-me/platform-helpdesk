<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Meine Tickets" icon="heroicon-o-ticket">
            <div class="flex items-center gap-2">
                <x-ui-button variant="success" size="sm" wire:click="createTicket()">+ Neues Ticket</x-ui-button>
                <x-ui-button variant="primary-outline" size="sm" wire:click="createTicketGroup">+ Neue Spalte</x-ui-button>
            </div>
        </x-ui-page-navbar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-4">
                <div class="text-sm text-[var(--ui-muted)]">Letzte Aktivitäten</div>
                <div class="space-y-3 text-sm">
                    @foreach(($activities ?? []) as $activity)
                        <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                            <div class="font-medium text-[var(--ui-secondary)] truncate">{{ $activity['title'] ?? 'Aktivität' }}</div>
                            <div class="text-[var(--ui-muted)]">{{ $activity['time'] ?? '' }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" defaultOpen="true" storeKey="sidebarOpen" side="left">
            <div class="p-4">
                <h3 class="text-lg font-semibold mb-2">Meine Tickets</h3>
                <div class="text-sm text-gray-600 mb-4">Persönliche Tickets und zuständige Board-Tickets</div>

                <div class="grid grid-cols-2 gap-2 mb-4">
                    <x-ui-dashboard-tile title="Story Points (offen)" :count="$groups->filter(fn($g) => !($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks)->sum(fn($t) => $t->story_points?->value() ?? 0)" icon="chart-bar" variant="warning" size="sm" />
                    <x-ui-dashboard-tile title="Story Points (erledigt)" :count="$groups->filter(fn($g) => $g->isDoneGroup ?? false)->flatMap(fn($g) => $g->tasks)->sum(fn($t) => $t->story_points?->value() ?? 0)" icon="check-circle" variant="success" size="sm" />
                    <x-ui-dashboard-tile title="Offen" :count="$groups->filter(fn($g) => !($g->isDoneGroup ?? false))->sum(fn($g) => $g->tasks->count())" icon="clock" variant="warning" size="sm" />
                    <x-ui-dashboard-tile title="Gesamt" :count="$groups->flatMap(fn($g) => $g->tasks)->count()" icon="document-text" variant="secondary" size="sm" />
                    <x-ui-dashboard-tile title="Erledigt" :count="$groups->filter(fn($g) => $g->isDoneGroup ?? false)->sum(fn($g) => $g->tasks->count())" icon="check-circle" variant="success" size="sm" />
                    <x-ui-dashboard-tile title="Ohne Fälligkeit" :count="$groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => !$t->due_date)->count()" icon="calendar" variant="neutral" size="sm" />
                    <x-ui-dashboard-tile title="Frösche" :count="0" icon="exclamation-triangle" variant="danger" size="sm" />
                    <x-ui-dashboard-tile title="Überfällig" :count="$groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => $t->due_date && $t->due_date->isPast() && !$t->is_done)->count()" icon="exclamation-circle" variant="danger" size="sm" />
                </div>

                <div class="d-flex flex-col gap-2 mb-4">
                    <x-ui-button variant="success-outline" size="sm" wire:click="createTicket()">+ Neues Ticket</x-ui-button>
                    <x-ui-button variant="primary-outline" size="sm" wire:click="createTicketGroup">+ Neue Spalte</x-ui-button>
                </div>

                @php $completedTickets = $groups->filter(fn($g) => $g->isDoneGroup ?? false)->flatMap(fn($g) => $g->tasks); @endphp
                @if($completedTickets->count() > 0)
                    <div>
                        <h4 class="font-medium mb-3">Erledigte Tickets ({{ $completedTickets->count() }})</h4>
                        <div class="space-y-1 max-h-60 overflow-y-auto">
                            @foreach($completedTickets->take(10) as $ticket)
                                <a href="{{ route('helpdesk.tickets.show', $ticket) }}" class="block p-2 bg-gray-50 rounded text-sm hover:bg-gray-100 transition" wire:navigate>
                                    <div class="d-flex items-center gap-2">
                                        <x-heroicon-o-check-circle class="w-4 h-4 text-green-500"/>
                                        <span class="truncate">{{ $ticket->title }}</span>
                                    </div>
                                </a>
                            @endforeach
                            @if($completedTickets->count() > 10)
                                <div class="text-xs text-gray-500 italic text-center">+{{ $completedTickets->count() - 10 }} weitere</div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="text-sm text-gray-500 italic">Noch keine erledigten Tickets</div>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <!-- Kanban-Board (Planner-kompatibel) -->
    <x-ui-kanban-container sortable="updateTicketGroupOrder" sortable-group="updateTicketOrder">
        {{-- Inbox (muted, nicht sortierbar als Gruppe) --}}
        @php $inbox = $groups->first(); @endphp
        @if($inbox)
            <x-ui-kanban-column :title="($inbox->label ?? 'INBOX')" :sortable-id="null" :scrollable="true" :muted="true">
                @foreach ($inbox->tasks as $ticket)
                    <livewire:helpdesk.ticket-preview-card 
                        :ticket="$ticket"
                        wire:key="ticket-preview-{{ $ticket->uuid }}"
                    />
                @endforeach
            </x-ui-kanban-column>
        @endif

        {{-- Mittlere Spalten (scrollable) --}}
        @foreach($groups->filter(fn ($g) => !($g->isInbox || ($g->isDoneGroup ?? false))) as $column)
            <x-ui-kanban-column :title="($column->label ?? $column->name ?? 'Spalte')" :sortable-id="$column->id" :scrollable="true">
                <x-slot name="headerActions">
                    <button 
                        wire:click="createTicket('{{$column->id}}')" 
                        class="text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
                        title="Neues Ticket">
                        @svg('heroicon-o-plus-circle', 'w-4 h-4')
                    </button>
                    <button 
                        @click="$dispatch('open-modal-ticket-group-settings', { ticketGroupId: {{ $column->id }} })"
                        class="text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
                        title="Einstellungen">
                        @svg('heroicon-o-cog-6-tooth', 'w-4 h-4')
                    </button>
                </x-slot>

                @foreach($column->tasks as $ticket)
                    <livewire:helpdesk.ticket-preview-card 
                        :ticket="$ticket"
                        wire:key="ticket-preview-{{ $ticket->uuid }}"
                    />
                @endforeach
            </x-ui-kanban-column>
        @endforeach
    </x-ui-kanban-container>

    <livewire:helpdesk.ticket-group-settings-modal/>

</x-ui-page>
