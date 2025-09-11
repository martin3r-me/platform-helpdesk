<div class="h-full overflow-y-auto p-6">
    <!-- Header mit Datum und Perspektive-Toggle -->
    <div class="mb-6">
        <div class="d-flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Helpdesk Dashboard</h1>
                <p class="text-gray-600">{{ $currentDay }}, {{ $currentDate }}</p>
            </div>
            <div class="d-flex items-center gap-4">
                <!-- Perspektive-Toggle -->
                <div class="d-flex bg-gray-100 rounded-lg p-1">
                    <button 
                        wire:click="$set('perspective', 'personal')"
                        class="px-4 py-2 rounded-md text-sm font-medium transition"
                        :class="'{{ $perspective }}' === 'personal' 
                            ? 'bg-success text-on-success shadow-sm' 
                            : 'text-gray-600 hover:text-gray-900'"
                    >
                        <div class="d-flex items-center gap-2">
                            @svg('heroicon-o-user', 'w-4 h-4')
                            <span>Persönlich</span>
                        </div>
                    </button>
                    <button 
                        wire:click="$set('perspective', 'team')"
                        class="px-4 py-2 rounded-md text-sm font-medium transition"
                        :class="'{{ $perspective }}' === 'team' 
                            ? 'bg-success text-on-success shadow-sm' 
                            : 'text-gray-600 hover:text-gray-900'"
                    >
                        <div class="d-flex items-center gap-2">
                            @svg('heroicon-o-users', 'w-4 h-4')
                            <span>Team</span>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Perspektive-spezifische Statistiken -->
    @if($perspective === 'personal')
        <!-- Persönliche Perspektive -->
        <div class="mb-4">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="d-flex items-center gap-2 mb-2">
                    @svg('heroicon-o-user', 'w-5 h-5 text-blue-600')
                    <h3 class="text-lg font-semibold text-blue-900">Persönliche Übersicht</h3>
                </div>
                <p class="text-blue-700 text-sm">Deine persönlichen Helpdesk-Tickets und zuständigen Support-Anfragen.</p>
            </div>
        </div>
    @else
        <!-- Team-Perspektive -->
        <div class="mb-4">
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="d-flex items-center gap-2 mb-2">
                    @svg('heroicon-o-users', 'w-5 h-5 text-green-600')
                    <h3 class="text-lg font-semibold text-green-900">Team-Übersicht</h3>
                </div>
                <p class="text-green-700 text-sm">Alle Helpdesk-Tickets des Teams in allen aktiven Boards.</p>
            </div>
        </div>
    @endif

    <!-- SLA & Eskalationen (2er Grid) -->
    <div class="grid grid-cols-2 gap-4 mb-8">
        <!-- SLA-Überschreitungen -->
        <x-ui-dashboard-tile
            title="SLA-Überschreitungen"
            :count="$slaOverdueTickets"
            subtitle="gefährdet: {{ $slaAtRiskTickets }}"
            icon="exclamation-triangle"
            variant="danger"
            size="lg"
        />
        
        <!-- Eskalierte Tickets -->
        <x-ui-dashboard-tile
            title="Eskalierte Tickets"
            :count="$escalatedTickets"
            subtitle="kritisch: {{ $criticalEscalations }}"
            icon="fire"
            variant="danger"
            size="lg"
        />
    </div>

    <!-- Allgemeine Statistiken (3er Grid) -->
    <div class="grid grid-cols-3 gap-4 mb-8">
        <!-- Boards -->
        <x-ui-dashboard-tile
            title="Aktive Boards"
            :count="$activeBoards"
            subtitle="Helpdesk Boards"
            icon="folder"
            variant="primary"
            size="lg"
        />
        
        <!-- Tickets -->
        <x-ui-dashboard-tile
            title="Offene Tickets"
            :count="$openTickets"
            subtitle="von {{ $totalTickets }}"
            icon="clock"
            variant="warning"
            size="lg"
        />
        
        <!-- Erledigte Tickets -->
        <x-ui-dashboard-tile
            title="Erledigte Tickets"
            :count="$completedTickets"
            subtitle="diesen Monat: {{ $monthlyCompletedTickets }}"
            icon="check-circle"
            variant="success"
            size="lg"
        />
    </div>

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

    <!-- Detaillierte Statistiken (2x3 Grid) -->
    <div class="grid grid-cols-2 gap-6 mb-8">
        <!-- Linke Spalte: Ticket-Details -->
        <div class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Ticket-Übersicht</h3>
            
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
            <h3 class="text-lg font-semibold text-gray-900 mb-4">SLA Performance</h3>
            
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
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Meine aktiven Helpdesk Boards</h3>
            <p class="text-sm text-gray-600 mt-1">Top 5 Boards nach offenen Tickets</p>
        </div>
        
        <div class="p-6">
            @if($activeBoardsList->count() > 0)
                <div class="space-y-4">
                    @foreach($activeBoardsList as $board)
                        <div class="d-flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                            <div class="d-flex items-center gap-4">
                                <div class="w-10 h-10 bg-primary text-on-primary rounded-lg d-flex items-center justify-center">
                                    <x-heroicon-o-folder class="w-5 h-5"/>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900">{{ $board['name'] }}</h4>
                                    <p class="text-sm text-gray-600">
                                        {{ $board['open_tickets'] }} offene von {{ $board['total_tickets'] }} Tickets
                                        @if($board['high_priority'] > 0)
                                            • {{ $board['high_priority'] }} hohe Priorität
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <a href="{{ route('helpdesk.boards.show', $board['id']) }}" 
                               class="inline-flex items-center gap-2 px-3 py-2 bg-primary text-on-primary rounded-md hover:bg-primary-dark transition text-sm"
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
                    <x-heroicon-o-folder class="w-12 h-12 text-gray-400 mx-auto mb-4"/>
                    <h4 class="text-lg font-medium text-gray-900 mb-2">Keine aktiven Boards</h4>
                    <p class="text-gray-600">Du hast noch keine Helpdesk Boards oder bist in keinem Board zuständig.</p>
                </div>
            @endif
        </div>
    </div>
</div>