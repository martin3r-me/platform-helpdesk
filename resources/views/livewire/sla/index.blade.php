<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="SLA-Verwaltung" icon="heroicon-o-clock" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Helpdesk', 'href' => route('helpdesk.dashboard'), 'icon' => 'lifebuoy'],
            ['label' => 'SLA-Verwaltung'],
        ]">
            <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#049b5c] text-white text-[13px] font-medium hover:bg-[#038a52] transition-colors" wire:click="openCreateModal">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neues SLA</span>
            </button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-72" defaultOpen="true" storeKey="sidebarOpen" side="left">
            <div class="p-4 space-y-4">
                @php $total = method_exists($slas, 'total') ? $slas->total() : $slas->count(); @endphp

                <div class="bg-white rounded-lg border border-gray-200 p-3">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Statistik</div>
                    <div class="text-[13px] text-gray-900">Gefundene SLAs: <strong>{{ $total }}</strong></div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-4">
                <p class="text-[13px] text-gray-400">Aktivitäten werden hier angezeigt...</p>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <label for="search" class="block text-[11px] font-medium text-gray-500 mb-1">Suche</label>
                <input type="text" id="search" name="search"
                    placeholder="Suche SLAs..."
                    wire:model.live.debounce.300ms="search"
                    class="w-64 px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c] transition-colors"
                >
            </div>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-medium text-gray-400 uppercase tracking-wide cursor-pointer hover:text-gray-700" wire:click="$set('sortField', 'name')">
                            <span class="flex items-center gap-1">
                                Name
                                @if($sortField === 'name')
                                    @svg($sortDirection === 'asc' ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down', 'w-3 h-3')
                                @endif
                            </span>
                        </th>
                        <th class="px-4 py-3 text-left text-[11px] font-medium text-gray-400 uppercase tracking-wide">Beschreibung</th>
                        <th class="px-4 py-3 text-left text-[11px] font-medium text-gray-400 uppercase tracking-wide">Reaktionszeit</th>
                        <th class="px-4 py-3 text-left text-[11px] font-medium text-gray-400 uppercase tracking-wide">Lösungszeit</th>
                        <th class="px-4 py-3 text-left text-[11px] font-medium text-gray-400 uppercase tracking-wide cursor-pointer hover:text-gray-700" wire:click="$set('sortField', 'is_active')">
                            <span class="flex items-center gap-1">
                                Status
                                @if($sortField === 'is_active')
                                    @svg($sortDirection === 'asc' ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down', 'w-3 h-3')
                                @endif
                            </span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($slas as $sla)
                        <tr class="hover:bg-emerald-50/50 transition-colors cursor-pointer" onclick="window.location='{{ route('helpdesk.slas.show', $sla->id) }}'">
                            <td class="px-4 py-3">
                                <a href="{{ route('helpdesk.slas.show', $sla->id) }}" wire:navigate class="text-[13px] font-medium text-gray-900 hover:text-[#049b5c]">{{ $sla->name }}</a>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-[13px] text-gray-400">
                                    {{ Str::limit($sla->description, 50) ?: '–' }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                @if($sla->response_time_hours)
                                    <div class="text-[13px] text-gray-700">{{ $sla->response_time_hours }} Stunden</div>
                                @else
                                    <span class="text-xs text-gray-400">–</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($sla->resolution_time_hours)
                                    <div class="text-[13px] text-gray-700">{{ $sla->resolution_time_hours }} Stunden</div>
                                @else
                                    <span class="text-xs text-gray-400">–</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($sla->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-green-100 text-green-800">Aktiv</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-700">Inaktiv</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-ui-page-container>

    <!-- Create SLA Modal -->
    <x-ui-modal
        model="modalShow"
        size="lg"
    >
        <x-slot name="header">
            SLA anlegen
        </x-slot>

        <div class="space-y-4">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                <div class="flex items-center gap-2 mb-2">
                    @svg('heroicon-o-information-circle', 'w-5 h-5 text-blue-600')
                    <h4 class="font-medium text-blue-900 text-[13px]">Hinweis</h4>
                </div>
                <p class="text-blue-700 text-[13px]">SLAs definieren Service Level Agreements für die Bearbeitung von Tickets. Reaktions- und Lösungszeiten können optional festgelegt werden.</p>
            </div>

            <form wire:submit.prevent="createSla" class="space-y-4">
                <div>
                    <label for="name" class="block text-[11px] font-medium text-gray-500 mb-1">SLA-Name</label>
                    <input type="text" id="name" name="name"
                        wire:model.live="name"
                        required
                        placeholder="z.B. Kritisch, Normal, Niedrig"
                        class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c] transition-colors"
                    >
                </div>

                <div>
                    <label for="description" class="block text-[11px] font-medium text-gray-500 mb-1">Beschreibung</label>
                    <textarea id="description" name="description"
                        wire:model.live="description"
                        placeholder="Beschreibung des SLAs (optional)"
                        rows="3"
                        class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c] transition-colors"
                    ></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="response_time_hours" class="block text-[11px] font-medium text-gray-500 mb-1">Reaktionszeit (Stunden)</label>
                        <input type="number" id="response_time_hours" name="response_time_hours"
                            wire:model.live="response_time_hours"
                            placeholder="z.B. 4"
                            min="1"
                            class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c] transition-colors"
                        >
                    </div>

                    <div>
                        <label for="resolution_time_hours" class="block text-[11px] font-medium text-gray-500 mb-1">Lösungszeit (Stunden)</label>
                        <input type="number" id="resolution_time_hours" name="resolution_time_hours"
                            wire:model.live="resolution_time_hours"
                            placeholder="z.B. 24"
                            min="1"
                            class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c] transition-colors"
                        >
                    </div>
                </div>

                <label class="flex items-center gap-3 cursor-pointer p-3 rounded-lg bg-gray-50 border border-gray-200">
                    <input type="checkbox"
                           wire:model="is_active"
                           class="w-4 h-4 rounded border-gray-300 text-[#049b5c] focus:ring-[#049b5c] focus:ring-offset-0">
                    <span class="text-[13px] text-gray-700">SLA ist aktiv</span>
                </label>
            </form>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-[13px] font-medium hover:bg-gray-50 transition-colors" wire:click="closeCreateModal">
                    Abbrechen
                </button>
                <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#049b5c] text-white text-[13px] font-medium hover:bg-[#038a52] transition-colors" wire:click="createSla">
                    SLA anlegen
                </button>
            </div>
        </x-slot>
    </x-ui-modal>

</x-ui-page>
