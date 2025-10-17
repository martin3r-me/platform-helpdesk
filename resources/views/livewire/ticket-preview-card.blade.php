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
