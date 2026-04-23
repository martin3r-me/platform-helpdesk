<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Helpdesk', 'href' => route('helpdesk.dashboard'), 'icon' => 'lifebuoy'],
            ['label' => 'SLA-Verwaltung', 'href' => route('helpdesk.slas.index')],
            ['label' => $sla->name],
        ]">
            @if($this->isDirty)
                <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#049b5c] text-white text-[13px] font-medium hover:bg-[#038a52] transition-colors" wire:click="save">
                    @svg('heroicon-o-check', 'w-4 h-4')
                    <span>Speichern</span>
                </button>
            @endif
            <x-ui-confirm-button
                action="deleteSla"
                text="Löschen"
                confirmText="Wirklich löschen?"
                variant="danger"
                :icon="@svg('heroicon-o-trash', 'w-4 h-4')->toHtml()"
            />
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" defaultOpen="true" storeKey="sidebarOpen" side="left">
            <div class="p-4 space-y-5">
                {{-- SLA Info --}}
                <div>
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-3">SLA Info</div>
                    <div class="space-y-2 text-[13px]">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Status:</span>
                            <span class="font-medium">
                                @if($sla->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-green-100 text-green-800">Aktiv</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-700">Inaktiv</span>
                                @endif
                            </span>
                        </div>
                        @if($sla->response_time_hours)
                            <div class="flex justify-between">
                                <span class="text-gray-400">Reaktionszeit:</span>
                                <span class="font-medium text-gray-900">{{ $sla->response_time_hours }}h</span>
                            </div>
                        @endif
                        @if($sla->resolution_time_hours)
                            <div class="flex justify-between">
                                <span class="text-gray-400">Lösungszeit:</span>
                                <span class="font-medium text-gray-900">{{ $sla->resolution_time_hours }}h</span>
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
                <div class="text-[13px] text-gray-400">Letzte Aktivitäten</div>
                <div class="space-y-3 text-[13px]">
                    <div class="p-2 rounded-lg border border-gray-200 bg-gray-50">
                        <div class="font-medium text-gray-900 truncate">SLA geladen</div>
                        <div class="text-gray-400">gerade eben</div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container spacing="space-y-8">
        {{-- SLA Grunddaten --}}
        <section class="bg-white rounded-lg border border-gray-200">
            <div class="px-4 py-3 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-900">SLA-Details</h3>
            </div>
            <div class="p-4">
                <x-ui-form-grid :cols="2" :gap="6">
                    <div>
                        <label for="sla.name" class="block text-[11px] font-medium text-gray-500 mb-1">SLA-Name</label>
                        <input type="text" id="sla.name" name="sla.name"
                            wire:model.live.debounce.500ms="sla.name"
                            placeholder="z.B. Standard, Kritisch, Express"
                            required
                            class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c] transition-colors"
                        >
                        @error('sla.name')
                            <span class="text-xs text-red-600 mt-1">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="flex items-center">
                        <label class="flex items-center gap-3 cursor-pointer p-3 rounded-lg bg-gray-50 border border-gray-200 w-full">
                            <input type="checkbox"
                                   wire:model="sla.is_active"
                                   class="w-4 h-4 rounded border-gray-300 text-[#049b5c] focus:ring-[#049b5c] focus:ring-offset-0">
                            <span class="text-[13px] text-gray-700">{{ $sla->is_active ? 'SLA ist aktiv' : 'SLA ist inaktiv' }}</span>
                        </label>
                    </div>
                </x-ui-form-grid>
                <div class="mt-6">
                    <label for="sla.description" class="block text-[11px] font-medium text-gray-500 mb-1">Beschreibung</label>
                    <textarea id="sla.description" name="sla.description"
                        wire:model.live.debounce.500ms="sla.description"
                        placeholder="Beschreibung des SLAs (optional)"
                        rows="3"
                        class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c] transition-colors"
                    ></textarea>
                    @error('sla.description')
                        <span class="text-xs text-red-600 mt-1">{{ $message }}</span>
                    @enderror
                </div>
            </div>
        </section>

        {{-- SLA-Zeiten --}}
        <section class="bg-white rounded-lg border border-gray-200">
            <div class="px-4 py-3 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-900">Zeitvorgaben</h3>
            </div>
            <div class="p-4">
                <x-ui-form-grid :cols="2" :gap="6">
                    <div>
                        <label for="sla.response_time_hours" class="block text-[11px] font-medium text-gray-500 mb-1">Reaktionszeit (Stunden)</label>
                        <input type="number" id="sla.response_time_hours" name="sla.response_time_hours"
                            wire:model.live.debounce.500ms="sla.response_time_hours"
                            placeholder="z.B. 4"
                            min="1"
                            class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c] transition-colors"
                        >
                        @error('sla.response_time_hours')
                            <span class="text-xs text-red-600 mt-1">{{ $message }}</span>
                        @enderror
                    </div>
                    <div>
                        <label for="sla.resolution_time_hours" class="block text-[11px] font-medium text-gray-500 mb-1">Lösungszeit (Stunden)</label>
                        <input type="number" id="sla.resolution_time_hours" name="sla.resolution_time_hours"
                            wire:model.live.debounce.500ms="sla.resolution_time_hours"
                            placeholder="z.B. 24"
                            min="1"
                            class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c] transition-colors"
                        >
                        @error('sla.resolution_time_hours')
                            <span class="text-xs text-red-600 mt-1">{{ $message }}</span>
                        @enderror
                    </div>
                </x-ui-form-grid>
            </div>
        </section>

        {{-- Verwendung --}}
        <section class="bg-white rounded-lg border border-gray-200">
            <div class="px-4 py-3 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-900">Verwendung</h3>
            </div>
            <div class="p-4">
                {{-- Boards die dieses SLA verwenden --}}
                <div class="mb-6">
                    <h4 class="text-[13px] font-medium text-gray-900 mb-3">Boards mit diesem SLA</h4>
                    @if($boardsUsingThisSla->count() > 0)
                        <div class="space-y-3">
                            @foreach($boardsUsingThisSla as $board)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 bg-[#049b5c] text-white rounded-lg flex items-center justify-center">
                                            @svg('heroicon-o-folder', 'w-4 h-4')
                                        </div>
                                        <div>
                                            <div class="text-[13px] font-medium text-gray-900">{{ $board->name }}</div>
                                            <div class="text-xs text-gray-400">{{ $board->team->name }}</div>
                                        </div>
                                    </div>
                                    <a href="{{ route('helpdesk.boards.show', $board->id) }}"
                                       class="text-[#049b5c] hover:underline text-[13px]"
                                       wire:navigate>
                                        Board öffnen
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4 text-gray-400 text-[13px]">
                            Keine Boards verwenden dieses SLA
                        </div>
                    @endif
                </div>

                {{-- Letzte Tickets mit diesem SLA --}}
                <div>
                    <h4 class="text-[13px] font-medium text-gray-900 mb-3">Letzte Tickets mit diesem SLA</h4>
                    @if($ticketsUsingThisSla->count() > 0)
                        <div class="space-y-3">
                            @foreach($ticketsUsingThisSla as $ticket)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 bg-amber-100 text-amber-700 rounded-lg flex items-center justify-center">
                                            @svg('heroicon-o-ticket', 'w-4 h-4')
                                        </div>
                                        <div>
                                            <div class="text-[13px] font-medium text-gray-900">{{ $ticket->title }}</div>
                                            <div class="text-xs text-gray-400">
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
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-green-100 text-green-800">Erledigt</span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-100 text-amber-800">Offen</span>
                                        @endif
                                        <a href="{{ route('helpdesk.tickets.show', $ticket->id) }}"
                                           class="text-[#049b5c] hover:underline text-[13px]"
                                           wire:navigate>
                                            Ticket öffnen
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4 text-gray-400 text-[13px]">
                            Keine Tickets mit diesem SLA gefunden
                        </div>
                    @endif
                </div>
            </div>
        </section>
    </x-ui-page-container>

</x-ui-page>
