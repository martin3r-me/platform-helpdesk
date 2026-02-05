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
                    <button
                        @click="$wire.set('activeTab', 'error-tracking')"
                        :class="$wire.activeTab === 'error-tracking' ? 'border-[var(--ui-primary)] text-[var(--ui-primary)]' : 'border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]'"
                        class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium transition-colors"
                        wire:click="$set('activeTab', 'error-tracking')"
                    >
                        Error Tracking
                    </button>
                    <button
                        @click="$wire.set('activeTab', 'channels')"
                        :class="$wire.activeTab === 'channels' ? 'border-[var(--ui-primary)] text-[var(--ui-primary)]' : 'border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]'"
                        class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium transition-colors"
                        wire:click="$set('activeTab', 'channels')"
                    >
                        Kanäle
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

            @elseif($activeTab === 'error-tracking' && $errorSettings)
            {{-- Error Tracking Einstellungen --}}
            <div class="space-y-6">
                {{-- Aktivierung --}}
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-[var(--ui-secondary)]">Error Tracking</h3>

                    <div class="p-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox"
                                   wire:model="errorSettings.enabled"
                                   class="w-5 h-5 text-[var(--ui-primary)] border-[var(--ui-border)] rounded focus:ring-[var(--ui-primary)]">
                            <div>
                                <span class="text-sm font-medium text-[var(--ui-secondary)]">Error Tracking aktivieren</span>
                                <p class="text-xs text-[var(--ui-muted)] mt-0.5">Systemfehler werden automatisch als Tickets erfasst</p>
                            </div>
                        </label>
                    </div>
                </div>

                {{-- HTTP Codes --}}
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-[var(--ui-secondary)]">HTTP-Codes erfassen</h3>
                    <p class="text-sm text-[var(--ui-muted)]">Wählen Sie die HTTP-Statuscodes, die als Fehler erfasst werden sollen.</p>

                    <div class="flex flex-wrap gap-2">
                        @foreach($availableHttpCodes as $code)
                            <button
                                type="button"
                                wire:click="toggleHttpCode({{ $code }})"
                                class="px-3 py-1.5 text-sm font-medium rounded-full border transition-colors
                                    {{ $this->isHttpCodeEnabled($code)
                                        ? 'bg-[var(--ui-primary)] text-white border-[var(--ui-primary)]'
                                        : 'bg-[var(--ui-surface)] text-[var(--ui-muted)] border-[var(--ui-border)] hover:border-[var(--ui-primary)] hover:text-[var(--ui-secondary)]' }}"
                            >
                                {{ $code }}
                                @if($code >= 500)
                                    <span class="ml-1 text-xs opacity-75">(Server)</span>
                                @elseif($code >= 400)
                                    <span class="ml-1 text-xs opacity-75">(Client)</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Deduplizierung --}}
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-[var(--ui-secondary)]">Deduplizierung</h3>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">
                                Dedupe-Fenster (Stunden)
                            </label>
                            <input type="number"
                                   wire:model="errorSettings.dedupe_window_hours"
                                   min="1"
                                   max="720"
                                   class="w-32 px-3 py-2 text-sm border border-[var(--ui-border)] rounded-md bg-[var(--ui-surface)] text-[var(--ui-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20 focus:border-[var(--ui-primary)]">
                            <p class="mt-1 text-xs text-[var(--ui-muted)]">Gleiche Fehler werden innerhalb dieses Zeitraums zusammengefasst</p>
                        </div>
                    </div>
                </div>

                {{-- Optionen --}}
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-[var(--ui-secondary)]">Optionen</h3>

                    <div class="space-y-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox"
                                   wire:model="errorSettings.capture_console_errors"
                                   class="w-4 h-4 text-[var(--ui-primary)] border-[var(--ui-border)] rounded focus:ring-[var(--ui-primary)]">
                            <span class="text-sm text-[var(--ui-secondary)]">Console-/Scheduler-Fehler erfassen</span>
                        </label>
                        <p class="text-xs text-[var(--ui-muted)] ml-6">Fehler aus Artisan-Commands und Scheduler-Jobs werden ebenfalls erfasst (ohne User-Kontext)</p>

                        <label class="flex items-center gap-2 cursor-pointer mt-4">
                            <input type="checkbox"
                                   wire:model="errorSettings.auto_create_ticket"
                                   class="w-4 h-4 text-[var(--ui-primary)] border-[var(--ui-border)] rounded focus:ring-[var(--ui-primary)]">
                            <span class="text-sm text-[var(--ui-secondary)]">Automatisch Tickets erstellen</span>
                        </label>
                        <p class="text-xs text-[var(--ui-muted)] ml-6">Bei neuen Fehlern wird automatisch ein Ticket angelegt</p>

                        <label class="flex items-center gap-2 cursor-pointer mt-4">
                            <input type="checkbox"
                                   wire:model="errorSettings.include_stack_trace"
                                   class="w-4 h-4 text-[var(--ui-primary)] border-[var(--ui-border)] rounded focus:ring-[var(--ui-primary)]">
                            <span class="text-sm text-[var(--ui-secondary)]">Stack Trace erfassen</span>
                        </label>
                        <p class="text-xs text-[var(--ui-muted)] ml-6">Stack Trace wird in den Fehlerdaten gespeichert</p>

                        @if($errorSettings->include_stack_trace)
                            <div class="ml-6 mt-2">
                                <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">
                                    Stack Trace Limit (Frames)
                                </label>
                                <input type="number"
                                       wire:model="errorSettings.stack_trace_limit"
                                       min="1"
                                       max="200"
                                       class="w-24 px-3 py-2 text-sm border border-[var(--ui-border)] rounded-md bg-[var(--ui-surface)] text-[var(--ui-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20 focus:border-[var(--ui-primary)]">
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            @elseif($activeTab === 'channels')
            {{-- Kanäle --}}
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-[var(--ui-secondary)]">Verknüpfte Kanäle</h3>
                </div>

                <p class="text-sm text-[var(--ui-muted)]">
                    Eingehende E-Mails auf verknüpften Kanälen erstellen automatisch ein Ticket in diesem Board.
                </p>

                <div class="space-y-2">
                    @forelse($availableChannels as $channel)
                        @php
                            $isLinked = in_array($channel['id'], $linkedChannelIds);
                        @endphp
                        <div class="flex items-center justify-between p-3 bg-[var(--ui-surface)] border border-[var(--ui-border)]/40 rounded-lg">
                            <div class="flex items-center gap-3 flex-1 min-w-0">
                                <div class="w-3 h-3 rounded-full flex-shrink-0 {{ $isLinked ? 'bg-[var(--ui-success)]' : 'bg-[var(--ui-muted)]' }}"></div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $channel['sender_identifier'] }}</div>
                                    @if($channel['name'])
                                        <div class="text-xs text-[var(--ui-muted)] mt-0.5">{{ $channel['name'] }}</div>
                                    @endif
                                </div>
                            </div>
                            <button
                                wire:click="toggleChannel({{ $channel['id'] }})"
                                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20
                                    {{ $isLinked ? 'bg-[var(--ui-primary)]' : 'bg-[var(--ui-muted-5)]' }}"
                            >
                                <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out
                                    {{ $isLinked ? 'translate-x-5' : 'translate-x-0' }}"></span>
                            </button>
                        </div>
                    @empty
                        <div class="text-center py-8 text-[var(--ui-muted)]">
                            <p class="text-sm">Keine E-Mail-Kanäle verfügbar</p>
                            <p class="text-xs mt-1">Erstellen Sie zuerst einen E-Mail-Kanal in den Kommunikations-Einstellungen.</p>
                        </div>
                    @endforelse
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
