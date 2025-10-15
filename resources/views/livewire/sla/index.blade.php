<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="SLA-Verwaltung" icon="heroicon-o-clock">
            <div class="flex items-center gap-2">
                <x-ui-input-text 
                    name="search" 
                    placeholder="Suche SLAs..." 
                    wire:model.live.debounce.300ms="search"
                    class="w-64"
                />
                <x-ui-button variant="primary" wire:click="openCreateModal">
                    Neues SLA
                </x-ui-button>
            </div>
        </x-ui-page-navbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Helpdesk" width="w-72" defaultOpen="true" storeKey="sidebarOpen" side="left">
            @include('helpdesk::livewire.sidebar')
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
    <x-ui-table compact="true">
        <x-ui-table-header>
            <x-ui-table-header-cell compact="true" sortable="true" sortField="name" :currentSort="$sortField" :sortDirection="$sortDirection" wire:click="sortBy('name')">Name</x-ui-table-header-cell>
            <x-ui-table-header-cell compact="true">Beschreibung</x-ui-table-header-cell>
            <x-ui-table-header-cell compact="true">Reaktionszeit</x-ui-table-header-cell>
            <x-ui-table-header-cell compact="true">Lösungszeit</x-ui-table-header-cell>
            <x-ui-table-header-cell compact="true" sortable="true" sortField="is_active" :currentSort="$sortField" :sortDirection="$sortDirection" wire:click="sortBy('is_active')">Status</x-ui-table-header-cell>
            <x-ui-table-header-cell compact="true" align="right">Aktionen</x-ui-table-header-cell>
        </x-ui-table-header>
        
        <x-ui-table-body>
            @foreach($slas as $sla)
                <x-ui-table-row compact="true">
                    <x-ui-table-cell compact="true">
                        <div class="font-medium">{{ $sla->name }}</div>
                    </x-ui-table-cell>
                    <x-ui-table-cell compact="true">
                        <div class="text-sm text-muted">
                            {{ Str::limit($sla->description, 50) ?: '–' }}
                        </div>
                    </x-ui-table-cell>
                    <x-ui-table-cell compact="true">
                        @if($sla->response_time_hours)
                            <div class="text-sm">
                                {{ $sla->response_time_hours }} Stunden
                            </div>
                        @else
                            <span class="text-xs text-muted">–</span>
                        @endif
                    </x-ui-table-cell>
                    <x-ui-table-cell compact="true">
                        @if($sla->resolution_time_hours)
                            <div class="text-sm">
                                {{ $sla->resolution_time_hours }} Stunden
                            </div>
                        @else
                            <span class="text-xs text-muted">–</span>
                        @endif
                    </x-ui-table-cell>
                    <x-ui-table-cell compact="true">
                        @if($sla->is_active)
                            <x-ui-badge variant="success" size="sm">Aktiv</x-ui-badge>
                        @else
                            <x-ui-badge variant="secondary" size="sm">Inaktiv</x-ui-badge>
                        @endif
                    </x-ui-table-cell>
                    <x-ui-table-cell compact="true" align="right">
                        <x-ui-button 
                            size="sm" 
                            variant="secondary" 
                            :href="route('helpdesk.slas.show', $sla->id)"
                            wire:navigate
                        >
                            Bearbeiten
                        </x-ui-button>
                    </x-ui-table-cell>
                </x-ui-table-row>
            @endforeach
        </x-ui-table-body>
    </x-ui-table>

    </x-ui-page-container>

    <!-- Create SLA Modal -->
    <x-ui-modal
        wire:model="modalShow"
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
            <div class="d-flex justify-end gap-2">
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
