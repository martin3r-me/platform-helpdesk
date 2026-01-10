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
                
                <div class="space-y-4">
                    <x-ui-input-text
                        name="board.name"
                        label="Board Name"
                        wire:model="board.name"
                        placeholder="z. B. IT Support, Buchhaltung"
                        required
                        :errorKey="'board.name'"
                    />
                    
                    <x-ui-input-textarea
                        name="board.description"
                        label="Beschreibung"
                        wire:model="board.description"
                        rows="3"
                        placeholder="Beschreibung des Helpdesk Boards..."
                        :errorKey="'board.description'"
                    />
                </div>
            </div>

            @elseif($activeTab === 'ai' && $aiSettings)
            {{-- KI-Einstellungen --}}
            <div class="space-y-6">
                {{-- Allgemeine KI-Einstellungen --}}
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-[var(--ui-secondary)]">Allgemein</h3>
                    
                    <div class="space-y-4">
                        <x-ui-input-select
                            name="aiSettings.ai_model"
                            label="AI-Modell"
                            :options="collect($availableModels)->map(fn($m) => ['value' => $m, 'label' => $m])"
                            optionValue="value"
                            optionLabel="label"
                            :nullable="false"
                            wire:model="aiSettings.ai_model"
                            :errorKey="'aiSettings.ai_model'"
                        />
                    </div>
                </div>

                {{-- Auto-Assignment --}}
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-[var(--ui-secondary)]">Auto-Assignment</h3>
                    
                    <div class="space-y-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" 
                                   wire:model="aiSettings.auto_assignment_enabled" 
                                   class="w-4 h-4 text-[var(--ui-primary)] border-[var(--ui-border)] rounded focus:ring-[var(--ui-primary)]">
                            <span class="text-sm text-[var(--ui-secondary)]">Automatische Zuweisung aktivieren</span>
                        </label>
                        
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">
                                Confidence-Threshold ({{ number_format($aiSettings->auto_assignment_confidence_threshold * 100, 0) }}%)
                            </label>
                            <input type="range" wire:model.live="aiSettings.auto_assignment_confidence_threshold" min="0" max="1" step="0.01"
                                   class="w-full h-2 bg-[var(--ui-muted-5)] rounded-lg appearance-none cursor-pointer">
                            <p class="mt-1 text-xs text-[var(--ui-muted)]">Mindest-Confidence für automatische Zuweisung</p>
                        </div>
                    </div>
                </div>

                {{-- Human-in-the-Loop --}}
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-[var(--ui-secondary)]">Human-in-the-Loop</h3>
                    
                    <div class="space-y-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" 
                                   wire:model="aiSettings.human_in_loop_enabled" 
                                   class="w-4 h-4 text-[var(--ui-primary)] border-[var(--ui-border)] rounded focus:ring-[var(--ui-primary)]">
                            <span class="text-sm text-[var(--ui-secondary)]">Human-in-the-Loop aktivieren</span>
                        </label>
                        
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">
                                Threshold ({{ number_format($aiSettings->human_in_loop_threshold * 100, 0) }}%)
                            </label>
                            <input type="range" wire:model.live="aiSettings.human_in_loop_threshold" min="0" max="1" step="0.01"
                                   class="w-full h-2 bg-[var(--ui-muted-5)] rounded-lg appearance-none cursor-pointer">
                            <p class="mt-1 text-xs text-[var(--ui-muted)]">Unter diesem Threshold wird Review benötigt</p>
                        </div>
                    </div>
                </div>

                {{-- Eskalationen --}}
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-[var(--ui-secondary)]">Eskalationen</h3>
                    
                    <div class="space-y-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" 
                                   wire:model="aiSettings.ai_enabled_for_escalated" 
                                   class="w-4 h-4 text-[var(--ui-primary)] border-[var(--ui-border)] rounded focus:ring-[var(--ui-primary)]">
                            <span class="text-sm text-[var(--ui-secondary)]">KI für eskalierten Tickets aktivieren</span>
                        </label>
                        <p class="text-xs text-[var(--ui-muted)]">Standardmäßig pausiert die KI bei eskalierten Tickets</p>
                    </div>
                </div>
            </div>

            @elseif($activeTab === 'service-hours')
            {{-- Service-Zeiten --}}
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-[var(--ui-secondary)]">Service Hours</h3>
                    <x-ui-button variant="primary-outline" size="sm" wire:click="toggleServiceHoursForm">
                        {{ $showServiceHoursForm ? 'Abbrechen' : '+ Service Hours hinzufügen' }}
                    </x-ui-button>
                </div>

                @if($showServiceHoursForm)
                    <div class="bg-[var(--ui-muted-5)] p-4 rounded-lg space-y-4 border border-[var(--ui-border)]/60">
                        <x-ui-form-grid :cols="2" :gap="4">
                            <x-ui-input-text
                                name="newServiceZeit.name"
                                label="Name"
                                wire:model="newServiceZeit.name"
                                placeholder="z. B. Mo-Fr 9-17 Uhr"
                                required
                                :errorKey="'newServiceZeit.name'"
                            />
                            
                            <x-ui-input-text
                                name="newServiceZeit.description"
                                label="Beschreibung"
                                wire:model="newServiceZeit.description"
                                placeholder="Optionale Beschreibung"
                                :errorKey="'newServiceZeit.description'"
                            />
                        </x-ui-form-grid>

                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" 
                                       wire:model="newServiceZeit.is_active" 
                                       class="w-4 h-4 text-[var(--ui-primary)] border-[var(--ui-border)] rounded focus:ring-[var(--ui-primary)]">
                                <span class="text-sm text-[var(--ui-secondary)]">Aktiv</span>
                            </label>
                            
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" 
                                       wire:model="newServiceZeit.use_auto_messages" 
                                       class="w-4 h-4 text-[var(--ui-primary)] border-[var(--ui-border)] rounded focus:ring-[var(--ui-primary)]">
                                <span class="text-sm text-[var(--ui-secondary)]">Auto-Nachrichten verwenden</span>
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
                                    <div class="flex items-center justify-between p-3 bg-[var(--ui-surface)] border border-[var(--ui-border)]/40">
                                        <div class="flex items-center gap-3 flex-1">
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox" 
                                                       wire:model="newServiceZeit.service_hours.{{ $index }}.enabled" 
                                                       class="w-4 h-4 text-[var(--ui-primary)] border-[var(--ui-border)] rounded focus:ring-[var(--ui-primary)]">
                                                <span class="text-sm font-medium text-[var(--ui-secondary)] w-24">{{ $dayName }}</span>
                                            </label>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input type="time" 
                                                   wire:model="newServiceZeit.service_hours.{{ $index }}.start"
                                                   class="px-3 py-1.5 text-sm border border-[var(--ui-border)] rounded-md bg-[var(--ui-surface)] text-[var(--ui-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20 focus:border-[var(--ui-primary)]">
                                            <span class="text-sm text-[var(--ui-muted)]">bis</span>
                                            <input type="time" 
                                                   wire:model="newServiceZeit.service_hours.{{ $index }}.end"
                                                   class="px-3 py-1.5 text-sm border border-[var(--ui-border)] rounded-md bg-[var(--ui-surface)] text-[var(--ui-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20 focus:border-[var(--ui-primary)]">
                                        </div>
                                        <input type="hidden" 
                                               wire:model="newServiceZeit.service_hours.{{ $index }}.day" 
                                               value="{{ $dayIndex }}">
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        @if($newServiceZeit['use_auto_messages'])
                            <div class="space-y-4">
                                <x-ui-input-textarea
                                    name="newServiceZeit.auto_message_inside"
                                    label="Nachricht während Service-Zeit"
                                    wire:model="newServiceZeit.auto_message_inside"
                                    rows="2"
                                    placeholder="z. B. Vielen Dank für Ihr Ticket. Wir bearbeiten es innerhalb der nächsten 2 Stunden."
                                    :errorKey="'newServiceZeit.auto_message_inside'"
                                />
                                
                                <x-ui-input-textarea
                                    name="newServiceZeit.auto_message_outside"
                                    label="Nachricht außerhalb Service-Zeit"
                                    wire:model="newServiceZeit.auto_message_outside"
                                    rows="2"
                                    placeholder="z. B. Vielen Dank für Ihr Ticket. Wir bearbeiten es am nächsten Werktag."
                                    :errorKey="'newServiceZeit.auto_message_outside'"
                                />
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
                        <div class="flex items-center justify-between p-3 bg-[var(--ui-surface)] border border-[var(--ui-border)]/40">
                            <div class="flex items-center gap-3 flex-1 min-w-0">
                                <div class="w-3 h-3 rounded-full flex-shrink-0 {{ $serviceHour->is_active ? 'bg-[var(--ui-success)]' : 'bg-[var(--ui-muted)]' }}"></div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $serviceHour->name }}</div>
                                    @if($serviceHour->description)
                                        <div class="text-xs text-[var(--ui-muted)] mt-0.5">{{ $serviceHour->description }}</div>
                                    @endif
                                    <div class="text-xs text-[var(--ui-muted)] mt-1">
                                        {{ $serviceHour->getFormattedSchedule() }}
                                    </div>
                                    @if($serviceHour->use_auto_messages)
                                        <div class="text-xs text-[var(--ui-primary)] mt-1">Auto-Nachrichten aktiv</div>
                                    @endif
                                </div>
                            </div>
                            <button wire:click="deleteServiceHours({{ $serviceHour->id }})" 
                                    class="text-[var(--ui-danger)] hover:text-[var(--ui-danger)]/80 transition-colors flex-shrink-0 ml-3"
                                    title="Löschen">
                                @svg('heroicon-o-trash', 'w-4 h-4')
                            </button>
                        </div>
                    @empty
                        <div class="text-center py-8 text-[var(--ui-muted)]">
                            <p class="text-sm">Noch keine Service Hours definiert</p>
                        </div>
                    @endforelse
                </div>
            </div>

            @elseif($activeTab === 'sla')
            {{-- SLA-Auswahl --}}
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-[var(--ui-secondary)]">Service Level Agreement (SLA)</h3>
                    <x-ui-button variant="primary-outline" size="sm" :href="route('helpdesk.slas.index')" wire:navigate>
                        SLAs verwalten
                    </x-ui-button>
                </div>
                
                <div class="space-y-4">
                    @if($board->sla)
                        <div class="flex items-center justify-between p-3 bg-[var(--ui-surface)] border border-[var(--ui-border)]/40">
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $board->sla->name }}</div>
                                @if($board->sla->description)
                                    <div class="text-xs text-[var(--ui-muted)] mt-0.5">{{ $board->sla->description }}</div>
                                @endif
                                <div class="text-xs text-[var(--ui-muted)] mt-1">
                                    @if($board->sla->response_time_hours)
                                        Reaktion: {{ $board->sla->response_time_hours }}h
                                    @endif
                                    @if($board->sla->resolution_time_hours)
                                        @if($board->sla->response_time_hours) | @endif
                                        Lösung: {{ $board->sla->resolution_time_hours }}h
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0 ml-3">
                                @if($board->sla->is_active)
                                    <x-ui-badge variant="success" size="xs">Aktiv</x-ui-badge>
                                @else
                                    <x-ui-badge variant="secondary" size="xs">Inaktiv</x-ui-badge>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="text-center py-8 text-[var(--ui-muted)]">
                            <p class="text-sm">Kein SLA zugewiesen</p>
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

            <hr class="border-[var(--ui-border)]/40">

            {{-- Löschen Button --}}
            <div class="flex justify-end">
                <x-ui-confirm-button action="deleteBoard" text="Board löschen" confirmText="Wirklich löschen?" />
            </div>
            </div>
        </div>
    @endif

    <x-slot name="footer">
        <x-ui-button variant="success" wire:click="save">Speichern</x-ui-button>
    </x-slot>
</x-ui-modal>
