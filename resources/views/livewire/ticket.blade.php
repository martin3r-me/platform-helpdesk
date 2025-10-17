<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-ui-page-container spacing="space-y-8">
        <div class="bg-white rounded-lg border border-[var(--ui-border)]/60 p-8">
            <div class="flex items-start justify-between">
                <div class="flex-1 min-w-0">
                    <h1 class="text-3xl font-bold text-[var(--ui-secondary)] mb-4 tracking-tight">{{ $ticket->title }}</h1>
                    <div class="flex items-center gap-6 text-sm text-[var(--ui-muted)]">
                        @if($ticket->helpdeskBoard)
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-rectangle-stack', 'w-4 h-4')
                                {{ $ticket->helpdeskBoard->name }}
                            </span>
                        @endif
                        @if($ticket->userInCharge)
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-user', 'w-4 h-4')
                                {{ $ticket->userInCharge->name }}
                            </span>
                        @endif
                        @if($ticket->due_date)
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-calendar', 'w-4 h-4')
                                {{ $ticket->due_date->format('d.m.Y H:i') }}
                            </span>
                        @endif
                        @if($ticket->story_points)
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-sparkles', 'w-4 h-4')
                                {{ $ticket->story_points->points() ?? $ticket->story_points }} SP
                            </span>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    @if($ticket->is_done)
                        <x-ui-badge variant="success" size="lg">Erledigt</x-ui-badge>
                    @endif
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-[var(--ui-border)]/60 p-8">
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
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                {{-- Navigation --}}
                <div class="space-y-2">
                    @if($ticket->helpdeskBoard)
                        <x-ui-button variant="secondary-outline" size="sm" :href="route('helpdesk.boards.show', $ticket->helpdeskBoard)" wire:navigate class="w-full">
                            <span class="flex items-center gap-2">@svg('heroicon-o-rectangle-stack','w-4 h-4') Zum Board</span>
                        </x-ui-button>
                    @endif
                    <x-ui-button variant="secondary-outline" size="sm" :href="route('helpdesk.my-tickets')" wire:navigate class="w-full">
                        <span class="flex items-center gap-2">@svg('heroicon-o-clipboard-document-list','w-4 h-4') Zu meinen Tickets</span>
                    </x-ui-button>
                </div>

            {{-- Navigation Buttons --}}
                {{-- Status --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Status</h3>
                    <div class="space-y-3">
                        @can('update', $ticket)
                            <button type="button" wire:click="$set('ticket.is_done', !($ticket->is_done))"
                                class="w-full flex items-center justify-between py-3 px-4 rounded-lg border transition-colors"
                                :class="{
                                    'border-[var(--ui-success)] bg-[var(--ui-success-5)] hover:bg-[var(--ui-success-10)]': {{ $ticket->is_done ? 'true' : 'false' }},
                                    'border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)] hover:bg-[var(--ui-primary-5)]': !({{ $ticket->is_done ? 'true' : 'false' }})
                                }">
                                <span class="text-sm font-medium" :class="{ 'text-[var(--ui-success)]': {{ $ticket->is_done ? 'true' : 'false' }}, 'text-[var(--ui-secondary)]': !({{ $ticket->is_done ? 'true' : 'false' }}) }">Erledigt</span>
                                @if($ticket->is_done)
                                    @svg('heroicon-o-check-circle', 'w-5 h-5 text-[var(--ui-success)]')
                                @else
                                    @svg('heroicon-o-circle-stack', 'w-5 h-5 text-[var(--ui-muted)]')
                                @endif
                            </button>
                        @else
                            <x-ui-badge variant="{{ $ticket->is_done ? 'success' : 'secondary' }}" size="sm">{{ $ticket->is_done ? 'Erledigt' : 'Offen' }}</x-ui-badge>
                        @endcan
                    </div>
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

            {{-- Aktionen --}}
            <div class="space-y-2">
                <x-ui-button variant="secondary-outline" size="sm" wire:click="printTicket" class="w-full">
                    <span class="inline-flex items-center gap-2">@svg('heroicon-o-printer','w-4 h-4') Drucken</span>
                </x-ui-button>
                @can('delete', $ticket)
                    <x-ui-confirm-button action="deleteTicketAndReturnToDashboard" text="Löschen" confirmText="Ticket wirklich löschen?" variant="danger" :icon="@svg('heroicon-o-trash','w-4 h-4')->toHtml()" />
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
                    >
                        Drucken
                    </x-ui-button>
                </div>
            </x-slot>
            </x-ui-modal>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 space-y-4">
                <div class="text-sm text-[var(--ui-muted)]">Letzte Aktivitäten</div>
                <div class="space-y-3 text-sm">
                    <livewire:activity-log.index :model="$ticket" :key="get_class($ticket) . '_' . $ticket->id" />
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
