@props(['ticket'])

@php
    $isDone = $ticket->is_done ?? false;
    $isEscalated = $ticket->isEscalated() ?? false;
    $isCritical = $ticket->isCritical() ?? false;

    // CRM Contact via EntityLink
    $crmContact = null;
    if (class_exists(\Platform\Crm\Models\CrmContact::class)) {
        try {
            $linkService = app(\Platform\Core\Services\EntityLinkService::class);
            $links = $linkService->getLinked(
                $ticket->team_id,
                get_class($ticket),
                $ticket->id,
                \Platform\Crm\Models\CrmContact::class,
                'crm_contact',
            );
            if ($links->isNotEmpty()) {
                $linked = $links->first()->getLinkedEntity(get_class($ticket), $ticket->id);
                $crmContact = \Platform\Crm\Models\CrmContact::find($linked['id']);
            }
        } catch (\Throwable $e) {}
    }
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
                class="p-1 rounded hover:bg-red-50 text-gray-400 hover:text-red-600 transition-colors"
                title="Ticket löschen"
            >
                @svg('heroicon-o-trash', 'w-3.5 h-3.5')
            </button>
        </div>

    <!-- Story Points und Eskalation (oben in eigene Zeile) -->
    @if($isEscalated || $ticket->story_points)
        <div class="mb-3 flex items-start gap-2">
            @if($isEscalated)
                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium {{ $isCritical ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800' }}">
                    @svg('heroicon-o-fire','w-3 h-3')
                    <span>{{ $isCritical ? 'Kritisch' : 'Eskaliert' }}</span>
                    @if(($ticket->escalation_count ?? 0) > 0)
                        <span title="Eskalationen: {{ $ticket->escalation_count }}x">
                            ({{ $ticket->escalation_count }})
                        </span>
                    @endif
                </span>
            @endif
            @if($ticket->story_points)
                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-600">
                    @svg('heroicon-o-sparkles','w-3 h-3')
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
            <span class="inline-flex items-start gap-1 text-xs text-gray-500 min-w-0">
                @if($userInCharge->avatar)
                    <img src="{{ $userInCharge->avatar }}" alt="{{ $userInCharge->name ?? $userInCharge->email }}" class="w-3.5 h-3.5 rounded-full object-cover mt-0.5">
                @else
                    <span class="inline-flex items-center justify-center w-3.5 h-3.5 rounded-full bg-gray-100 border border-gray-200 text-[10px] text-gray-500 mt-0.5">{{ $initials }}</span>
                @endif
                <span class="truncate max-w-[7rem]">{{ $userInCharge->name ?? $userInCharge->email }}</span>
            </span>
        </div>
    @endif

    <!-- Titel (durchgestrichen wenn erledigt) -->
    <div class="mb-4 flex items-start gap-1.5">
        @if($ticket->is_locked)
            <span class="mt-0.5 shrink-0" title="Ticket ist gesperrt">
                @svg('heroicon-o-lock-closed', 'w-3.5 h-3.5 text-amber-500')
            </span>
        @endif
        <h4 class="text-[13px] font-medium text-gray-900 m-0 {{ $isDone ? 'line-through text-gray-400' : '' }}">
            {{ $ticket->title }}
        </h4>
    </div>

    <!-- Meta: Team -->
    @if($ticket->team)
        <div class="mb-3">
            <span class="inline-flex items-start gap-1 text-xs text-gray-400">
                @svg('heroicon-o-user-group','w-3 h-3 mt-0.5')
                <span>{{ $ticket->team->name }}</span>
            </span>
        </div>
    @endif

    <!-- Meta: Board (statt Projekt) -->
    @if($ticket->helpdeskBoard)
        <div class="mb-3">
            <span class="inline-flex items-start gap-1 text-xs text-gray-400 min-w-0">
                @svg('heroicon-o-rectangle-stack','w-2.5 h-2.5 mt-0.5')
                <span class="truncate max-w-[9rem]">{{ $ticket->helpdeskBoard->name }}</span>
            </span>
        </div>
    @endif

    <!-- Eingang -->
    @if($ticket->created_at)
        <div class="mb-3">
            <span class="inline-flex items-start gap-1 text-xs text-gray-400">
                @svg('heroicon-o-clock','w-3 h-3 mt-0.5')
                <span>{{ $ticket->created_at->format('d.m.Y H:i') }}</span>
            </span>
        </div>
    @endif

    <!-- CRM Kontakt -->
    @if($crmContact)
        <div class="mb-3">
            <span class="inline-flex items-start gap-1 text-xs text-gray-400 min-w-0">
                @svg('heroicon-o-user','w-3 h-3 mt-0.5')
                <span class="truncate max-w-[9rem]">{{ $crmContact->first_name }} {{ $crmContact->last_name }}</span>
            </span>
        </div>
    @endif

    <!-- GitHub Repo Integration -->
    @php
        $githubRepos = $ticket->githubRepositories();
        $githubRepoCount = $githubRepos->count();
    @endphp
    @if($githubRepoCount > 0)
        <div class="mb-3 flex items-start gap-1">
            @svg('heroicon-o-code-bracket','w-3 h-3 mt-0.5 shrink-0 text-gray-400')
            <div class="flex flex-wrap gap-1">
                @foreach($githubRepos as $repo)
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] leading-tight bg-gray-100 text-gray-500 truncate max-w-[9rem]" title="{{ $repo->full_name }}">{{ $repo->name ?? $repo->full_name }}</span>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Description (truncated) -->
    @if($ticket->description)
        <div class="text-xs text-gray-400 my-1.5 mb-3 line-clamp-2">
            {{ Str::limit($ticket->description, 80) }}
        </div>
    @endif

    <!-- DoD (Definition of Done) Fortschrittsanzeige -->
    @php
        $dodProgress = $ticket->dod_progress;
        $hasDod = $dodProgress['total'] > 0;
    @endphp
    @if($hasDod)
        <div class="mt-3 pt-3 border-t border-gray-200/60">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-xs text-gray-400 flex items-center gap-1">
                    @svg('heroicon-o-clipboard-document-check', 'w-3 h-3')
                    DoD
                </span>
                <span class="text-xs font-medium {{ $dodProgress['percentage'] === 100 ? 'text-green-600' : 'text-[#049b5c]' }}">
                    {{ $dodProgress['completed'] }}/{{ $dodProgress['total'] }}
                </span>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
                <div
                    class="h-1.5 rounded-full transition-all duration-300 {{ $dodProgress['percentage'] === 100 ? 'bg-green-500' : 'bg-[#049b5c]' }}"
                    style="width: {{ $dodProgress['percentage'] }}%"
                ></div>
            </div>
        </div>
    @endif
    </div>
</x-ui-kanban-card>
