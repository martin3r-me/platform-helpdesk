<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="{{ $sla->name }}" icon="heroicon-o-clock" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" defaultOpen="true" storeKey="sidebarOpen" side="left">
            <div class="p-6 space-y-6">
                {{-- Navigation Buttons --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Navigation</h3>
                    <div class="space-y-2">
                        <x-ui-button
                            variant="secondary-outline"
                            size="sm"
                            :href="route('helpdesk.slas.index')"
                            wire:navigate
                            class="w-full"
                        >
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-arrow-left', 'w-4 h-4')
                                Zurück zu SLAs
                            </span>
                        </x-ui-button>
                    </div>
                </div>

                {{-- Quick Actions --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Aktionen</h3>
                    <div class="space-y-2">
                        @if($this->isDirty)
                            <x-ui-button x-transition.opacity variant="primary" size="sm" wire:click="save" class="w-full">
                                <span class="inline-flex items-center gap-2">
                                    @svg('heroicon-o-check','w-4 h-4')
                                    Speichern
                                </span>
                            </x-ui-button>
                        @endif
                        <x-ui-confirm-button
                            action="deleteSla"
                            text="SLA löschen"
                            confirmText="Wirklich löschen?"
                            variant="danger"
                            size="sm"
                            :icon="@svg('heroicon-o-trash', 'w-4 h-4')->toHtml()"
                            class="w-full"
                        />
                    </div>
                </div>

                {{-- SLA Info --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">SLA Info</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-[var(--ui-muted)]">Status:</span>
                            <span class="font-medium text-[var(--ui-secondary)]">
                                @if($sla->is_active)
                                    <x-ui-badge variant="success" size="xs">Aktiv</x-ui-badge>
                                @else
                                    <x-ui-badge variant="secondary" size="xs">Inaktiv</x-ui-badge>
                                @endif
                            </span>
                        </div>
                        @if($sla->response_time_hours)
                            <div class="flex justify-between">
                                <span class="text-[var(--ui-muted)]">Reaktionszeit:</span>
                                <span class="font-medium text-[var(--ui-secondary)]">{{ $sla->response_time_hours }}h</span>
                            </div>
                        @endif
                        @if($sla->resolution_time_hours)
                            <div class="flex justify-between">
                                <span class="text-[var(--ui-muted)]">Lösungszeit:</span>
                                <span class="font-medium text-[var(--ui-secondary)]">{{ $sla->resolution_time_hours }}h</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-4">
                <div class="text-sm text-[var(--ui-muted)]">Letzte Aktivitäten</div>
                <div class="space-y-3 text-sm">
                    <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                        <div class="font-medium text-[var(--ui-secondary)] truncate">SLA geladen</div>
                        <div class="text-[var(--ui-muted)]">gerade eben</div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container spacing="space-y-8">
        {{-- SLA Grunddaten --}}
        <x-ui-panel title="SLA-Details">
            <x-ui-form-grid :cols="2" :gap="6">
                <x-ui-input-text 
                    name="sla.name"
                    label="SLA-Name"
                    wire:model.live.debounce.500ms="sla.name"
                    placeholder="z.B. Standard, Kritisch, Express"
                    required
                    :errorKey="'sla.name'"
                />
                <div class="flex items-center">
                    <x-ui-input-checkbox
                        model="sla.is_active"
                        checked-label="SLA ist aktiv"
                        unchecked-label="SLA ist inaktiv"
                        size="md"
                        block="true"
                    />
                </div>
            </x-ui-form-grid>
            <div class="mt-6">
                <x-ui-input-textarea 
                    name="sla.description"
                    label="Beschreibung"
                    wire:model.live.debounce.500ms="sla.description"
                    placeholder="Beschreibung des SLAs (optional)"
                    rows="3"
                    :errorKey="'sla.description'"
                />
            </div>
        </x-ui-panel>

        {{-- SLA-Zeiten --}}
        <x-ui-panel title="Zeitvorgaben">
            <x-ui-form-grid :cols="2" :gap="6">
                <x-ui-input-number
                    name="sla.response_time_hours"
                    label="Reaktionszeit (Stunden)"
                    wire:model.live.debounce.500ms="sla.response_time_hours"
                    placeholder="z.B. 4"
                    :nullable="true"
                    min="1"
                    :errorKey="'sla.response_time_hours'"
                />
                <x-ui-input-number
                    name="sla.resolution_time_hours"
                    label="Lösungszeit (Stunden)"
                    wire:model.live.debounce.500ms="sla.resolution_time_hours"
                    placeholder="z.B. 24"
                    :nullable="true"
                    min="1"
                    :errorKey="'sla.resolution_time_hours'"
                />
            </x-ui-form-grid>
        </x-ui-panel>

        {{-- Verwendung --}}
        <x-ui-panel title="Verwendung">
            {{-- Boards die dieses SLA verwenden --}}
            <div class="mb-6">
                <h4 class="font-medium mb-3 text-[var(--ui-secondary)]">Boards mit diesem SLA</h4>
                @if($boardsUsingThisSla->count() > 0)
                    <div class="space-y-3">
                        @foreach($boardsUsingThisSla as $board)
                            <div class="flex items-center justify-between p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/60">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-[var(--ui-primary)] text-[var(--ui-primary-foreground)] rounded-lg flex items-center justify-center">
                                        @svg('heroicon-o-folder', 'w-4 h-4')
                                    </div>
                                    <div>
                                        <div class="font-medium text-[var(--ui-secondary)]">{{ $board->name }}</div>
                                        <div class="text-sm text-[var(--ui-muted)]">{{ $board->team->name }}</div>
                                    </div>
                                </div>
                                <a href="{{ route('helpdesk.boards.show', $board->id) }}" 
                                   class="text-[var(--ui-primary)] hover:underline text-sm"
                                   wire:navigate>
                                    Board öffnen
                                </a>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-4 text-[var(--ui-muted)]">
                        Keine Boards verwenden dieses SLA
                    </div>
                @endif
            </div>

            {{-- Letzte Tickets mit diesem SLA --}}
            <div>
                <h4 class="font-medium mb-3 text-[var(--ui-secondary)]">Letzte Tickets mit diesem SLA</h4>
                @if($ticketsUsingThisSla->count() > 0)
                    <div class="space-y-3">
                        @foreach($ticketsUsingThisSla as $ticket)
                            <div class="flex items-center justify-between p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/60">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-[var(--ui-warning)] text-[var(--ui-warning-foreground)] rounded-lg flex items-center justify-center">
                                        @svg('heroicon-o-ticket', 'w-4 h-4')
                                    </div>
                                    <div>
                                        <div class="font-medium text-[var(--ui-secondary)]">{{ $ticket->title }}</div>
                                        <div class="text-sm text-[var(--ui-muted)]">
                                            {{ $ticket->helpdeskBoard->name }} • 
                                            @if($ticket->userInCharge)
                                                {{ $ticket->userInCharge->name }}
                                            @else
                                                Nicht zugewiesen
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if($ticket->is_done)
                                        <x-ui-badge variant="success" size="xs">Erledigt</x-ui-badge>
                                    @else
                                        <x-ui-badge variant="warning" size="xs">Offen</x-ui-badge>
                                    @endif
                                    <a href="{{ route('helpdesk.tickets.show', $ticket->id) }}" 
                                       class="text-[var(--ui-primary)] hover:underline text-sm"
                                       wire:navigate>
                                        Ticket öffnen
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-4 text-[var(--ui-muted)]">
                        Keine Tickets mit diesem SLA gefunden
                    </div>
                @endif
            </div>
        </x-ui-panel>
    </x-ui-page-container>

</x-ui-page>
