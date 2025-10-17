<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="SLA-Verwaltung" icon="heroicon-o-clock" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-72" defaultOpen="true" storeKey="sidebarOpen" side="left">
            <div class="p-4 space-y-4">
                @php $total = method_exists($slas, 'total') ? $slas->total() : $slas->count(); @endphp

                <div class="p-3 bg-[color:var(--ui-muted-5)] rounded-lg">
                    <h4 class="text-sm font-semibold text-[color:var(--ui-secondary)] mb-1">Statistik</h4>
                    <div class="text-sm text-[color:var(--ui-secondary)]">Gefundene SLAs: <strong>{{ $total }}</strong></div>
                </div>

                <div>
                    <h4 class="text-sm font-semibold text-[color:var(--ui-secondary)] mb-2">Aktionen</h4>
                    <x-ui-button variant="primary" size="sm" wire:click="openCreateModal" class="w-full">
                        @svg('heroicon-o-plus', 'w-4 h-4 mr-2') Neues SLA
                    </x-ui-button>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-4">
                <p class="text-sm text-[color:var(--ui-muted)]">Aktivitäten werden hier angezeigt...</p>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
        <div class="mb-6 flex items-center justify-between">
            <x-ui-input-text
                name="search"
                placeholder="Suche SLAs..."
                class="w-64"
                wire:model.live.debounce.300ms="search"
            />
        </div>

        <x-ui-table compact="true">
            <x-ui-table-header>
                <x-ui-table-header-cell compact="true" sortable="true" sortField="name" :currentSort="$sortField" :sortDirection="$sortDirection">Name</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Beschreibung</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Reaktionszeit</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Lösungszeit</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true" sortable="true" sortField="is_active" :currentSort="$sortField" :sortDirection="$sortDirection">Status</x-ui-table-header-cell>
            </x-ui-table-header>
            
            <x-ui-table-body>
                @foreach($slas as $sla)
                    <x-ui-table-row
                        compact="true"
                        clickable="true"
                        :href="route('helpdesk.slas.show', $sla->id)"
                        wire:navigate
                    >
                        <x-ui-table-cell compact="true">
                            <div class="font-medium">{{ $sla->name }}</div>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <div class="text-sm text-[color:var(--ui-muted)]">
                                {{ Str::limit($sla->description, 50) ?: '–' }}
                            </div>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            @if($sla->response_time_hours)
                                <div class="text-sm">
                                    {{ $sla->response_time_hours }} Stunden
                                </div>
                            @else
                                <span class="text-xs text-[color:var(--ui-muted)]">–</span>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            @if($sla->resolution_time_hours)
                                <div class="text-sm">
                                    {{ $sla->resolution_time_hours }} Stunden
                                </div>
                            @else
                                <span class="text-xs text-[color:var(--ui-muted)]">–</span>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            @if($sla->is_active)
                                <x-ui-badge variant="success" size="sm">Aktiv</x-ui-badge>
                            @else
                                <x-ui-badge variant="secondary" size="sm">Inaktiv</x-ui-badge>
                            @endif
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @endforeach
            </x-ui-table-body>
        </x-ui-table>
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
                <div class="d-flex items-center gap-2 mb-2">
                    @svg('heroicon-o-information-circle', 'w-5 h-5 text-blue-600')
                    <h4 class="font-medium text-blue-900">Hinweis</h4>
                </div>
                <p class="text-blue-700 text-sm">SLAs definieren Service Level Agreements für die Bearbeitung von Tickets. Reaktions- und Lösungszeiten können optional festgelegt werden.</p>
            </div>

            <form wire:submit.prevent="createSla" class="space-y-4">
                <x-ui-input-text
                    name="name"
                    label="SLA-Name"
                    wire:model.live="name"
                    required
                    placeholder="z.B. Kritisch, Normal, Niedrig"
                />

                <x-ui-input-textarea
                    name="description"
                    label="Beschreibung"
                    wire:model.live="description"
                    placeholder="Beschreibung des SLAs (optional)"
                    rows="3"
                />

                <div class="grid grid-cols-2 gap-4">
                    <x-ui-input-number
                        name="response_time_hours"
                        label="Reaktionszeit (Stunden)"
                        wire:model.live="response_time_hours"
                        placeholder="z.B. 4"
                        :nullable="true"
                        min="1"
                    />
                    
                    <x-ui-input-number
                        name="resolution_time_hours"
                        label="Lösungszeit (Stunden)"
                        wire:model.live="resolution_time_hours"
                        placeholder="z.B. 24"
                        :nullable="true"
                        min="1"
                    />
                </div>

                <x-ui-input-checkbox
                    model="is_active"
                    checked-label="SLA ist aktiv"
                    unchecked-label="SLA ist inaktiv"
                    size="md"
                    block="true"
                />
            </form>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-ui-button 
                    type="button" 
                    variant="secondary-outline" 
                    wire:click="closeCreateModal"
                >
                    Abbrechen
                </x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="createSla">
                    SLA anlegen
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

</x-ui-page>
