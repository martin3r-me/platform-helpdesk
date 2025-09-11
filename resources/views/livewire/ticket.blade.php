<div class="d-flex h-full">
    <!-- Linke Spalte -->
    <div class="flex-grow-1 d-flex flex-col">
        <!-- Header oben (fix) -->
        <div class="border-top-1 border-bottom-1 border-muted border-top-solid border-bottom-solid p-2 flex-shrink-0">
            <div class="d-flex gap-1">
                <div class="d-flex">
                    @if($ticket->helpdeskBoard)
                        @can('view', $ticket->helpdeskBoard)
                            <a href="{{ route('helpdesk.boards.show', $ticket->helpdeskBoard) }}" class="px-3 underline" wire:navigate>
                                Board: {{ $ticket->helpdeskBoard?->name }}
                            </a>
                        @else
                            <span class="px-3 text-gray-400" title="Kein Zugriff auf das Board">
                                Board: {{ $ticket->helpdeskBoard?->name }} <span class="italic">(kein Zugriff)</span>
                            </span>
                        @endcan
                    @endif

                    <a href="{{ route('helpdesk.my-tickets') }}" class="d-flex px-3 border-right-solid border-right-1 border-right-muted underline" wire:navigate>
                        Meine Tickets
                    </a>
                </div>
                <div class="flex-grow-1 text-right d-flex items-center justify-end gap-2">
                    <span>{{ $ticket->title }}</span>
                    <x-ui-button 
                        variant="secondary" 
                        size="sm"
                        wire:click="printTicket"
                        title="Ticket drucken"
                    >
                        <div class="d-flex items-center gap-2">
                            @svg('heroicon-o-printer', 'w-4 h-4')
                            Drucken
                        </div>
                    </x-ui-button>
                    @if($ticket->is_done)
                        <x-ui-badge variant="success" size="sm">
                            @svg('heroicon-o-check-circle', 'w-3 h-3')
                            Erledigt
                        </x-ui-badge>
                    @endif
                </div>
            </div>
        </div>

        <!-- Haupt-Content (nimmt Restplatz, scrollt) -->
        <div class="flex-grow-1 overflow-y-auto p-4">
            
            {{-- SLA Dashboard --}}
            @if($ticket->sla)
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-4 text-secondary d-flex items-center gap-2">
                        @svg('heroicon-o-clock', 'w-5 h-5')
                        Service Level Agreement
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        {{-- SLA Info --}}
                        <div class="p-4 bg-white border rounded-lg shadow-sm">
                            <div class="d-flex items-center gap-2 mb-2">
                                <x-heroicon-o-information-circle class="w-4 h-4 text-primary"/>
                                <span class="font-medium text-sm">SLA Details</span>
                            </div>
                            <div class="space-y-1">
                                <div class="text-sm">
                                    <span class="font-medium">{{ $ticket->sla->name }}</span>
                                </div>
                                @if($ticket->sla->description)
                                    <div class="text-xs text-gray-500">{{ Str::limit($ticket->sla->description, 50) }}</div>
                                @endif
                                <div class="d-flex items-center gap-1">
                                    @if($ticket->sla->is_active)
                                        <div class="w-2 h-2 bg-success rounded-full"></div>
                                        <span class="text-xs text-success">Aktiv</span>
                                    @else
                                        <div class="w-2 h-2 bg-gray-400 rounded-full"></div>
                                        <span class="text-xs text-gray-500">Inaktiv</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Zeit seit Eingang --}}
                        <div class="p-4 bg-white border rounded-lg shadow-sm">
                            <div class="d-flex items-center gap-2 mb-2">
                                <x-heroicon-o-calendar class="w-4 h-4 text-primary"/>
                                <span class="font-medium text-sm">Zeit seit Eingang</span>
                            </div>
                            <div class="space-y-1">
                                <div class="text-2xl font-bold text-primary">
                                    {{ $ticket->created_at->diffForHumans() }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    Erstellt am {{ $ticket->created_at->format('d.m.Y H:i') }}
                                </div>
                            </div>
                        </div>

                        {{-- Restzeit --}}
                        <div class="p-4 bg-white border rounded-lg shadow-sm">
                            <div class="d-flex items-center gap-2 mb-2">
                                <x-heroicon-o-clock class="w-4 h-4 text-primary"/>
                                <span class="font-medium text-sm">Restzeit</span>
                            </div>
                            <div class="space-y-1">
                                @php
                                    $remainingTime = $ticket->sla->getRemainingTime($ticket);
                                    $isOverdue = $ticket->sla->isOverdue($ticket);
                                @endphp
                                
                                @if($remainingTime !== null)
                                    @if($isOverdue)
                                        <div class="text-2xl font-bold text-danger">
                                            <x-heroicon-o-exclamation-triangle class="w-5 h-5 inline"/>
                                            Überschritten
                                        </div>
                                        <div class="text-xs text-danger">
                                            {{ abs($remainingTime) }}h überfällig
                                        </div>
                                    @else
                                        <div class="text-2xl font-bold text-success">
                                            {{ $remainingTime }}h
                                        </div>
                                        <div class="text-xs text-success">
                                            verbleibend
                                        </div>
                                    @endif
                                @else
                                    <div class="text-2xl font-bold text-gray-400">
                                        –
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Keine Zeitvorgabe
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- SLA Zeitvorgaben --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if($ticket->sla->response_time_hours)
                            <div class="p-3 bg-muted-5 rounded-lg">
                                <div class="d-flex items-center gap-2 mb-1">
                                    <x-heroicon-o-chat-bubble-left class="w-4 h-4 text-muted"/>
                                    <span class="text-sm font-medium">Reaktionszeit</span>
                                </div>
                                <div class="text-sm">
                                    <span class="font-bold">{{ $ticket->sla->response_time_hours }} Stunden</span>
                                    @php
                                        $responseTime = $ticket->created_at->addHours($ticket->sla->response_time_hours);
                                        $isResponseOverdue = now()->isAfter($responseTime);
                                    @endphp
                                    @if($isResponseOverdue)
                                        <span class="text-danger">({{ $responseTime->diffForHumans() }} überschritten)</span>
                                    @else
                                        <span class="text-success">(bis {{ $responseTime->format('d.m.Y H:i') }})</span>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if($ticket->sla->resolution_time_hours && !$ticket->is_done)
                            <div class="p-3 bg-muted-5 rounded-lg">
                                <div class="d-flex items-center gap-2 mb-1">
                                    <x-heroicon-o-check-circle class="w-4 h-4 text-muted"/>
                                    <span class="text-sm font-medium">Lösungszeit</span>
                                </div>
                                <div class="text-sm">
                                    <span class="font-bold">{{ $ticket->sla->resolution_time_hours }} Stunden</span>
                                    @php
                                        $resolutionTime = $ticket->created_at->addHours($ticket->sla->resolution_time_hours);
                                        $isResolutionOverdue = now()->isAfter($resolutionTime);
                                    @endphp
                                    @if($isResolutionOverdue)
                                        <span class="text-danger">({{ $resolutionTime->diffForHumans() }} überschritten)</span>
                                    @else
                                        <span class="text-success">(bis {{ $resolutionTime->format('d.m.Y H:i') }})</span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Ticket Details --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4 text-secondary">Ticket Details</h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {{-- Linke Spalte: Titel & Beschreibung --}}
                    <div class="space-y-4">
                        @can('update', $ticket)
                            <x-ui-input-text 
                                name="ticket.title"
                                label="Ticket-Titel"
                                wire:model.live.debounce.500ms="ticket.title"
                                placeholder="Ticket-Titel eingeben..."
                                required
                                :errorKey="'ticket.title'"
                            />
                        @else
                            <div>
                                <label class="font-semibold">Ticket-Titel:</label>
                                <div class="p-3 bg-muted-5 rounded-lg">{{ $ticket->title }}</div>
                            </div>
                        @endcan

                        @can('update', $ticket)
                            <x-ui-input-textarea 
                                name="ticket.description"
                                label="Ticket Beschreibung"
                                wire:model.live.debounce.500ms="ticket.description"
                                placeholder="Ticket Beschreibung eingeben..."
                                rows="6"
                                :errorKey="'ticket.description'"
                            />
                        @else
                            <div>
                                <label class="font-semibold">Beschreibung:</label>
                                <div class="p-3 bg-muted-5 rounded-lg whitespace-pre-wrap">{{ $ticket->description ?: 'Keine Beschreibung vorhanden' }}</div>
                            </div>
                        @endcan
                    </div>

                    {{-- Rechte Spalte: Metadaten --}}
                    <div class="space-y-4">
                        {{-- Status & Priorität --}}
                        <div class="grid grid-cols-2 gap-4">
                            @can('update', $ticket)
                                <x-ui-input-select
                                    name="ticket.status"
                                    label="Status"
                                    :options="\Platform\Helpdesk\Enums\TicketStatus::cases()"
                                    optionValue="value"
                                    optionLabel="label"
                                    :nullable="true"
                                    nullLabel="– Kein Status –"
                                    wire:model.live="ticket.status"
                                />
                            @else
                                <div>
                                    <label class="font-semibold">Status:</label>
                                    <div class="p-2 bg-muted-5 rounded-lg">{{ $ticket->status?->label() ?? '–' }}</div>
                                </div>
                            @endcan

                            @can('update', $ticket)
                                <x-ui-input-select
                                    name="ticket.priority"
                                    label="Priorität"
                                    :options="\Platform\Helpdesk\Enums\TicketPriority::cases()"
                                    optionValue="value"
                                    optionLabel="label"
                                    :nullable="true"
                                    nullLabel="– Keine Priorität –"
                                    wire:model.live="ticket.priority"
                                />
                            @else
                                <div>
                                    <label class="font-semibold">Priorität:</label>
                                    <div class="p-2 bg-muted-5 rounded-lg">{{ $ticket->priority?->label() ?? '–' }}</div>
                                </div>
                            @endcan
                        </div>

                        {{-- Story Points & Fälligkeitsdatum --}}
                        <div class="grid grid-cols-2 gap-4">
                            @can('update', $ticket)
                                <x-ui-input-select
                                    name="ticket.story_points"
                                    label="Story Points"
                                    :options="\Platform\Helpdesk\Enums\TicketStoryPoints::cases()"
                                    optionValue="value"
                                    optionLabel="label"
                                    :nullable="true"
                                    nullLabel="– Kein Wert –"
                                    wire:model.live="ticket.story_points"
                                />
                            @else
                                <div>
                                    <label class="font-semibold">Story Points:</label>
                                    <div class="p-2 bg-muted-5 rounded-lg">{{ $ticket->story_points?->label() ?? '–' }}</div>
                                </div>
                            @endcan

                            @can('update', $ticket)
                                <x-ui-input-date
                                    name="ticket.due_date"
                                    label="Fälligkeitsdatum"
                                    wire:model.live="ticket.due_date"
                                    :nullable="true"
                                    :errorKey="'ticket.due_date'"
                                />
                            @else
                                <div>
                                    <label class="font-semibold">Fälligkeitsdatum:</label>
                                    <div class="p-2 bg-muted-5 rounded-lg">
                                        {{ $ticket->due_date ? $ticket->due_date->format('d.m.Y') : '–' }}
                                    </div>
                                </div>
                            @endcan
                        </div>

                        {{-- Zugewiesener Benutzer --}}
                        @can('update', $ticket)
                            <x-ui-input-select
                                name="ticket.user_in_charge_id"
                                label="Zugewiesen an"
                                :options="$teamUsers"
                                optionValue="id"
                                optionLabel="name"
                                :nullable="true"
                                nullLabel="– Niemand zugewiesen –"
                                wire:model.live="ticket.user_in_charge_id"
                            />
                        @else
                            <div>
                                <label class="font-semibold">Zugewiesen an:</label>
                                <div class="p-2 bg-muted-5 rounded-lg">
                                    {{ $ticket->userInCharge?->name ?? 'Niemand zugewiesen' }}
                                </div>
                            </div>
                        @endcan
                    </div>
                </div>
            </div>
        </div>

        <!-- Aktivitäten (immer unten) -->
        <div x-data="{ open: false }" class="flex-shrink-0 border-t border-muted">
            <div 
                @click="open = !open" 
                class="cursor-pointer border-top-1 border-top-solid border-top-muted border-bottom-1 border-bottom-solid border-bottom-muted p-2 text-center d-flex items-center justify-center gap-1 mx-2 shadow-lg"
            >
                AKTIVITÄTEN 
                <span class="text-xs">
                    {{$ticket->activities->count()}}
                </span>
                <x-heroicon-o-chevron-double-down 
                    class="w-3 h-3" 
                    x-show="!open"
                />
                <x-heroicon-o-chevron-double-up 
                    class="w-3 h-3" 
                    x-show="open"
                />
            </div>
            <div x-show="open" class="p-2 max-h-xs overflow-y-auto">
                <livewire:activity-log.index
                    :model="$ticket"
                    :key="get_class($ticket) . '_' . $ticket->id"
                />
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
                @if($ticket->helpdeskBoard)
                    @can('view', $ticket->helpdeskBoard)
                        <x-ui-button 
                            variant="secondary-outline" 
                            size="md" 
                            :href="route('helpdesk.boards.show', $ticket->helpdeskBoard)" 
                            wire:navigate
                            class="w-full d-flex"
                        >
                            <div class="d-flex items-center gap-2">
                                @svg('heroicon-o-arrow-left', 'w-4 h-4')
                                Board: {{ $ticket->helpdeskBoard?->name }}
                            </div>
                        </x-ui-button>
                    @else
                        <x-ui-button 
                            variant="secondary-outline" 
                            size="md" 
                            disabled="true"
                            title="Kein Zugriff auf das Board"
                            class="w-full d-flex"
                        >
                            <div class="d-flex items-center gap-2">
                                @svg('heroicon-o-arrow-left', 'w-4 h-4')
                                Board: {{ $ticket->helpdeskBoard?->name }}
                            </div>
                        </x-ui-button>
                    @endcan
                @endif
                <x-ui-button 
                    variant="secondary-outline" 
                    size="md" 
                    :href="route('helpdesk.my-tickets')" 
                    wire:navigate
                    class="w-full d-flex"
                >
                    <div class="d-flex items-center gap-2">
                        @svg('heroicon-o-arrow-left', 'w-4 h-4')
                        Meine Tickets
                    </div>
                </x-ui-button>
            </div>

            {{-- Quick Actions --}}
            <div class="mb-4">
                <h4 class="font-semibold mb-2 text-secondary">Quick Actions</h4>
                
                {{-- Erledigt-Checkbox --}}
                @can('update', $ticket)
                    <x-ui-input-checkbox
                        model="ticket.is_done"
                        checked-label="Erledigt"
                        unchecked-label="Als erledigt markieren"
                        size="md"
                        block="true"
                        variant="success"
                        :icon="@svg('heroicon-o-check-circle', 'w-4 h-4')->toHtml()"
                    />
                @else
                    <div class="mb-2">
                        <x-ui-badge variant="{{ $ticket->is_done ? 'success' : 'gray' }}">
                            @svg('heroicon-o-check-circle', 'w-4 h-4')
                            {{ $ticket->is_done ? 'Erledigt' : 'Offen' }}
                        </x-ui-badge>
                    </div>
                @endcan


            </div>

            <hr>

            {{-- Ticket Info --}}
            <div class="mb-4">
                <h4 class="font-semibold mb-2 text-secondary">Ticket Info</h4>
                <div class="space-y-2 text-sm">
                    <div class="d-flex justify-between">
                        <span class="text-gray-600">Erstellt:</span>
                        <span>{{ $ticket->created_at->format('d.m.Y H:i') }}</span>
                    </div>
                    <div class="d-flex justify-between">
                        <span class="text-gray-600">Aktualisiert:</span>
                        <span>{{ $ticket->updated_at->format('d.m.Y H:i') }}</span>
                    </div>
                    <div class="d-flex justify-between">
                        <span class="text-gray-600">Erstellt von:</span>
                        <span>{{ $ticket->user?->name ?? 'Unbekannt' }}</span>
                    </div>
                    @if($ticket->userInCharge)
                        <div class="d-flex justify-between">
                            <span class="text-gray-600">Zugewiesen an:</span>
                            <span>{{ $ticket->userInCharge->name }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <hr>

            {{-- Löschen-Buttons --}}
            @can('delete', $ticket)
                <div class="d-flex flex-col gap-2">
                    <x-ui-confirm-button 
                        action="deleteTicketAndReturnToDashboard" 
                        text="Zu Meinen Tickets" 
                        confirmText="Löschen?" 
                        variant="danger-outline"
                        :icon="@svg('heroicon-o-trash', 'w-4 h-4')->toHtml()"
                    />
                    
                    @if($ticket->helpdeskBoard)
                        <x-ui-confirm-button 
                            action="deleteTicketAndReturnToBoard" 
                            text="Zum Board" 
                            confirmText="Löschen?" 
                            variant="danger-outline"
                            :icon="@svg('heroicon-o-trash', 'w-4 h-4')->toHtml()"
                        />
                    @endif
                </div>
            @endcan
        </div>
        <!-- Print Modal -->
        <x-ui-modal model="printModalShow" size="md">
            <x-slot name="header">
                Ticket drucken
            </x-slot>

            <div class="space-y-4">
                <!-- Auswahl-Typ -->
                <div class="space-y-2">
                    <label class="font-semibold text-sm">Druckziel wählen:</label>
                    <div class="d-flex gap-4">
                        <label class="d-flex items-center gap-2 cursor-pointer">
                            <input 
                                type="radio" 
                                name="printTarget" 
                                value="printer" 
                                wire:model.live="printTarget"
                                class="w-4 h-4"
                            >
                            <span class="text-sm">Einzelner Drucker</span>
                        </label>
                        <label class="d-flex items-center gap-2 cursor-pointer">
                            <input 
                                type="radio" 
                                name="printTarget" 
                                value="group" 
                                wire:model.live="printTarget"
                                class="w-4 h-4"
                            >
                            <span class="text-sm">Drucker-Gruppe</span>
                        </label>
                    </div>
                </div>

                <!-- Drucker-Auswahl -->
                @if($printTarget === 'printer')
                    <div class="space-y-2">
                        <label class="font-semibold text-sm">Drucker auswählen:</label>
                        @if($printers->count() > 0)
                            <div class="space-y-2 max-h-40 overflow-y-auto border rounded-lg p-2">
                                @foreach($printers as $printer)
                                    <label class="d-flex items-center gap-2 cursor-pointer p-2 hover:bg-muted-5 rounded">
                                        <input 
                                            type="radio" 
                                            name="selectedPrinterId" 
                                            value="{{ $printer->id }}" 
                                            wire:model.live="selectedPrinterId"
                                            class="w-4 h-4"
                                        >
                                        <span class="text-sm">{{ $printer->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <div class="text-sm text-muted p-3 bg-muted-5 rounded-lg">
                                Keine aktiven Drucker verfügbar
                            </div>
                        @endif
                    </div>
                @endif

                <!-- Gruppen-Auswahl -->
                @if($printTarget === 'group')
                    <div class="space-y-2">
                        <label class="font-semibold text-sm">Gruppe auswählen:</label>
                        @if($printerGroups->count() > 0)
                            <div class="space-y-2 max-h-40 overflow-y-auto border rounded-lg p-2">
                                @foreach($printerGroups as $group)
                                    <label class="d-flex items-center gap-2 cursor-pointer p-2 hover:bg-muted-5 rounded">
                                        <input 
                                            type="radio" 
                                            name="selectedPrinterGroupId" 
                                            value="{{ $group->id }}" 
                                            wire:model.live="selectedPrinterGroupId"
                                            class="w-4 h-4"
                                        >
                                        <span class="text-sm">{{ $group->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <div class="text-sm text-muted p-3 bg-muted-5 rounded-lg">
                                Keine aktiven Drucker-Gruppen verfügbar
                            </div>
                        @endif
                    </div>
                @endif

                <!-- Info -->
                <div class="text-xs text-muted bg-muted-5 p-2 rounded">
                    <strong>Info:</strong> 
                    @if($printTarget === 'printer')
                        Das Ticket wird auf dem ausgewählten Drucker gedruckt.
                    @elseif($printTarget === 'group')
                        Das Ticket wird auf allen aktiven Druckern der Gruppe gedruckt.
                    @else
                        Wählen Sie einen Drucker oder eine Gruppe aus.
                    @endif
                </div>
            </div>

            <x-slot name="footer">
                <div class="d-flex justify-end gap-2">
                    <x-ui-button type="button" variant="secondary-outline" @click="$wire.closePrintModal()">
                        Abbrechen
                    </x-ui-button>
                    <x-ui-button 
                        type="button" 
                        variant="primary" 
                        wire:click="printTicketConfirm"
                        :disabled="!$this->canPrint"
                    >
                        Drucken
                    </x-ui-button>
                </div>
            </x-slot>
        </x-ui-modal>
    </div>
</div>
