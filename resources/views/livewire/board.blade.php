<div class="h-full d-flex">
    <!-- Info-Bereich (fixe Breite) -->
    <div class="w-80 border-r border-muted p-4 flex-shrink-0">
        <!-- Board-Info -->
        <div class="mb-6">
            <div class="d-flex justify-between items-start mb-2">
                <h3 class="text-lg font-semibold">{{ $helpdeskBoard->name }}</h3>
                <x-ui-button variant="info" size="sm" @click="$dispatch('open-modal-board-settings', { boardId: {{ $helpdeskBoard->id }} })">
                    <div class="d-flex items-center gap-2">
                        @svg('heroicon-o-information-circle', 'w-4 h-4')
                        Info
                    </div>
                </x-ui-button>
            </div>
            <div class="text-sm text-gray-600 mb-4">{{ $helpdeskBoard->description ?? 'Keine Beschreibung' }}</div>
            
            <!-- Statistiken mit Dashboard-Tiles in 2-spaltigem Grid -->
            <div class="grid grid-cols-2 gap-2 mb-4">
                <x-ui-dashboard-tile
                    title="Story Points (offen)"
                    :count="$groups->filter(fn($g) => !($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks)->sum(fn($t) => $t->story_points?->points() ?? 0)"
                    icon="chart-bar"
                    variant="warning"
                    size="sm"
                />
                <x-ui-dashboard-tile
                    title="Story Points (erledigt)"
                    :count="$groups->filter(fn($g) => $g->isDoneGroup ?? false)->flatMap(fn($g) => $g->tasks)->sum(fn($t) => $t->story_points?->points() ?? 0)"
                    icon="check-circle"
                    variant="success"
                    size="sm"
                />
                <x-ui-dashboard-tile
                    title="Offen"
                    :count="$groups->filter(fn($g) => !($g->isDoneGroup ?? false))->sum(fn($g) => $g->tasks->count())"
                    icon="clock"
                    variant="warning"
                    size="sm"
                />
                <x-ui-dashboard-tile
                    title="Gesamt"
                    :count="$groups->flatMap(fn($g) => $g->tasks)->count()"
                    icon="document-text"
                    variant="secondary"
                    size="sm"
                />
                <x-ui-dashboard-tile
                    title="Erledigt"
                    :count="$groups->filter(fn($g) => $g->isDoneGroup ?? false)->sum(fn($g) => $g->tasks->count())"
                    icon="check-circle"
                    variant="success"
                    size="sm"
                />
                <x-ui-dashboard-tile
                    title="Ohne Fälligkeit"
                    :count="$groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => !$t->due_date)->count()"
                    icon="calendar"
                    variant="neutral"
                    size="sm"
                />
                <x-ui-dashboard-tile
                    title="Frösche"
                    :count="$groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => $t->is_frog)->count()"
                    icon="exclamation-triangle"
                    variant="danger"
                    size="sm"
                />
                <x-ui-dashboard-tile
                    title="Überfällig"
                    :count="$groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => $t->due_date && $t->due_date->isPast() && !$t->is_done)->count()"
                    icon="exclamation-circle"
                    variant="danger"
                    size="sm"
                />
            </div>

            <!-- Aktionen -->
            @can('update', $helpdeskBoard)
                <div class="d-flex flex-col gap-2 mb-4">
                    <x-ui-button variant="success-outline" size="sm" wire:click="createTicket()">
                        + Neues Ticket
                    </x-ui-button>
                    <x-ui-button variant="primary-outline" size="sm" wire:click="createBoardSlot">
                        + Neue Spalte
                    </x-ui-button>
                </div>
            @endcan
        </div>

        <!-- Erledigte Tickets -->
        @php $completedTickets = $groups->filter(fn($g) => $g->isDoneGroup ?? false)->flatMap(fn($g) => $g->tasks); @endphp
        @if($completedTickets->count() > 0)
            <div>
                <h4 class="font-medium mb-3">Erledigte Tickets ({{ $completedTickets->count() }})</h4>
                <div class="space-y-1 max-h-60 overflow-y-auto">
                    @foreach($completedTickets->take(10) as $ticket)
                        <a href="{{ route('helpdesk.tickets.show', $ticket) }}" 
                           class="block p-2 bg-gray-50 rounded text-sm hover:bg-gray-100 transition"
                           wire:navigate>
                            <div class="d-flex items-center gap-2">
                                <x-heroicon-o-check-circle class="w-4 h-4 text-green-500"/>
                                <span class="truncate">{{ $ticket->title }}</span>
                            </div>
                        </a>
                    @endforeach
                    @if($completedTickets->count() > 10)
                        <div class="text-xs text-gray-500 italic text-center">
                            +{{ $completedTickets->count() - 10 }} weitere
                        </div>
                    @endif
                </div>
            </div>
        @else
            <div class="text-sm text-gray-500 italic">Noch keine erledigten Tickets</div>
        @endif
    </div>

    <!-- Kanban-Board (scrollbar) -->
    <div class="flex-grow overflow-x-auto">
        <x-ui-kanban-board wire:sortable="updateTicketGroupOrder" wire:sortable-group="updateTicketOrder">

            {{-- Mittlere Spalten --}}
            @foreach($groups->filter(fn ($g) => !($g->isDoneGroup ?? false)) as $column)
                <x-ui-kanban-column
                    :title="$column->label"
                    :sortable-id="$column->id">

                    <x-slot name="extra">
                        <div class="d-flex gap-1">
                            @can('update', $helpdeskBoard)
                                <x-ui-button variant="success-outline" size="sm" class="w-full" wire:click="createTicket('{{ $column->id }}')">
                                    + Neues Ticket
                                </x-ui-button>
                                <x-ui-button variant="primary-outline" size="sm" class="w-full" @click="$dispatch('open-modal-board-slot-settings', { boardSlotId: {{ $column->id }} })">Settings</x-ui-button>
                            @endcan
                        </div>
                    </x-slot>

                    @foreach($column->tasks as $ticket)
                        <livewire:helpdesk.ticket-preview-card 
                            :ticket="$ticket"
                            wire:key="ticket-preview-{{ $ticket->uuid }}"
                        />
                    @endforeach

                </x-ui-kanban-column>
            @endforeach

            {{-- ERLEDIGT Spalte --}}
            @php $doneGroup = $groups->filter(fn($g) => $g->isDoneGroup ?? false)->first(); @endphp
            @if($doneGroup)
                <x-ui-kanban-column
                    title="ERLEDIGT"
                    :sortable-id="$doneGroup->id">

                    @foreach($doneGroup->tasks as $ticket)
                        <livewire:helpdesk.ticket-preview-card 
                            :ticket="$ticket"
                            wire:key="ticket-preview-{{ $ticket->uuid }}"
                        />
                    @endforeach

                </x-ui-kanban-column>
            @endif

        </x-ui-kanban-board>
    </div>

    <livewire:helpdesk.board-settings-modal/>
    <livewire:helpdesk.board-slot-settings-modal/>
</div>
