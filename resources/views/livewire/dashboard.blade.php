<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Helpdesk" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Schnellzugriff" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                {{-- Aktionen --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Aktionen</h3>
                    <div class="space-y-2">
                        <x-ui-button variant="secondary-outline" size="sm" :href="route('helpdesk.my-tickets')" wire:navigate class="w-full">
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-ticket', 'w-4 h-4')
                                Meine Tickets
                            </span>
                        </x-ui-button>
                    </div>
                </div>

                {{-- Schnellstatistiken --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Schnellstatistiken</h3>
                    <div class="space-y-3">
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="text-xs text-[var(--ui-muted)]">Aktive Boards</div>
                            <div class="text-lg font-bold text-[var(--ui-secondary)]">{{ $activeBoards }}</div>
                        </div>
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="text-xs text-[var(--ui-muted)]">Offene Tickets</div>
                            <div class="text-lg font-bold text-[var(--ui-secondary)]">{{ $openTickets }}</div>
                        </div>
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="text-xs text-[var(--ui-muted)]">Eskalationen</div>
                            <div class="text-lg font-bold text-[var(--ui-secondary)]">{{ $escalatedTickets }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-4">
                <div class="text-sm text-[var(--ui-muted)]">Letzte Aktivitäten</div>
                <div class="space-y-3 text-sm">
                    <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                        <div class="font-medium text-[var(--ui-secondary)] truncate">Dashboard geladen</div>
                        <div class="text-[var(--ui-muted)]">gerade eben</div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
    
    <!-- Haupt-Kacheln (schlank, Planner-Stil) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <x-ui-dashboard-tile title="Offene Tickets" :count="$openTickets" icon="clock" variant="warning" size="lg" />
        <x-ui-dashboard-tile title="Eskalationen" :count="$escalatedTickets" icon="fire" variant="danger" size="lg" />
        <x-ui-dashboard-tile title="Aktive Boards" :count="$activeBoards" icon="folder" variant="secondary" size="lg" />
        <x-ui-dashboard-tile title="SLA überfällig" :count="$slaOverdueTickets" icon="exclamation-triangle" variant="danger" size="lg" />
    </div>

    <!-- Sekundäre Kennzahlen (kompakt) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <x-ui-dashboard-tile title="SLA gefährdet" :count="$slaAtRiskTickets" icon="clock" variant="warning" size="md" />
        <x-ui-dashboard-tile title="Erledigt (Monat)" :count="$monthlyCompletedTickets" icon="check-circle" variant="success" size="md" />
        <x-ui-dashboard-tile title="Überfällig" :count="$overdueTickets" icon="exclamation-circle" variant="danger" size="md" />
        <x-ui-dashboard-tile title="Tickets gesamt" :count="$totalTickets" icon="document-text" variant="neutral" size="md" />
    </div>

    <!-- Eskalations-Übersicht (optional) -->

    <!-- Eskalations-Übersicht -->
    @if($escalatedTickets > 0)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8">
            <div class="p-6 border-b border-gray-200">
                <div class="d-flex items-center gap-2">
                    <x-heroicon-o-fire class="w-5 h-5 text-red-600"/>
                    <h3 class="text-lg font-semibold text-gray-900">Eskalierte Tickets</h3>
                    <x-ui-badge variant="danger" size="sm">{{ $escalatedTickets }}</x-ui-badge>
                </div>
                <p class="text-sm text-gray-600 mt-1">Tickets die dringend Aufmerksamkeit benötigen</p>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-4 gap-4">
                    <x-ui-dashboard-tile
                        title="Warnung"
                        :count="$escalationLevels['warning']"
                        icon="exclamation-triangle"
                        variant="warning"
                        size="sm"
                    />
                    
                    <x-ui-dashboard-tile
                        title="Eskaliert"
                        :count="$escalationLevels['escalated']"
                        icon="exclamation-circle"
                        variant="danger"
                        size="sm"
                    />
                    
                    <x-ui-dashboard-tile
                        title="Kritisch"
                        :count="$escalationLevels['critical']"
                        icon="fire"
                        variant="danger"
                        size="sm"
                    />
                    
                    <x-ui-dashboard-tile
                        title="Dringend"
                        :count="$escalationLevels['urgent']"
                        icon="bolt"
                        variant="danger"
                        size="sm"
                    />
                </div>
            </div>
        </div>
    @endif

    <!-- Detaillierte Statistiken (2 Spalten) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Linke Spalte: Ticket-Details -->
        <div class="space-y-4">
            <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Ticket-Übersicht</h3>
            
            <div class="grid grid-cols-2 gap-3">
                <x-ui-dashboard-tile
                    title="Hohe Priorität"
                    :count="$highPriorityTickets"
                    icon="exclamation-triangle"
                    variant="danger"
                    size="sm"
                />
                
                <x-ui-dashboard-tile
                    title="Überfällig"
                    :count="$overdueTickets"
                    icon="exclamation-circle"
                    variant="danger"
                    size="sm"
                />
                
                <x-ui-dashboard-tile
                    title="Erstellt (Monat)"
                    :count="$monthlyCreatedTickets"
                    icon="plus-circle"
                    variant="neutral"
                    size="sm"
                />
                
                <x-ui-dashboard-tile
                    title="Erledigt (Monat)"
                    :count="$monthlyCompletedTickets"
                    icon="check-circle"
                    variant="success"
                    size="sm"
                />
            </div>
        </div>

        <!-- Rechte Spalte: SLA Performance -->
        <div class="space-y-4">
            <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">SLA Performance</h3>
            
            <div class="grid grid-cols-2 gap-3">
                <x-ui-dashboard-tile
                    title="Überschritten"
                    :count="$slaOverdueTickets"
                    icon="exclamation-triangle"
                    variant="danger"
                    size="sm"
                />
                
                <x-ui-dashboard-tile
                    title="Gefährdet"
                    :count="$slaAtRiskTickets"
                    icon="clock"
                    variant="warning"
                    size="sm"
                />
                
                <x-ui-dashboard-tile
                    title="Offen"
                    :count="$openTickets"
                    icon="clock"
                    variant="warning"
                    size="sm"
                />
                
                <x-ui-dashboard-tile
                    title="Erledigt"
                    :count="$completedTickets"
                    icon="check-circle"
                    variant="success"
                    size="sm"
                />
            </div>
        </div>
    </div>

    <!-- Board-Übersicht -->
    <div class="bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)]/60">
        <div class="p-6 border-b border-[var(--ui-border)]/60">
            <h3 class="text-lg font-semibold text-[var(--ui-secondary)]">Meine aktiven Helpdesk Boards</h3>
            <p class="text-sm text-[var(--ui-muted)] mt-1">Top 5 Boards nach offenen Tickets</p>
        </div>
        
        <div class="p-6">
            @if($activeBoardsList->count() > 0)
                <div class="space-y-4">
                    @foreach($activeBoardsList as $board)
                        <div class="d-flex items-center justify-between p-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/60 hover:bg-[var(--ui-primary-5)] hover:border-[var(--ui-primary)]/60 transition">
                            <div class="d-flex items-center gap-4">
                                <div class="w-10 h-10 rounded-lg d-flex items-center justify-center bg-[var(--ui-primary-5)] text-[var(--ui-primary)]">
                                    <x-heroicon-o-folder class="w-5 h-5"/>
                                </div>
                                <div>
                                    <h4 class="font-medium text-[var(--ui-secondary)]">{{ $board['name'] }}</h4>
                                    <p class="text-sm text-[var(--ui-muted)]">
                                        {{ $board['open_tickets'] }} offene von {{ $board['total_tickets'] }} Tickets
                                        @if($board['high_priority'] > 0)
                                            • {{ $board['high_priority'] }} hohe Priorität
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <a href="{{ route('helpdesk.boards.show', $board['id']) }}" 
                               class="inline-flex items-center gap-2 px-3 py-2 rounded-md border border-[var(--ui-primary)] text-[var(--ui-primary)] bg-[var(--ui-primary-5)] hover:bg-[var(--ui-primary-10)] transition text-sm"
                               wire:navigate>
                                <div class="d-flex items-center gap-2">
                                    @svg('heroicon-o-arrow-right', 'w-4 h-4')
                                    <span>Öffnen</span>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <x-heroicon-o-folder class="w-12 h-12 text-[var(--ui-muted)] mx-auto mb-4"/>
                    <h4 class="text-lg font-medium text-[var(--ui-secondary)] mb-2">Keine aktiven Boards</h4>
                    <p class="text-[var(--ui-muted)]">Du hast noch keine Helpdesk Boards oder bist in keinem Board zuständig.</p>
                </div>
            @endif
        </div>
    </div>

    </x-ui-page-container>
</x-ui-page>