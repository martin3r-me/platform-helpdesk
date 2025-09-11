<x-ui-kanban-card 
    :sortable-id="$ticket->id" 
    :title="'TICKET'"
    href="{{ route('helpdesk.tickets.show', $ticket) }}"
>
    <div class="d-flex items-center gap-2 mb-2">
        @if($ticket->isEscalated())
            <div class="w-6 h-6 bg-danger text-on-danger rounded-full d-flex items-center justify-center">
                @switch($ticket->escalation_level)
                    @case(\Platform\Helpdesk\Enums\TicketEscalationLevel::WARNING)
                        <x-heroicon-o-exclamation-triangle class="w-3 h-3"/>
                        @break
                    @case(\Platform\Helpdesk\Enums\TicketEscalationLevel::ESCALATED)
                        <x-heroicon-o-exclamation-circle class="w-3 h-3"/>
                        @break
                    @case(\Platform\Helpdesk\Enums\TicketEscalationLevel::CRITICAL)
                        <x-heroicon-o-fire class="w-3 h-3"/>
                        @break
                    @case(\Platform\Helpdesk\Enums\TicketEscalationLevel::URGENT)
                        <x-heroicon-o-bolt class="w-3 h-3"/>
                        @break
                @endswitch
            </div>
        @endif
        <span class="font-semibold">{{ $ticket->title }}</span>
        @if($ticket->helpdeskBoard)<small class="text-secondary">| {{ $ticket->helpdeskBoard?->name }}</small>@endif
    </div> 
    <p class = "text-xs text-muted">{{ $ticket->description }}</p>

    <x-slot name="footer">
        <span class="text-xs text-muted">Zuletzt bearbeitet: 17.07.2025</span>
        <div class="d-flex gap-1">
            @if($ticket->story_points)
            <x-ui-badge variant="secondary" size="xs">
                {{ $ticket->story_points->label() }}
            </x-ui-badge>
            @endif
            @if($ticket->priority)
            <x-ui-badge variant="secondary" size="xs">
                {{ $ticket->priority->label() }}
            </x-ui-badge>
            @endif
            @if($ticket->status)
            <x-ui-badge variant="secondary" size="xs">
                {{ $ticket->status->label() }}
            </x-ui-badge>
            @endif

        </div>
    </x-slot>
</x-ui-kanban-card>
