<x-ui-modal size="lg" model="modalShow">
    <x-slot name="header">
        Board-Einstellungen: {{ $board->name ?? '' }}
    </x-slot>

    @if($board)
        <div class="flex-grow-1 overflow-y-auto">
            {{-- Tabs --}}
            <div class="border-b border-[var(--ui-border)]/40 mb-6 px-4 pt-4">
                <nav class="-mb-px flex space-x-6" aria-label="Tabs">
                    <button
                        @click="$wire.set('activeTab', 'general')"
                        :class="$wire.activeTab === 'general' ? 'border-[var(--ui-primary)] text-[var(--ui-primary)]' : 'border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]'"
                        class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium transition-colors"
                        wire:click="$set('activeTab', 'general')"
                    >
                        Allgemein
                    </button>
                    <button
                        @click="$wire.set('activeTab', 'ai')"
                        :class="$wire.activeTab === 'ai' ? 'border-[var(--ui-primary)] text-[var(--ui-primary)]' : 'border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]'"
                        class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium transition-colors"
                        wire:click="$set('activeTab', 'ai')"
                    >
                        KI-Einstellungen
                    </button>
                    <button
                        @click="$wire.set('activeTab', 'service-hours')"
                        :class="$wire.activeTab === 'service-hours' ? 'border-[var(--ui-primary)] text-[var(--ui-primary)]' : 'border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]'"
                        class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium transition-colors"
                        wire:click="$set('activeTab', 'service-hours')"
                    >
                        Service Hours
                    </button>
                    <button
                        @click="$wire.set('activeTab', 'sla')"
                        :class="$wire.activeTab === 'sla' ? 'border-[var(--ui-primary)] text-[var(--ui-primary)]' : 'border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]'"
                        class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium transition-colors"
                        wire:click="$set('activeTab', 'sla')"
                    >
                        SLA
                    </button>
                </nav>
            </div>

            <div class="p-4 space-y-6">
            @if($activeTab === 'general')
            {{-- Board Grunddaten --}}
            <div class="space-y-4">
                <h3 class="text-lg font-medium text-[var(--ui-secondary)]">Grunddaten</h3>
                
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-[var(--ui-secondary)]">Board Name</label>
                        <input type="text" wire:model="board.name"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                               placeholder="z. B. IT Support, Buchhaltung">
                        @error('board.name') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-[var(--ui-secondary)]">Beschreibung</label>
                        <textarea wire:model="board.description" rows="3"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Beschreibung des Helpdesk Boards..."></textarea>
                        @error('board.description') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            @elseif($activeTab === 'ai' && $aiSettings)
            {{-- KI-Einstellungen --}}
            <div class="space-y-6">
                {{-- Allgemeine KI-Einstellungen --}}
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-[var(--ui-secondary)]">Allgemein</h3>
                    
                    <div class="space-y-3">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="aiSettings.auto_response_enabled" class="rounded border-gray-300">
                            <span class="text-sm text-[var(--ui-secondary)]">KI-Auto-Response aktivieren</span>
                        </label>
                        
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">AI-Modell</label>
                            <select wire:model="aiSettings.ai_model" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                @foreach($availableModels as $model)
                                    <option value="{{ $model }}">{{ $model }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Auto-Response Einstellungen --}}
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-[var(--ui-secondary)]">Auto-Response</h3>
                    
                    <div class="space-y-3">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="aiSettings.auto_response_immediate_enabled" class="rounded border-gray-300">
                            <span class="text-sm text-[var(--ui-secondary)]">Sofortige Bestätigung aktivieren</span>
                        </label>
                        
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">
                                Timing (Minuten)
                            </label>
                            <input type="number" wire:model="aiSettings.auto_response_timing_minutes" min="1" max="1440"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <p class="mt-1 text-xs text-[var(--ui-muted)]">Zeit bis zur vollständigen KI-Antwort (Standard: 30 Minuten)</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">
                                Confidence-Threshold ({{ number_format($aiSettings->auto_response_confidence_threshold * 100, 0) }}%)
                            </label>
                            <input type="range" wire:model.live="aiSettings.auto_response_confidence_threshold" min="0" max="1" step="0.01"
                                   class="w-full">
                            <p class="mt-1 text-xs text-[var(--ui-muted)]">Mindest-Confidence für automatisches Senden</p>
                        </div>
                    </div>
                </div>

                {{-- Auto-Assignment --}}
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-[var(--ui-secondary)]">Auto-Assignment</h3>
                    
                    <div class="space-y-3">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="aiSettings.auto_assignment_enabled" class="rounded border-gray-300">
                            <span class="text-sm text-[var(--ui-secondary)]">Automatische Zuweisung aktivieren</span>
                        </label>
                        
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">
                                Confidence-Threshold ({{ number_format($aiSettings->auto_assignment_confidence_threshold * 100, 0) }}%)
                            </label>
                            <input type="range" wire:model.live="aiSettings.auto_assignment_confidence_threshold" min="0" max="1" step="0.01"
                                   class="w-full">
                            <p class="mt-1 text-xs text-[var(--ui-muted)]">Mindest-Confidence für automatische Zuweisung</p>
                        </div>
                    </div>
                </div>

                {{-- Human-in-the-Loop --}}
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-[var(--ui-secondary)]">Human-in-the-Loop</h3>
                    
                    <div class="space-y-3">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="aiSettings.human_in_loop_enabled" class="rounded border-gray-300">
                            <span class="text-sm text-[var(--ui-secondary)]">Human-in-the-Loop aktivieren</span>
                        </label>
                        
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">
                                Threshold ({{ number_format($aiSettings->human_in_loop_threshold * 100, 0) }}%)
                            </label>
                            <input type="range" wire:model.live="aiSettings.human_in_loop_threshold" min="0" max="1" step="0.01"
                                   class="w-full">
                            <p class="mt-1 text-xs text-[var(--ui-muted)]">Unter diesem Threshold wird Review benötigt</p>
                        </div>
                    </div>
                </div>

                {{-- Eskalationen --}}
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-[var(--ui-secondary)]">Eskalationen</h3>
                    
                    <div class="space-y-3">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="aiSettings.ai_enabled_for_escalated" class="rounded border-gray-300">
                            <span class="text-sm text-[var(--ui-secondary)]">KI für eskalierten Tickets aktivieren</span>
                        </label>
                        <p class="text-xs text-[var(--ui-muted)]">Standardmäßig pausiert die KI bei eskalierten Tickets</p>
                    </div>
                </div>
            </div>

            @elseif($activeTab === 'service-hours')
            {{-- Service-Zeiten --}}
            <div class="space-y-4">
                <div class="d-flex items-center justify-between">
                    <h3 class="text-lg font-medium text-[var(--ui-secondary)]">Service Hours</h3>
                    <x-ui-button variant="primary-outline" size="sm" wire:click="toggleServiceHoursForm">
                        {{ $showServiceHoursForm ? 'Abbrechen' : '+ Service Hours hinzufügen' }}
                    </x-ui-button>
                </div>

                @if($showServiceHoursForm)
                    <div class="bg-[var(--ui-muted-5)] p-4 rounded-lg space-y-4 border border-[var(--ui-border)]/60">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-[var(--ui-secondary)]">Name</label>
                                <input type="text" wire:model="newServiceZeit.name"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="z. B. Mo-Fr 9-17 Uhr">
                                @error('newServiceZeit.name') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-[var(--ui-secondary)]">Beschreibung</label>
                                <input type="text" wire:model="newServiceZeit.description"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Optionale Beschreibung">
                            </div>
                        </div>

                        <div class="d-flex items-center gap-4">
                            <label class="d-flex items-center">
                                <input type="checkbox" wire:model="newServiceZeit.is_active" class="rounded border-gray-300">
                                <span class="ml-2 text-sm text-gray-700">Aktiv</span>
                            </label>
                            <label class="d-flex items-center">
                                <input type="checkbox" wire:model="newServiceZeit.use_auto_messages" class="rounded border-gray-300">
                                <span class="ml-2 text-sm text-gray-700">Auto-Nachrichten verwenden</span>
                            </label>
                        </div>

                        {{-- Service Hours Zeitplan --}}
                        <div class="space-y-3">
                            <h4 class="text-sm font-medium text-[var(--ui-secondary)]">Öffnungszeiten</h4>
                            <div class="space-y-2">
                                @foreach(['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'] as $index => $dayName)
                                    @php
                                        $dayIndex = $index === 6 ? 0 : $index + 1; // Sonntag = 0
                                    @endphp
                                    <div class="d-flex items-center justify-between p-3 bg-[var(--ui-surface)] rounded border border-[var(--ui-border)]/60">
                                        <div class="d-flex items-center gap-3">
                                            <label class="d-flex items-center">
                                                <input type="checkbox" 
                                                       wire:model="newServiceZeit.service_hours.{{ $index }}.enabled" 
                                                       class="rounded border-gray-300">
                                                <span class="ml-2 text-sm font-medium w-20">{{ $dayName }}</span>
                                            </label>
                                        </div>
                                        <div class="d-flex items-center gap-2">
                                            <input type="time" 
                                                   wire:model="newServiceZeit.service_hours.{{ $index }}.start"
                                                   class="border-gray-300 rounded text-sm px-2 py-1">
                                            <span class="text-sm text-gray-500">bis</span>
                                            <input type="time" 
                                                   wire:model="newServiceZeit.service_hours.{{ $index }}.end"
                                                   class="border-gray-300 rounded text-sm px-2 py-1">
                                        </div>
                                        <input type="hidden" 
                                               wire:model="newServiceZeit.service_hours.{{ $index }}.day" 
                                               value="{{ $dayIndex }}">
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        @if($newServiceZeit['use_auto_messages'])
                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Nachricht während Service-Zeit</label>
                                    <textarea wire:model="newServiceZeit.auto_message_inside" rows="2"
                                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                              placeholder="z. B. Vielen Dank für Ihr Ticket. Wir bearbeiten es innerhalb der nächsten 2 Stunden."></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Nachricht außerhalb Service-Zeit</label>
                                    <textarea wire:model="newServiceZeit.auto_message_outside" rows="2"
                                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                              placeholder="z. B. Vielen Dank für Ihr Ticket. Wir bearbeiten es am nächsten Werktag."></textarea>
                                </div>
                            </div>
                        @endif

                        <div class="d-flex justify-end">
                            <x-ui-button variant="success" size="sm" wire:click="addServiceHours">
                                Service Hours hinzufügen
                            </x-ui-button>
                        </div>
                    </div>
                @endif

                {{-- Bestehende Service Hours --}}
                <div class="space-y-2">
                    @forelse($serviceHours as $serviceHour)
                        <div class="d-flex items-center justify-between p-3 bg-white border rounded-lg">
                            <div class="d-flex items-center gap-3">
                                <div class="w-3 h-3 rounded-full {{ $serviceHour->is_active ? 'bg-green-500' : 'bg-gray-300' }}"></div>
                                <div class="flex-grow">
                                    <div class="font-medium">{{ $serviceHour->name }}</div>
                                    @if($serviceHour->description)
                                        <div class="text-sm text-gray-500">{{ $serviceHour->description }}</div>
                                    @endif
                                    <div class="text-xs text-gray-600 mt-1">
                                        {{ $serviceHour->getFormattedSchedule() }}
                                    </div>
                                    @if($serviceHour->use_auto_messages)
                                        <div class="text-xs text-blue-600">Auto-Nachrichten aktiv</div>
                                    @endif
                                </div>
                            </div>
                            <button wire:click="deleteServiceHours({{ $serviceHour->id }})" 
                                    class="text-red-500 hover:text-red-700" title="Löschen">
                                <x-heroicon-o-trash class="w-4 h-4"/>
                            </button>
                        </div>
                    @empty
                        <div class="text-center py-4 text-gray-500">
                            Noch keine Service Hours definiert
                        </div>
                    @endforelse
                </div>
            </div>

            @elseif($activeTab === 'sla')
            {{-- SLA-Auswahl --}}
            <div class="space-y-4">
                <div class="d-flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900">Service Level Agreement (SLA)</h3>
                    <x-ui-button variant="primary-outline" size="sm" :href="route('helpdesk.slas.index')" wire:navigate>
                        SLAs verwalten
                    </x-ui-button>
                </div>
                
                <div class="space-y-2">
                    @if($board->sla)
                        <div class="d-flex items-center justify-between p-3 bg-white border rounded-lg">
                            <div class="flex-grow">
                                <div class="font-medium">{{ $board->sla->name }}</div>
                                @if($board->sla->description)
                                    <div class="text-sm text-gray-500">{{ $board->sla->description }}</div>
                                @endif
                                <div class="text-xs text-gray-600 mt-1">
                                    @if($board->sla->response_time_hours)
                                        Reaktion: {{ $board->sla->response_time_hours }}h
                                    @endif
                                    @if($board->sla->resolution_time_hours)
                                        @if($board->sla->response_time_hours) | @endif
                                        Lösung: {{ $board->sla->resolution_time_hours }}h
                                    @endif
                                </div>
                            </div>
                            <div class="d-flex items-center gap-2">
                                @if($board->sla->is_active)
                                    <x-ui-badge variant="success" size="xs">Aktiv</x-ui-badge>
                                @else
                                    <x-ui-badge variant="secondary" size="xs">Inaktiv</x-ui-badge>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="text-center py-4 text-gray-500">
                            Kein SLA zugewiesen
                        </div>
                    @endif
                    
                    <x-ui-input-select
                        name="board.helpdesk_board_sla_id"
                        label="SLA auswählen"
                        :options="$availableSlas"
                        optionValue="id"
                        optionLabel="name"
                        :nullable="true"
                        nullLabel="– SLA auswählen –"
                        wire:model.live="board.helpdesk_board_sla_id"
                    />
                </div>
            </div>
            @endif

            <hr>

            {{-- Löschen Button --}}
            <div class="d-flex justify-end">
                <x-ui-confirm-button action="deleteBoard" text="Board löschen" confirmText="Wirklich löschen?" />
            </div>
            </div>
        </div>
    @endif

    <x-slot name="footer">
        <x-ui-button variant="success" wire:click="save">Speichern</x-ui-button>
    </x-slot>
</x-ui-modal>
