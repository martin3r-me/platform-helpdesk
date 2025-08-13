<div class="d-flex h-full">
    <!-- Linke Spalte -->
    <div class="flex-grow-1 d-flex flex-col">
        <!-- Header oben (fix) -->
        <div class="border-top-1 border-bottom-1 border-muted border-top-solid border-bottom-solid p-2 flex-shrink-0">
            <div class="d-flex gap-1">
                <div class="d-flex">
                    <a href="{{ route('helpdesk.slas.index') }}" class="d-flex px-3 border-right-solid border-right-1 border-right-muted underline" wire:navigate>
                        SLAs
                    </a>
                </div>
                <div class="flex-grow-1 text-right d-flex items-center justify-end gap-2">
                    <span>{{ $sla->name }}</span>
                    @if($this->isDirty)
                        <x-ui-button 
                            variant="primary" 
                            size="sm"
                            wire:click="save"
                        >
                            <div class="d-flex items-center gap-2">
                                @svg('heroicon-o-check', 'w-4 h-4')
                                Speichern
                            </div>
                        </x-ui-button>
                    @endif
                </div>
            </div>
        </div>

        <!-- Haupt-Content (nimmt Restplatz, scrollt) -->
        <div class="flex-grow-1 overflow-y-auto p-4">
            
            {{-- SLA Grunddaten --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4 text-secondary">SLA-Details</h3>
                <div class="grid grid-cols-2 gap-4">
                    <x-ui-input-text 
                        name="sla.name"
                        label="SLA-Name"
                        wire:model.live.debounce.500ms="sla.name"
                        placeholder="z.B. Standard, Kritisch, Express"
                        required
                        :errorKey="'sla.name'"
                    />
                    <div class="d-flex items-center">
                        <x-ui-input-checkbox
                            model="sla.is_active"
                            checked-label="SLA ist aktiv"
                            unchecked-label="SLA ist inaktiv"
                            size="md"
                            block="true"
                        />
                    </div>
                </div>
                <div class="mt-4">
                    <x-ui-input-textarea 
                        name="sla.description"
                        label="Beschreibung"
                        wire:model.live.debounce.500ms="sla.description"
                        placeholder="Beschreibung des SLAs (optional)"
                        rows="3"
                        :errorKey="'sla.description'"
                    />
                </div>
            </div>

            {{-- SLA-Zeiten --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4 text-secondary">Zeitvorgaben</h3>
                <div class="grid grid-cols-2 gap-4">
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
                </div>
            </div>

            {{-- Verwendung --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4 text-secondary">Verwendung</h3>
                
                {{-- Boards die dieses SLA verwenden --}}
                <div class="mb-4">
                    <h4 class="font-medium mb-2">Boards mit diesem SLA</h4>
                    @if($boardsUsingThisSla->count() > 0)
                        <div class="space-y-2">
                            @foreach($boardsUsingThisSla as $board)
                                <div class="d-flex items-center justify-between p-3 bg-white border rounded-lg">
                                    <div class="d-flex items-center gap-3">
                                        <div class="w-8 h-8 bg-primary text-on-primary rounded-lg d-flex items-center justify-center">
                                            <x-heroicon-o-folder class="w-4 h-4"/>
                                        </div>
                                        <div>
                                            <div class="font-medium">{{ $board->name }}</div>
                                            <div class="text-sm text-gray-500">{{ $board->team->name }}</div>
                                        </div>
                                    </div>
                                    <a href="{{ route('helpdesk.boards.show', $board->id) }}" 
                                       class="text-primary hover:underline text-sm"
                                       wire:navigate>
                                        Board öffnen
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4 text-gray-500">
                            Keine Boards verwenden dieses SLA
                        </div>
                    @endif
                </div>

                {{-- Letzte Tickets mit diesem SLA --}}
                <div>
                    <h4 class="font-medium mb-2">Letzte Tickets mit diesem SLA</h4>
                    @if($ticketsUsingThisSla->count() > 0)
                        <div class="space-y-2">
                            @foreach($ticketsUsingThisSla as $ticket)
                                <div class="d-flex items-center justify-between p-3 bg-white border rounded-lg">
                                    <div class="d-flex items-center gap-3">
                                        <div class="w-8 h-8 bg-warning text-on-warning rounded-lg d-flex items-center justify-center">
                                            <x-heroicon-o-ticket class="w-4 h-4"/>
                                        </div>
                                        <div>
                                            <div class="font-medium">{{ $ticket->title }}</div>
                                            <div class="text-sm text-gray-500">
                                                {{ $ticket->helpdeskBoard->name }} • 
                                                @if($ticket->userInCharge)
                                                    {{ $ticket->userInCharge->name }}
                                                @else
                                                    Nicht zugewiesen
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex items-center gap-2">
                                        @if($ticket->is_done)
                                            <x-ui-badge variant="success" size="xs">Erledigt</x-ui-badge>
                                        @else
                                            <x-ui-badge variant="warning" size="xs">Offen</x-ui-badge>
                                        @endif
                                        <a href="{{ route('helpdesk.tickets.show', $ticket->id) }}" 
                                           class="text-primary hover:underline text-sm"
                                           wire:navigate>
                                            Ticket öffnen
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4 text-gray-500">
                            Keine Tickets mit diesem SLA gefunden
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Rechte Spalte -->
    <div class="min-w-80 w-80 d-flex flex-col border-left-1 border-left-solid border-left-muted">
        <div class="d-flex gap-2 border-top-1 border-bottom-1 border-muted border-top-solid border-bottom-solid p-2 flex-shrink-0">
            <x-heroicon-o-cog-6-tooth class="w-6 h-6"/>
            Einstellungen
        </div>
        <div class="flex-grow-1 overflow-y-auto p-4">

            {{-- Navigation Buttons --}}
            <div class="d-flex flex-col gap-2 mb-4">
                <x-ui-button 
                    variant="secondary-outline" 
                    size="md" 
                    :href="route('helpdesk.slas.index')" 
                    wire:navigate
                    class="w-full"
                >
                    <div class="d-flex items-center gap-2">
                        @svg('heroicon-o-arrow-left', 'w-4 h-4')
                        Zurück zu SLAs
                    </div>
                </x-ui-button>
            </div>

            {{-- Kurze Übersicht --}}
            <div class="mb-4 p-3 bg-muted-5 rounded-lg">
                <h4 class="font-semibold mb-2 text-secondary">SLA-Übersicht</h4>
                <div class="space-y-1 text-sm">
                    <div><strong>Name:</strong> {{ $sla->name }}</div>
                    @if($sla->description)
                        <div><strong>Beschreibung:</strong> {{ Str::limit($sla->description, 50) }}</div>
                    @endif
                    <div><strong>Status:</strong> 
                        @if($sla->is_active)
                            <span class="text-success">Aktiv</span>
                        @else
                            <span class="text-muted">Inaktiv</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Zeitvorgaben --}}
            <div class="mb-4">
                <h4 class="font-semibold mb-2">Zeitvorgaben</h4>
                <div class="space-y-2">
                    @if($sla->response_time_hours)
                        <div class="d-flex items-center gap-2 p-2 bg-muted-5 rounded">
                            <x-heroicon-o-clock class="w-4 h-4 text-muted"/>
                            <span class="text-sm">Reaktionszeit: {{ $sla->response_time_hours }} Stunden</span>
                        </div>
                    @endif
                    @if($sla->resolution_time_hours)
                        <div class="d-flex items-center gap-2 p-2 bg-muted-5 rounded">
                            <x-heroicon-o-check-circle class="w-4 h-4 text-muted"/>
                            <span class="text-sm">Lösungszeit: {{ $sla->resolution_time_hours }} Stunden</span>
                        </div>
                    @endif
                    @if(!$sla->response_time_hours && !$sla->resolution_time_hours)
                        <p class="text-sm text-muted">Keine Zeitvorgaben definiert</p>
                    @endif
                </div>
            </div>

            <hr>

            {{-- Statistiken --}}
            <div class="mb-4">
                <h4 class="font-semibold mb-2">Statistiken</h4>
                <div class="space-y-2">
                    <div class="d-flex items-center gap-2 p-2 bg-muted-5 rounded">
                        <x-heroicon-o-folder class="w-4 h-4 text-muted"/>
                        <span class="text-sm">{{ $boardsUsingThisSla->count() }} Boards verwenden dieses SLA</span>
                    </div>
                    <div class="d-flex items-center gap-2 p-2 bg-muted-5 rounded">
                        <x-heroicon-o-ticket class="w-4 h-4 text-muted"/>
                        <span class="text-sm">{{ $ticketsUsingThisSla->count() }} Tickets in der Vorschau</span>
                    </div>
                </div>
            </div>

            <hr>

            {{-- Löschen --}}
            <div class="mb-4">
                <x-ui-confirm-button 
                    action="deleteSla" 
                    text="SLA löschen" 
                    confirmText="Wirklich löschen? Dieses SLA wird von allen Boards entfernt." 
                    variant="danger-outline"
                    class="w-full"
                />
            </div>
        </div>
    </div>
</div>
