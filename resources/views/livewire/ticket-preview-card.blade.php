@props(['ticket'])

@php
    $isDone = $ticket->is_done ?? false;
    $isEscalated = $ticket->isEscalated() ?? false;
    $isCritical = $ticket->isCritical() ?? false;
@endphp
<x-ui-kanban-card
    :title="''"
    :sortable-id="$ticket->id"
    :href="route('helpdesk.tickets.show', $ticket)"
>
    <div class="relative group/card">
        <!-- Lösch-Icon -->
        <div class="absolute -top-1 -right-1 opacity-0 group-hover/card:opacity-100 transition-opacity z-10">
            <button
                type="button"
                wire:click.stop.prevent="deleteTicket({{ $ticket->id }})"
                wire:confirm="Ticket wirklich löschen?"
                class="p-1 rounded hover:bg-[var(--ui-danger)]/10 text-[var(--ui-muted)] hover:text-[var(--ui-danger)] transition-colors"
                title="Ticket löschen"
            >
                @svg('heroicon-o-trash', 'w-3.5 h-3.5')
            </button>
        </div>

    <!-- Story Points und Eskalation (oben in eigene Zeile) -->
    @if($isEscalated || $ticket->story_points)
        <div class="mb-3 flex items-start gap-2">
            @if($isEscalated)
                <span class="inline-flex items-start gap-1 text-xs {{ $isCritical ? 'text-[var(--ui-danger)] font-semibold' : 'text-[var(--ui-warning)]' }}">
                    @svg('heroicon-o-fire','w-3 h-3 mt-0.5')
                    <span>{{ $isCritical ? 'Kritisch' : 'Eskaliert' }}</span>
                    @if(($ticket->escalation_count ?? 0) > 0)
                        <span class="ml-0.5" title="Eskalationen: {{ $ticket->escalation_count }}x">
                            ({{ $ticket->escalation_count }})
                        </span>
                    @endif
                </span>
            @endif
            @if($ticket->story_points)
                <span class="inline-flex items-start gap-1 text-xs text-[var(--ui-muted)]">
                    @svg('heroicon-o-sparkles','w-3 h-3 mt-0.5')
                    <span>SP {{ is_object($ticket->story_points) ? ($ticket->story_points->points() ?? $ticket->story_points) : $ticket->story_points }}</span>
                </span>
            @endif
        </div>
    @endif

    <!-- Verantwortlicher (eigene Zeile) -->
    @php
        $userInCharge = $ticket->userInCharge ?? null;
        $initials = $userInCharge ? mb_strtoupper(mb_substr($userInCharge->name ?? $userInCharge->email ?? 'U', 0, 1)) : null;
    @endphp
    @if($userInCharge)
        <div class="mb-3">
            <span class="inline-flex items-start gap-1 text-xs text-[var(--ui-muted)] min-w-0">
                @if($userInCharge->avatar)
                    <img src="{{ $userInCharge->avatar }}" alt="{{ $userInCharge->name ?? $userInCharge->email }}" class="w-3.5 h-3.5 rounded-full object-cover mt-0.5">
                @else
                    <span class="inline-flex items-center justify-center w-3.5 h-3.5 rounded-full bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 text-[10px] text-[var(--ui-muted)] mt-0.5">{{ $initials }}</span>
                @endif
                <span class="truncate max-w-[7rem]">{{ $userInCharge->name ?? $userInCharge->email }}</span>
            </span>
        </div>
    @endif

    <!-- Titel (durchgestrichen wenn erledigt) -->
    <div class="mb-4">
        <h4 class="text-sm font-medium text-[var(--ui-secondary)] m-0 {{ $isDone ? 'line-through text-[var(--ui-muted)]' : '' }}">
            {{ $ticket->title }}
        </h4>
    </div>

    <!-- Meta: Team -->
    @if($ticket->team)
        <div class="mb-3">
            <span class="inline-flex items-start gap-1 text-xs text-[var(--ui-muted)]">
                @svg('heroicon-o-user-group','w-3 h-3 mt-0.5')
                <span>{{ $ticket->team->name }}</span>
            </span>
        </div>
    @endif

    <!-- Meta: Board (statt Projekt) -->
    @if($ticket->helpdeskBoard)
        <div class="mb-3">
            <span class="inline-flex items-start gap-1 text-xs text-[var(--ui-muted)] min-w-0">
                @svg('heroicon-o-rectangle-stack','w-2.5 h-2.5 mt-0.5')
                <span class="truncate max-w-[9rem]">{{ $ticket->helpdeskBoard->name }}</span>
            </span>
        </div>
    @endif

    <!-- GitHub Repo Integration -->
    @php
        $githubRepos = $ticket->githubRepositories();
        $githubRepoCount = $githubRepos->count();
    @endphp
    @if($githubRepoCount > 0)
        <div class="mb-3">
            <span class="inline-flex items-start gap-1 text-xs text-[var(--ui-muted)]" title="{{ $githubRepos->pluck('full_name')->join(', ') }}">
                @svg('heroicon-o-code-bracket','w-3 h-3 mt-0.5')
                <span>{{ $githubRepoCount }} {{ $githubRepoCount === 1 ? 'Repo' : 'Repos' }}</span>
            </span>
        </div>
    @endif

    <!-- Description (truncated) -->
    @if($ticket->description)
        <div class="text-xs text-[var(--ui-muted)] my-1.5 mb-3 line-clamp-2">
            {{ Str::limit($ticket->description, 80) }}
        </div>
    @endif

    <!-- DoD (Definition of Done) Fortschrittsanzeige -->
    @php
        $dodProgress = $ticket->dod_progress;
        $hasDod = $dodProgress['total'] > 0;
    @endphp
    @if($hasDod)
        <div class="mt-3 pt-3 border-t border-[var(--ui-border)]/30">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-xs text-[var(--ui-muted)] flex items-center gap-1">
                    @svg('heroicon-o-clipboard-document-check', 'w-3 h-3')
                    DoD
                </span>
                <span class="text-xs font-medium {{ $dodProgress['percentage'] === 100 ? 'text-[var(--ui-success)]' : 'text-[var(--ui-primary)]' }}">
                    {{ $dodProgress['completed'] }}/{{ $dodProgress['total'] }}
                </span>
            </div>
            <div class="w-full bg-[var(--ui-muted-10)] rounded-full h-1.5 overflow-hidden">
                <div
                    class="h-1.5 rounded-full transition-all duration-300 {{ $dodProgress['percentage'] === 100 ? 'bg-[var(--ui-success)]' : 'bg-[var(--ui-primary)]' }}"
                    style="width: {{ $dodProgress['percentage'] }}%"
                ></div>
            </div>
        </div>
    @endif
    </div>
</x-ui-kanban-card>
