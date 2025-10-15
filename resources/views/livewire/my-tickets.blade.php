<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Meine Tickets" icon="heroicon-o-ticket">
            <x-slot name="titleActions"></x-slot>
            <div class="text-sm text-[var(--ui-muted)]">{{ now()->format('l') }}, {{ now()->format('d.m.Y') }}</div>
        </x-ui-page-navbar>
    </x-slot>

    <x-ui-page-container>
        <x-ui-kanban-container sortable="updateTicketGroupOrder" sortable-group="updateTicketOrder" wire:key="my-tickets-kanban-container">
            @php $inbox = $groups->first(); @endphp
            @if($inbox)
                <x-ui-kanban-column :title="($inbox->label ?? 'INBOX')" :sortable-id="null" :scrollable="true" :muted="true" wire:key="my-tickets-column-inbox">
                    @foreach ($inbox->tasks as $ticket)
                        <livewire:helpdesk.ticket-preview-card :ticket="$ticket" wire:key="ticket-preview-{{ $ticket->id ?? $ticket->uuid }}" />
                    @endforeach
                </x-ui-kanban-column>
            @endif

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

            @php $done = $groups->first(fn($g) => ($g->isDoneGroup ?? false)); @endphp
            @if($done)
                <x-ui-kanban-column :title="($done->label ?? 'Erledigt')" :sortable-id="null" :scrollable="true" :muted="true" wire:key="my-tickets-column-done">
                    @foreach($done->tasks as $ticket)
                        <livewire:helpdesk.ticket-preview-card :ticket="$ticket" wire:key="ticket-preview-{{ $ticket->id ?? $ticket->uuid }}" />
                    @endforeach
                </x-ui-kanban-column>
            @endif
        </x-ui-kanban-container>

        <livewire:helpdesk.ticket-group-settings-modal wire:key="helpdesk-ticket-group-settings-modal"/>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true">
            <div class="p-4 space-y-4">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Statistiken</h3>
                    <div class="space-y-2">
                        @php 
                            $stats = [
                                ['title' => 'Story Points (offen)', 'count' => $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks)->sum(fn($t) => $t->story_points?->points() ?? 0), 'icon' => 'chart-bar', 'variant' => 'warning'],
                                ['title' => 'Story Points (erledigt)', 'count' => ($groups->first(fn($g)=>($g->isDoneGroup ?? false))?->tasks ?? collect())->sum(fn($t) => $t->story_points?->points() ?? 0), 'icon' => 'check-circle', 'variant' => 'success'],
                                ['title' => 'Offen', 'count' => $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->sum(fn($g) => $g->tasks->count()), 'icon' => 'clock', 'variant' => 'warning'],
                                ['title' => 'Gesamt', 'count' => $groups->flatMap(fn($g) => $g->tasks)->count(), 'icon' => 'document-text', 'variant' => 'secondary'],
                                ['title' => 'Erledigt', 'count' => ($groups->first(fn($g)=>($g->isDoneGroup ?? false))?->tasks ?? collect())->count(), 'icon' => 'check-circle', 'variant' => 'success'],
                                ['title' => 'Ohne Fälligkeit', 'count' => $groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => !$t->due_date)->count(), 'icon' => 'calendar', 'variant' => 'neutral'],
                                ['title' => 'Überfällig', 'count' => $groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => $t->due_date && $t->due_date->isPast() && !$t->is_done)->count(), 'icon' => 'exclamation-circle', 'variant' => 'danger'],
                            ];
                        @endphp
                        @foreach($stats as $stat)
                            <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                                <div class="flex items-center gap-2">
                                    @svg('heroicon-o-' . $stat['icon'], 'w-4 h-4 text-[var(--ui-' . $stat['variant'] . ')]')
                                    <span class="text-sm text-[var(--ui-secondary)]">{{ $stat['title'] }}</span>
                                </div>
                                <span class="text-sm font-semibold text-[var(--ui-' . $stat['variant'] . ')]">
                                    {{ $stat['count'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
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

</x-ui-page>
