<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Helpdesk" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Helpdesk', 'icon' => 'lifebuoy'],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Schnellzugriff" width="w-80" :defaultOpen="true">
            <div class="p-4 space-y-4">
                {{-- Schnellstatistiken --}}
                <div>
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-3">Schnellstatistiken</div>
                    <div class="space-y-2">
                        <div class="bg-white rounded-lg border border-gray-200 p-3">
                            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Aktive Boards</div>
                            <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $activeBoards }}</div>
                        </div>
                        <div class="bg-white rounded-lg border border-gray-200 p-3">
                            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Offene Tickets</div>
                            <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $openTickets }}</div>
                        </div>
                        <div class="bg-white rounded-lg border border-gray-200 p-3">
                            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Eskalationen</div>
                            <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $escalatedTickets }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-4">
                <div class="text-[13px] text-gray-400">Letzte Aktivitäten</div>
                <div class="space-y-3 text-[13px]">
                    <div class="p-2 rounded-lg border border-gray-200 bg-gray-50">
                        <div class="font-medium text-gray-900 truncate">Dashboard geladen</div>
                        <div class="text-gray-400">gerade eben</div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>

    <!-- Haupt-Kacheln -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-lg border border-gray-200 p-4">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center">
                    @svg('heroicon-o-clock', 'w-4 h-4 text-amber-600')
                </div>
                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Offene Tickets</div>
            </div>
            <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $openTickets }}</div>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center">
                    @svg('heroicon-o-fire', 'w-4 h-4 text-red-600')
                </div>
                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Eskalationen</div>
            </div>
            <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $escalatedTickets }}</div>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center">
                    @svg('heroicon-o-folder', 'w-4 h-4 text-gray-600')
                </div>
                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Aktive Boards</div>
            </div>
            <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $activeBoards }}</div>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center">
                    @svg('heroicon-o-exclamation-triangle', 'w-4 h-4 text-red-600')
                </div>
                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">SLA überfällig</div>
            </div>
            <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $slaOverdueTickets }}</div>
        </div>
    </div>

    <!-- Sekundäre Kennzahlen -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-lg border border-gray-200 p-4">
            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">SLA gefährdet</div>
            <div class="text-xl font-bold text-amber-600 tabular-nums">{{ $slaAtRiskTickets }}</div>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Erledigt (Monat)</div>
            <div class="text-xl font-bold text-green-600 tabular-nums">{{ $monthlyCompletedTickets }}</div>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Überfällig</div>
            <div class="text-xl font-bold text-red-600 tabular-nums">{{ $overdueTickets }}</div>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Tickets gesamt</div>
            <div class="text-xl font-bold text-gray-900 tabular-nums">{{ $totalTickets }}</div>
        </div>
    </div>

    <!-- Eskalations-Übersicht -->
    @if($escalatedTickets > 0)
        <section class="bg-white rounded-lg border border-gray-200 mb-8">
            <div class="px-4 py-3 border-b border-gray-200">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-fire class="w-5 h-5 text-red-600"/>
                    <h3 class="text-sm font-semibold text-gray-900">Eskalierte Tickets</h3>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">{{ $escalatedTickets }}</span>
                </div>
                <p class="text-[13px] text-gray-500 mt-1">Tickets die dringend Aufmerksamkeit benötigen</p>
            </div>

            <div class="p-4">
                <div class="grid grid-cols-4 gap-4">
                    <div class="bg-white rounded-lg border border-gray-200 p-3">
                        <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Warnung</div>
                        <div class="text-xl font-bold text-amber-700 tabular-nums">{{ $escalationLevels['warning'] }}</div>
                    </div>
                    <div class="bg-white rounded-lg border border-gray-200 p-3">
                        <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Eskaliert</div>
                        <div class="text-xl font-bold text-orange-700 tabular-nums">{{ $escalationLevels['escalated'] }}</div>
                    </div>
                    <div class="bg-white rounded-lg border border-gray-200 p-3">
                        <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Kritisch</div>
                        <div class="text-xl font-bold text-red-700 tabular-nums">{{ $escalationLevels['critical'] }}</div>
                    </div>
                    <div class="bg-white rounded-lg border border-gray-200 p-3">
                        <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Dringend</div>
                        <div class="text-xl font-bold text-red-800 tabular-nums">{{ $escalationLevels['urgent'] }}</div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    <!-- Detaillierte Statistiken (2 Spalten) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Linke Spalte: Ticket-Details -->
        <div class="space-y-4">
            <h3 class="text-sm font-semibold text-gray-900">Ticket-Übersicht</h3>

            <div class="grid grid-cols-2 gap-3">
                <div class="bg-white rounded-lg border border-gray-200 p-3">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Hohe Priorität</div>
                    <div class="text-lg font-bold text-red-600 tabular-nums">{{ $highPriorityTickets }}</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-3">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Überfällig</div>
                    <div class="text-lg font-bold text-red-600 tabular-nums">{{ $overdueTickets }}</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-3">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Erstellt (Monat)</div>
                    <div class="text-lg font-bold text-gray-900 tabular-nums">{{ $monthlyCreatedTickets }}</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-3">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Erledigt (Monat)</div>
                    <div class="text-lg font-bold text-green-600 tabular-nums">{{ $monthlyCompletedTickets }}</div>
                </div>
            </div>
        </div>

        <!-- Rechte Spalte: SLA Performance -->
        <div class="space-y-4">
            <h3 class="text-sm font-semibold text-gray-900">SLA Performance</h3>

            <div class="grid grid-cols-2 gap-3">
                <div class="bg-white rounded-lg border border-gray-200 p-3">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Überschritten</div>
                    <div class="text-lg font-bold text-red-600 tabular-nums">{{ $slaOverdueTickets }}</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-3">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Gefährdet</div>
                    <div class="text-lg font-bold text-amber-600 tabular-nums">{{ $slaAtRiskTickets }}</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-3">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Offen</div>
                    <div class="text-lg font-bold text-amber-600 tabular-nums">{{ $openTickets }}</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-3">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Erledigt</div>
                    <div class="text-lg font-bold text-green-600 tabular-nums">{{ $completedTickets }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Board-Übersicht -->
    <section class="bg-white rounded-lg border border-gray-200">
        <div class="px-4 py-3 border-b border-gray-200">
            <h3 class="text-sm font-semibold text-gray-900">Meine aktiven Helpdesk Boards</h3>
            <p class="text-[13px] text-gray-400 mt-1">Top 5 Boards nach offenen Tickets</p>
        </div>

        <div class="p-4">
            @if($activeBoardsList->count() > 0)
                <div class="space-y-3">
                    @foreach($activeBoardsList as $board)
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200 hover:bg-emerald-50/50 hover:border-[#049b5c]/30 transition">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-emerald-50 text-[#049b5c]">
                                    <x-heroicon-o-folder class="w-5 h-5"/>
                                </div>
                                <div>
                                    <h4 class="text-[13px] font-medium text-gray-900">{{ $board['name'] }}</h4>
                                    <p class="text-[13px] text-gray-400">
                                        {{ $board['open_tickets'] }} offene von {{ $board['total_tickets'] }} Tickets
                                        @if($board['high_priority'] > 0)
                                            • {{ $board['high_priority'] }} hohe Priorität
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <a href="{{ route('helpdesk.boards.show', $board['id']) }}"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-[#049b5c] text-[#049b5c] bg-emerald-50 hover:bg-emerald-100 transition-colors text-[13px] font-medium"
                               wire:navigate>
                                @svg('heroicon-o-arrow-right', 'w-4 h-4')
                                <span>Öffnen</span>
                            </a>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <x-heroicon-o-folder class="w-12 h-12 text-gray-300 mx-auto mb-4"/>
                    <h4 class="text-[13px] font-medium text-gray-900 mb-2">Keine aktiven Boards</h4>
                    <p class="text-[13px] text-gray-400">Du hast noch keine Helpdesk Boards oder bist in keinem Board zuständig.</p>
                </div>
            @endif
        </div>
    </section>

    </x-ui-page-container>
</x-ui-page>
