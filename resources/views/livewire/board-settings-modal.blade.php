<x-ui-modal size="lg" model="modalShow">
    <x-slot name="header">
        Board-Einstellungen: {{ $board->name ?? '' }}
    </x-slot>

    @if($board)
        <div class="flex-grow-1 overflow-y-auto">
            {{-- Tabs --}}
            <div class="border-b border-gray-200 mb-6 px-4 pt-4">
                <nav class="-mb-px flex space-x-6" aria-label="Tabs">
                    <button
                        @click="$wire.set('activeTab', 'general')"
                        :class="$wire.activeTab === 'general' ? 'border-[#049b5c] text-[#049b5c]' : 'border-transparent text-gray-400 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap border-b-2 py-4 px-1 text-[13px] font-medium transition-colors"
                        wire:click="$set('activeTab', 'general')"
                    >
                        Allgemein
                    </button>
                    <button
                        @click="$wire.set('activeTab', 'service-hours')"
                        :class="$wire.activeTab === 'service-hours' ? 'border-[#049b5c] text-[#049b5c]' : 'border-transparent text-gray-400 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap border-b-2 py-4 px-1 text-[13px] font-medium transition-colors"
                        wire:click="$set('activeTab', 'service-hours')"
                    >
                        Service Hours
                    </button>
                    <button
                        @click="$wire.set('activeTab', 'sla')"
                        :class="$wire.activeTab === 'sla' ? 'border-[#049b5c] text-[#049b5c]' : 'border-transparent text-gray-400 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap border-b-2 py-4 px-1 text-[13px] font-medium transition-colors"
                        wire:click="$set('activeTab', 'sla')"
                    >
                        SLA
                    </button>
                    <button
                        @click="$wire.set('activeTab', 'error-tracking')"
                        :class="$wire.activeTab === 'error-tracking' ? 'border-[#049b5c] text-[#049b5c]' : 'border-transparent text-gray-400 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap border-b-2 py-4 px-1 text-[13px] font-medium transition-colors"
                        wire:click="$set('activeTab', 'error-tracking')"
                    >
                        Error Tracking
                    </button>
                    <button
                        @click="$wire.set('activeTab', 'channels')"
                        :class="$wire.activeTab === 'channels' ? 'border-[#049b5c] text-[#049b5c]' : 'border-transparent text-gray-400 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap border-b-2 py-4 px-1 text-[13px] font-medium transition-colors"
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
                <h3 class="text-sm font-semibold text-gray-900">Grunddaten</h3>

                <div class="space-y-4">
                    <div>
                        <label for="board.name" class="block text-[11px] font-medium text-gray-500 mb-1">Board Name</label>
                        <input type="text" id="board.name" name="board.name"
                            wire:model="board.name"
                            placeholder="z. B. IT Support, Buchhaltung"
                            required
                            class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c] transition-colors"
                        >
                        @error('board.name')
                            <span class="text-xs text-red-600 mt-1">{{ $message }}</span>
                        @enderror
                    </div>

                    <div>
                        <label for="board.description" class="block text-[11px] font-medium text-gray-500 mb-1">Beschreibung</label>
                        <textarea id="board.description" name="board.description"
                            wire:model="board.description"
                            rows="3"
                            placeholder="Beschreibung des Helpdesk Boards..."
                            class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c] transition-colors"
                        ></textarea>
                        @error('board.description')
                            <span class="text-xs text-red-600 mt-1">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>

            @elseif($activeTab === 'service-hours')
            {{-- Service-Zeiten --}}
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">Service Hours</h3>
                    <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-[#049b5c] text-[#049b5c] text-[13px] font-medium hover:bg-emerald-50 transition-colors" wire:click="toggleServiceHoursForm">
                        {{ $showServiceHoursForm ? 'Abbrechen' : '+ Service Hours hinzufügen' }}
                    </button>
                </div>

                @if($showServiceHoursForm)
                    <div class="bg-gray-50 p-4 rounded-lg space-y-4 border border-gray-200">
                        <x-ui-form-grid :cols="2" :gap="4">
                            <div>
                                <label for="newServiceZeit.name" class="block text-[11px] font-medium text-gray-500 mb-1">Name</label>
                                <input type="text" id="newServiceZeit.name" name="newServiceZeit.name"
                                    wire:model="newServiceZeit.name"
                                    placeholder="z. B. Mo-Fr 9-17 Uhr"
                                    required
                                    class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c] transition-colors"
                                >
                                @error('newServiceZeit.name')
                                    <span class="text-xs text-red-600 mt-1">{{ $message }}</span>
                                @enderror
                            </div>

                            <div>
                                <label for="newServiceZeit.description" class="block text-[11px] font-medium text-gray-500 mb-1">Beschreibung</label>
                                <input type="text" id="newServiceZeit.description" name="newServiceZeit.description"
                                    wire:model="newServiceZeit.description"
                                    placeholder="Optionale Beschreibung"
                                    class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c] transition-colors"
                                >
                            </div>
                        </x-ui-form-grid>

                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox"
                                       wire:model="newServiceZeit.is_active"
                                       class="w-4 h-4 rounded border-gray-300 text-[#049b5c] focus:ring-[#049b5c] focus:ring-offset-0">
                                <span class="text-[13px] text-gray-700">Aktiv</span>
                            </label>

                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox"
                                       wire:model="newServiceZeit.use_auto_messages"
                                       class="w-4 h-4 rounded border-gray-300 text-[#049b5c] focus:ring-[#049b5c] focus:ring-offset-0">
                                <span class="text-[13px] text-gray-700">Auto-Nachrichten verwenden</span>
                            </label>
                        </div>

                        {{-- Service Hours Zeitplan --}}
                        <div class="space-y-3">
                            <h4 class="text-[13px] font-medium text-gray-900">Öffnungszeiten</h4>
                            <div class="space-y-2">
                                @foreach(['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'] as $index => $dayName)
                                    @php
                                        $dayIndex = $index === 6 ? 0 : $index + 1;
                                    @endphp
                                    <div class="flex items-center justify-between p-3 bg-white border border-gray-200">
                                        <div class="flex items-center gap-3 flex-1">
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox"
                                                       wire:model="newServiceZeit.service_hours.{{ $index }}.enabled"
                                                       class="w-4 h-4 rounded border-gray-300 text-[#049b5c] focus:ring-[#049b5c] focus:ring-offset-0">
                                                <span class="text-[13px] font-medium text-gray-700 w-24">{{ $dayName }}</span>
                                            </label>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input type="time"
                                                   wire:model="newServiceZeit.service_hours.{{ $index }}.start"
                                                   class="px-3 py-1.5 text-[13px] border border-gray-300 rounded-md bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c]">
                                            <span class="text-[13px] text-gray-400">bis</span>
                                            <input type="time"
                                                   wire:model="newServiceZeit.service_hours.{{ $index }}.end"
                                                   class="px-3 py-1.5 text-[13px] border border-gray-300 rounded-md bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c]">
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
                                <div>
                                    <label for="newServiceZeit.auto_message_inside" class="block text-[11px] font-medium text-gray-500 mb-1">Nachricht während Service-Zeit</label>
                                    <textarea id="newServiceZeit.auto_message_inside" name="newServiceZeit.auto_message_inside"
                                        wire:model="newServiceZeit.auto_message_inside"
                                        rows="2"
                                        placeholder="z. B. Vielen Dank für Ihr Ticket. Wir bearbeiten es innerhalb der nächsten 2 Stunden."
                                        class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c] transition-colors"
                                    ></textarea>
                                </div>

                                <div>
                                    <label for="newServiceZeit.auto_message_outside" class="block text-[11px] font-medium text-gray-500 mb-1">Nachricht außerhalb Service-Zeit</label>
                                    <textarea id="newServiceZeit.auto_message_outside" name="newServiceZeit.auto_message_outside"
                                        wire:model="newServiceZeit.auto_message_outside"
                                        rows="2"
                                        placeholder="z. B. Vielen Dank für Ihr Ticket. Wir bearbeiten es am nächsten Werktag."
                                        class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c] transition-colors"
                                    ></textarea>
                                </div>
                            </div>
                        @endif

                        <div class="flex justify-end">
                            <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#049b5c] text-white text-[13px] font-medium hover:bg-[#038a52] transition-colors" wire:click="addServiceHours">
                                Service Hours hinzufügen
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Bestehende Service Hours --}}
                <div class="space-y-2">
                    @forelse($serviceHours as $serviceHour)
                        <div class="flex items-center justify-between p-3 bg-white border border-gray-200 rounded-lg">
                            <div class="flex items-center gap-3 flex-1 min-w-0">
                                <div class="w-3 h-3 rounded-full flex-shrink-0 {{ $serviceHour->is_active ? 'bg-green-500' : 'bg-gray-400' }}"></div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-[13px] font-medium text-gray-900">{{ $serviceHour->name }}</div>
                                    @if($serviceHour->description)
                                        <div class="text-xs text-gray-400 mt-0.5">{{ $serviceHour->description }}</div>
                                    @endif
                                    <div class="text-xs text-gray-400 mt-1">
                                        {{ $serviceHour->getFormattedSchedule() }}
                                    </div>
                                    @if($serviceHour->use_auto_messages)
                                        <div class="text-xs text-[#049b5c] mt-1">Auto-Nachrichten aktiv</div>
                                    @endif
                                </div>
                            </div>
                            <button wire:click="deleteServiceHours({{ $serviceHour->id }})"
                                    class="text-red-600 hover:text-red-700 transition-colors flex-shrink-0 ml-3"
                                    title="Löschen">
                                @svg('heroicon-o-trash', 'w-4 h-4')
                            </button>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-400">
                            <p class="text-[13px]">Noch keine Service Hours definiert</p>
                        </div>
                    @endforelse
                </div>
            </div>

            @elseif($activeTab === 'sla')
            {{-- SLA-Auswahl --}}
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">Service Level Agreement (SLA)</h3>
                    <a href="{{ route('helpdesk.slas.index') }}" wire:navigate class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-[#049b5c] text-[#049b5c] text-[13px] font-medium hover:bg-emerald-50 transition-colors">
                        SLAs verwalten
                    </a>
                </div>

                <div class="space-y-4">
                    @if($board->sla)
                        <div class="flex items-center justify-between p-3 bg-white border border-gray-200 rounded-lg">
                            <div class="flex-1 min-w-0">
                                <div class="text-[13px] font-medium text-gray-900">{{ $board->sla->name }}</div>
                                @if($board->sla->description)
                                    <div class="text-xs text-gray-400 mt-0.5">{{ $board->sla->description }}</div>
                                @endif
                                <div class="text-xs text-gray-400 mt-1">
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
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-green-100 text-green-800">Aktiv</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-700">Inaktiv</span>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="text-center py-8 text-gray-400">
                            <p class="text-[13px]">Kein SLA zugewiesen</p>
                        </div>
                    @endif

                    <div>
                        <label for="board.helpdesk_board_sla_id" class="block text-[11px] font-medium text-gray-500 mb-1">SLA auswählen</label>
                        <select id="board.helpdesk_board_sla_id" name="board.helpdesk_board_sla_id"
                            wire:model.live="board.helpdesk_board_sla_id"
                            class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c] transition-colors"
                        >
                            <option value="">– SLA auswählen –</option>
                            @foreach($availableSlas as $sla)
                                <option value="{{ $sla->id }}">{{ $sla->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            @elseif($activeTab === 'error-tracking' && $errorSettings)
            {{-- Error Tracking Einstellungen --}}
            <div class="space-y-6">
                <div class="space-y-4">
                    <h3 class="text-sm font-semibold text-gray-900">Error Tracking</h3>

                    <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox"
                                   wire:model="errorSettings.enabled"
                                   class="w-5 h-5 rounded border-gray-300 text-[#049b5c] focus:ring-[#049b5c] focus:ring-offset-0">
                            <div>
                                <span class="text-[13px] font-medium text-gray-900">Error Tracking aktivieren</span>
                                <p class="text-xs text-gray-400 mt-0.5">Systemfehler werden automatisch als Tickets erfasst</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="space-y-4">
                    <h3 class="text-sm font-semibold text-gray-900">HTTP-Codes erfassen</h3>
                    <p class="text-[13px] text-gray-400">Wählen Sie die HTTP-Statuscodes, die als Fehler erfasst werden sollen.</p>

                    <div class="flex flex-wrap gap-2">
                        @foreach($availableHttpCodes as $code)
                            <button
                                type="button"
                                wire:click="toggleHttpCode({{ $code }})"
                                class="px-3 py-1.5 text-[13px] font-medium rounded-full border transition-colors
                                    {{ $this->isHttpCodeEnabled($code)
                                        ? 'bg-[#049b5c] text-white border-[#049b5c]'
                                        : 'bg-white text-gray-400 border-gray-300 hover:border-[#049b5c] hover:text-gray-700' }}"
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

                <div class="space-y-4">
                    <h3 class="text-sm font-semibold text-gray-900">Deduplizierung</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-[11px] font-medium text-gray-500 mb-1">Dedupe-Fenster (Stunden)</label>
                            <input type="number"
                                   wire:model="errorSettings.dedupe_window_hours"
                                   min="1"
                                   max="720"
                                   class="w-32 px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c]">
                            <p class="mt-1 text-xs text-gray-400">Gleiche Fehler werden innerhalb dieses Zeitraums zusammengefasst</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <h3 class="text-sm font-semibold text-gray-900">Optionen</h3>
                    <div class="space-y-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox"
                                   wire:model="errorSettings.capture_console_errors"
                                   class="w-4 h-4 rounded border-gray-300 text-[#049b5c] focus:ring-[#049b5c] focus:ring-offset-0">
                            <span class="text-[13px] text-gray-700">Console-/Scheduler-Fehler erfassen</span>
                        </label>
                        <p class="text-xs text-gray-400 ml-6">Fehler aus Artisan-Commands und Scheduler-Jobs werden ebenfalls erfasst (ohne User-Kontext)</p>

                        <label class="flex items-center gap-2 cursor-pointer mt-4">
                            <input type="checkbox"
                                   wire:model="errorSettings.auto_create_ticket"
                                   class="w-4 h-4 rounded border-gray-300 text-[#049b5c] focus:ring-[#049b5c] focus:ring-offset-0">
                            <span class="text-[13px] text-gray-700">Automatisch Tickets erstellen</span>
                        </label>
                        <p class="text-xs text-gray-400 ml-6">Bei neuen Fehlern wird automatisch ein Ticket angelegt</p>

                        <label class="flex items-center gap-2 cursor-pointer mt-4">
                            <input type="checkbox"
                                   wire:model="errorSettings.include_stack_trace"
                                   class="w-4 h-4 rounded border-gray-300 text-[#049b5c] focus:ring-[#049b5c] focus:ring-offset-0">
                            <span class="text-[13px] text-gray-700">Stack Trace erfassen</span>
                        </label>
                        <p class="text-xs text-gray-400 ml-6">Stack Trace wird in den Fehlerdaten gespeichert</p>

                        @if($errorSettings->include_stack_trace)
                            <div class="ml-6 mt-2">
                                <label class="block text-[11px] font-medium text-gray-500 mb-1">Stack Trace Limit (Frames)</label>
                                <input type="number"
                                       wire:model="errorSettings.stack_trace_limit"
                                       min="1"
                                       max="200"
                                       class="w-24 px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c]">
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            @elseif($activeTab === 'channels')
            {{-- Kanäle --}}
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">Verknüpfte Kanäle</h3>
                </div>

                <p class="text-[13px] text-gray-400">
                    Eingehende E-Mails auf verknüpften Kanälen erstellen automatisch ein Ticket in diesem Board.
                </p>

                <div class="space-y-2">
                    @forelse($availableChannels as $channel)
                        @php
                            $isLinked = in_array($channel['id'], $linkedChannelIds);
                        @endphp
                        <div class="flex items-center justify-between p-3 bg-white border border-gray-200 rounded-lg">
                            <div class="flex items-center gap-3 flex-1 min-w-0">
                                <div class="w-3 h-3 rounded-full flex-shrink-0 {{ $isLinked ? 'bg-green-500' : 'bg-gray-400' }}"></div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-[13px] font-medium text-gray-900">{{ $channel['sender_identifier'] }}</div>
                                    @if($channel['name'])
                                        <div class="text-xs text-gray-400 mt-0.5">{{ $channel['name'] }}</div>
                                    @endif
                                </div>
                            </div>
                            <button
                                wire:click="toggleChannel({{ $channel['id'] }})"
                                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20
                                    {{ $isLinked ? 'bg-[#049b5c]' : 'bg-gray-200' }}"
                            >
                                <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out
                                    {{ $isLinked ? 'translate-x-5' : 'translate-x-0' }}"></span>
                            </button>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-400">
                            <p class="text-[13px]">Keine E-Mail-Kanäle verfügbar</p>
                            <p class="text-xs mt-1">Erstellen Sie zuerst einen E-Mail-Kanal in den Kommunikations-Einstellungen.</p>
                        </div>
                    @endforelse
                </div>
            </div>
            @endif

            <hr class="border-gray-200">

            {{-- Löschen Button --}}
            <div class="flex justify-end">
                <x-ui-confirm-button action="deleteBoard" text="Board löschen" confirmText="Wirklich löschen?" />
            </div>
            </div>
        </div>
    @endif

    <x-slot name="footer">
        <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#049b5c] text-white text-[13px] font-medium hover:bg-[#038a52] transition-colors" wire:click="save">Speichern</button>
    </x-slot>
</x-ui-modal>
