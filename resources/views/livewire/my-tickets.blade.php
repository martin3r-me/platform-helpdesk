<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Meine Tickets" icon="heroicon-o-ticket">
            <div class="flex items-center gap-2">
                <x-ui-button variant="success" size="sm" wire:click="createTicket()">+ Neues Ticket</x-ui-button>
                <x-ui-button variant="primary-outline" size="sm" wire:click="createTicketGroup">+ Neue Spalte</x-ui-button>
            </div>
        </x-ui-page-navbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Schnellzugriff" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                {{-- Aktionen --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Aktionen</h3>
                    <div class="space-y-2">
                        <x-ui-button variant="secondary-outline" size="sm" :href="route('helpdesk.dashboard')" wire:navigate class="w-full">
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-home', 'w-4 h-4')
                                Zum Dashboard
                            </span>
                        </x-ui-button>
                        <x-ui-button variant="success-outline" size="sm" class="w-full" wire:click="createTicket()">
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-plus', 'w-4 h-4')
                                Neues Ticket
                            </span>
                        </x-ui-button>
                        <x-ui-button variant="primary-outline" size="sm" class="w-full" wire:click="createTicketGroup">
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-square-2-stack', 'w-4 h-4')
                                Neue Spalte
                            </span>
                        </x-ui-button>
                    </div>
                </div>

                {{-- Schnellstatistiken --}}
                @php
                    $totalOpen = $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks)->count();
                    $doneGroup = $groups->first(fn($g) => ($g->isDoneGroup ?? false));
                    $doneCount = $doneGroup ? $doneGroup->tasks->count() : 0;
                @endphp
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Schnellstatistiken</h3>
                    <div class="space-y-3">
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="text-xs text-[var(--ui-muted)]">Offene Tickets</div>
                            <div class="text-lg font-bold text-[var(--ui-secondary)]">{{ $totalOpen }}</div>
                        </div>
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="text-xs text-[var(--ui-muted)]">Erledigt</div>
                            <div class="text-lg font-bold text-[var(--ui-secondary)]">{{ $doneCount }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>

    <!-- Kanban-Board (Planner-kompatibel) -->
    <x-ui-kanban-container sortable="updateTicketGroupOrder" sortable-group="updateTicketOrder" wire:key="my-tickets-kanban-container">
        {{-- Inbox (muted, nicht sortierbar als Gruppe) --}}
        @php $inbox = $groups->first(); @endphp
        @if($inbox)
            <x-ui-kanban-column :title="($inbox->label ?? 'INBOX')" :sortable-id="null" :scrollable="true" :muted="true" wire:key="my-tickets-column-inbox">
                @foreach ($inbox->tasks as $ticket)
                    <livewire:helpdesk.ticket-preview-card :ticket="$ticket" wire:key="ticket-preview-{{ $ticket->id ?? $ticket->uuid }}" />
                @endforeach
            </x-ui-kanban-column>
        @endif

        {{-- Mittlere Spalten (scrollable) --}}
        @foreach($groups->filter(fn ($g) => !($g->isInbox || ($g->isDoneGroup ?? false))) as $column)
            <x-ui-kanban-column :title="($column->label ?? $column->name ?? 'Spalte')" :sortable-id="$column->id" :scrollable="true" wire:key="my-tickets-column-{{ $column->id }}">
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
                    <livewire:helpdesk.ticket-preview-card :ticket="$ticket" wire:key="ticket-preview-{{ $ticket->id ?? $ticket->uuid }}" />
                @endforeach
            </x-ui-kanban-column>
        @endforeach
    </x-ui-kanban-container>

    <livewire:helpdesk.ticket-group-settings-modal wire:key="helpdesk-ticket-group-settings-modal"/>

    </x-ui-page-container>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
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

</x-ui-page>
