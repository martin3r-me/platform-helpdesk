<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Meine Tickets" />
    </x-slot>

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

    <x-ui-kanban-container sortable="updateTicketGroupOrder" sortable-group="updateTicketOrder" wire:key="my-tickets-kanban-container">

        {{-- Backlog (nicht sortierbar) --}}
        @php $inbox = $groups->first(fn($g) => ($g->isInbox ?? false)); @endphp
    @if($inbox)
            <x-ui-kanban-column :title="($inbox->label ?? 'Posteingang')" :sortable-id="null" :scrollable="true" :muted="true">
                @foreach(($inbox->tasks ?? []) as $ticket)
                    <x-ui-kanban-card :title="$ticket->title" :sortable-id="$ticket->id" :href="route('helpdesk.tickets.show', $ticket)">
                        <div class="text-xs text-[var(--ui-muted)]">
                            @if($ticket->due_date)
                                Fällig: {{ $ticket->due_date->format('d.m.Y') }}
                            @else
                                Keine Fälligkeit
                            @endif
                        </div>
                    </x-ui-kanban-card>
                @endforeach
            </x-ui-kanban-column>
        @endif

        {{-- Mittlere Spalten (sortierbar) --}}
        @foreach($groups->filter(fn ($g) => !($g->isDoneGroup ?? false) && !($g->isInbox ?? false)) as $column)
            <x-ui-kanban-column :title="($column->label ?? $column->name ?? 'Spalte')" :sortable-id="$column->id" :scrollable="true">
                <x-slot name="headerActions">
                    <button 
                        @click="$dispatch('open-modal-ticket-group-settings', { ticketGroupId: {{ $column->id }} })"
                        class="text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition-colors"
                        title="Gruppen-Einstellungen"
                    >
                        @svg('heroicon-o-cog-6-tooth', 'w-4 h-4')
                    </button>
                </x-slot>
                @foreach(($column->tasks ?? []) as $ticket)
                    <x-ui-kanban-card :sortable-id="$ticket->id" :title="$ticket->title" :href="route('helpdesk.tickets.show', $ticket)">
                        {{-- Team/Board-Zeile --}}
                        @if($ticket->helpdeskBoard)
                            <div class="mb-2">
                                <span class="inline-flex items-center gap-1 text-xs text-[var(--ui-muted)]">
                                    @svg('heroicon-o-rectangle-stack','w-3.5 h-3.5')
                                    <span class="font-medium">{{ $ticket->helpdeskBoard->name }}</span>
                                </span>
                            </div>
                        @endif

                        {{-- Meta: Zuständiger • Priorität • Story Points --}}
                        <div class="flex items-center justify-between mb-2 gap-2">
                            <div class="flex items-center gap-2 text-xs text-[var(--ui-secondary)] min-w-0">
                                @php $owner = $ticket->assignee ?? ($ticket->user ?? null); $initials = $owner ? mb_strtoupper(mb_substr($owner->name ?? $owner->email ?? 'U', 0, 1)) : null; @endphp
                                @if($owner)
                                    <span class="inline-flex items-center gap-1 min-w-0">
                                        <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 text-[10px] text-[var(--ui-secondary)]">{{ $initials }}</span>
                                        <span class="truncate max-w-[7rem]">{{ $owner->name ?? $owner->email }}</span>
                                    </span>
                                @endif
                            </div>
                            <div class="flex items-center gap-1 flex-shrink-0">
                                @if($ticket->priority)
                                    <x-ui-badge variant="secondary" size="xs">{{ $ticket->priority->label() }}</x-ui-badge>
                                @endif
                                @if($ticket->story_points)
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] bg-[var(--ui-primary-5)] text-[color:var(--ui-primary)]">
                                        @svg('heroicon-o-sparkles','w-3 h-3')
                                        SP {{ is_object($ticket->story_points) ? ($ticket->story_points->points() ?? $ticket->story_points) : $ticket->story_points }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Kurzbeschreibung --}}
                        @if($ticket->description)
                            <div class="text-xs text-[var(--ui-muted)] mb-2 line-clamp-2">{{ Str::limit($ticket->description, 80) }}</div>
                        @endif

                        {{-- Flags --}}
                        <div class="text-xs text-[var(--ui-muted)] flex items-center gap-2">
                            @if($ticket->due_date)
                                <span class="inline-flex items-center gap-1">
                                    @svg('heroicon-o-calendar','w-3 h-3')
                                    {{ $ticket->due_date->format('d.m.Y') }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1">
                                    @svg('heroicon-o-calendar','w-3 h-3')
                                    Keine Fälligkeit
                                </span>
                            @endif

                            <div class="flex items-center gap-1 ml-auto">
                                @if($ticket->isEscalated())
                                    <span class="inline-flex items-center gap-1 text-[10px] text-[var(--ui-danger)]">
                                        @svg('heroicon-o-fire','w-3 h-3') Eskaliert
                                    </span>
                                @endif
                                @if(($ticket->status?->value ?? null) === 'done')
                                    <span class="inline-flex items-center gap-1 text-[10px] text-[var(--ui-success)]">
                                        @svg('heroicon-o-check-circle','w-3 h-3') Erledigt
                                    </span>
                                @endif
                            </div>
                        </div>
                    </x-ui-kanban-card>
                @endforeach
            </x-ui-kanban-column>
        @endforeach

        {{-- Erledigt (nicht sortierbar) --}}
        @php $done = $groups->first(fn($g) => ($g->isDoneGroup ?? false)); @endphp
        @if($done)
            <x-ui-kanban-column :title="($done->label ?? 'Erledigt')" :sortable-id="null" :scrollable="true" :muted="true">
                @foreach(($done->tasks ?? []) as $ticket)
                    <x-ui-kanban-card :sortable-id="$ticket->id" :title="$ticket->title" :href="route('helpdesk.tickets.show', $ticket)">
                        {{-- Team/Board-Zeile --}}
                        @if($ticket->helpdeskBoard)
                            <div class="mb-2">
                                <span class="inline-flex items-center gap-1 text-xs text-[var(--ui-muted)]">
                                    @svg('heroicon-o-rectangle-stack','w-3.5 h-3.5')
                                    <span class="font-medium">{{ $ticket->helpdeskBoard->name }}</span>
                                </span>
                            </div>
                        @endif

                        {{-- Meta: Zuständiger • Priorität • Story Points --}}
                        <div class="flex items-center justify-between mb-2 gap-2">
                            <div class="flex items-center gap-2 text-xs text-[var(--ui-secondary)] min-w-0">
                                @php $owner = $ticket->assignee ?? ($ticket->user ?? null); $initials = $owner ? mb_strtoupper(mb_substr($owner->name ?? $owner->email ?? 'U', 0, 1)) : null; @endphp
                                @if($owner)
                                    <span class="inline-flex items-center gap-1 min-w-0">
                                        <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 text-[10px] text-[var(--ui-secondary)]">{{ $initials }}</span>
                                        <span class="truncate max-w-[7rem]">{{ $owner->name ?? $owner->email }}</span>
                                    </span>
                                @endif
                            </div>
                            <div class="flex items-center gap-1 flex-shrink-0">
                                @if($ticket->priority)
                                    <x-ui-badge variant="secondary" size="xs">{{ $ticket->priority->label() }}</x-ui-badge>
                                @endif
                                @if($ticket->story_points)
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] bg-[var(--ui-primary-5)] text-[color:var(--ui-primary)]">
                                        @svg('heroicon-o-sparkles','w-3 h-3')
                                        SP {{ is_object($ticket->story_points) ? ($ticket->story_points->points() ?? $ticket->story_points) : $ticket->story_points }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Kurzbeschreibung --}}
                        @if($ticket->description)
                            <div class="text-xs text-[var(--ui-muted)] mb-2 line-clamp-2">{{ Str::limit($ticket->description, 80) }}</div>
                        @endif

                        {{-- Flags --}}
                        <div class="text-xs text-[var(--ui-muted)] flex items-center gap-2">
                            @if($ticket->due_date)
                                <span class="inline-flex items-center gap-1">
                                    @svg('heroicon-o-calendar','w-3 h-3')
                                    {{ $ticket->due_date->format('d.m.Y') }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1">
                                    @svg('heroicon-o-calendar','w-3 h-3')
                                    Keine Fälligkeit
                                </span>
                            @endif

                            <div class="flex items-center gap-1 ml-auto">
                                @if($ticket->isEscalated())
                                    <span class="inline-flex items-center gap-1 text-[10px] text-[var(--ui-danger)]">
                                        @svg('heroicon-o-fire','w-3 h-3') Eskaliert
                                    </span>
                                @endif
                                @if(($ticket->status?->value ?? null) === 'done')
                                    <span class="inline-flex items-center gap-1 text-[10px] text-[var(--ui-success)]">
                                        @svg('heroicon-o-check-circle','w-3 h-3') Erledigt
                                    </span>
                                @endif
                            </div>
                        </div>
                    </x-ui-kanban-card>
            @endforeach
        </x-ui-kanban-column>
    @endif

    </x-ui-kanban-container>

    <livewire:helpdesk.ticket-group-settings-modal/>

</x-ui-page>
