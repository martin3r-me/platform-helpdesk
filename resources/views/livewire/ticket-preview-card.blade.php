<x-ui-kanban-card 
    :sortable-id="$ticket->id" 
    :title="'TICKET'"
    href="{{ route('helpdesk.tickets.show', $ticket) }}"
>
    {{ $ticket->title }} @if($ticket->helpdeskBoard)<small class = "text-secondary">| {{ $ticket->helpdeskBoard?->name }}</small>@endif 
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
            @if($ticket->is_frog)
            <x-ui-badge variant="danger" size="xs">
                Frosch
            </x-ui-badge>
            @endif
        </div>
    </x-slot>
</x-ui-kanban-card>
