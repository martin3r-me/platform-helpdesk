{{-- Helpdesk Sidebar - Organisationsstruktur-Gruppierung --}}
<div
    x-data="{
        init() {
            const savedState = localStorage.getItem('helpdesk.showAllBoards');
            if (savedState !== null) {
                @this.set('showAllBoards', savedState === 'true');
            }
        }
    }"
>
    {{-- Modul Header --}}
    <div x-show="!collapsed" class="p-3 text-[11px] font-medium text-gray-400 uppercase tracking-wide border-b border-gray-200 mb-2">
        Helpdesk
    </div>

    {{-- Abschnitt: Allgemein --}}
    <div x-show="!collapsed" class="px-2 py-1">
        <div class="text-[10px] font-medium text-gray-400 uppercase tracking-wide px-2 mb-1">Allgemein</div>
        <a href="{{ route('helpdesk.dashboard') }}" wire:navigate class="flex items-center gap-2 px-2 py-1.5 rounded-md text-gray-700 hover:bg-emerald-50 hover:text-[#049b5c] transition-colors text-[13px]">
            @svg('heroicon-o-chart-bar', 'w-4 h-4 text-gray-400')
            <span>Dashboard</span>
        </a>
        <a href="{{ route('helpdesk.my-tickets') }}" wire:navigate class="flex items-center gap-2 px-2 py-1.5 rounded-md text-gray-700 hover:bg-emerald-50 hover:text-[#049b5c] transition-colors text-[13px]">
            @svg('heroicon-o-home', 'w-4 h-4 text-gray-400')
            <span>Meine Tickets</span>
        </a>
        <a href="{{ route('helpdesk.slas.index') }}" wire:navigate class="flex items-center gap-2 px-2 py-1.5 rounded-md text-gray-700 hover:bg-emerald-50 hover:text-[#049b5c] transition-colors text-[13px]">
            @svg('heroicon-o-clock', 'w-4 h-4 text-gray-400')
            <span>SLA-Verwaltung</span>
        </a>
        <a href="{{ route('helpdesk.health-index') }}" wire:navigate class="flex items-center gap-2 px-2 py-1.5 rounded-md text-gray-700 hover:bg-emerald-50 hover:text-[#049b5c] transition-colors text-[13px]">
            @svg('heroicon-o-heart', 'w-4 h-4 text-gray-400')
            <span>Health-Index</span>
        </a>
    </div>

    {{-- Neues Helpdesk Board --}}
    <div x-show="!collapsed" class="px-2 py-1">
        <button type="button" wire:click="createHelpdeskBoard" class="flex items-center gap-2 px-2 py-1.5 rounded-md text-gray-700 hover:bg-emerald-50 hover:text-[#049b5c] transition-colors text-[13px] w-full text-left">
            @svg('heroicon-o-plus-circle', 'w-4 h-4 text-gray-400')
            <span>Helpdesk Board anlegen</span>
        </button>
    </div>

    {{-- Collapsed: Icons-only für Allgemein --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-gray-200">
        <div class="flex flex-col gap-2">
            <a href="{{ route('helpdesk.dashboard') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-gray-500 hover:bg-emerald-50 hover:text-[#049b5c] transition-colors">
                @svg('heroicon-o-chart-bar', 'w-5 h-5')
            </a>
            <a href="{{ route('helpdesk.my-tickets') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-gray-500 hover:bg-emerald-50 hover:text-[#049b5c] transition-colors">
                @svg('heroicon-o-home', 'w-5 h-5')
            </a>
            <a href="{{ route('helpdesk.slas.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-gray-500 hover:bg-emerald-50 hover:text-[#049b5c] transition-colors">
                @svg('heroicon-o-clock', 'w-5 h-5')
            </a>
        </div>
    </div>
    <div x-show="collapsed" class="px-2 py-2 border-b border-gray-200">
        <button type="button" wire:click="createHelpdeskBoard" class="flex items-center justify-center p-2 rounded-md text-gray-500 hover:bg-emerald-50 hover:text-[#049b5c] transition-colors">
            @svg('heroicon-o-plus-circle', 'w-5 h-5')
        </button>
    </div>

    {{-- Abschnitt: Helpdesk Boards (Entity-basierte Gruppierung) --}}
    <div>
        <div class="mt-2" x-show="!collapsed">
            {{-- Entity Type Gruppen (Baum-Darstellung) --}}
            @foreach($entityTypeGroups as $typeGroup)
                <div wire:key="type-group-{{ $typeGroup['type_id'] }}" class="px-2 py-1">
                    <div class="text-[10px] font-medium text-gray-400 uppercase tracking-wide px-2 mb-1">{{ $typeGroup['type_name'] }}</div>
                    @foreach($typeGroup['entities'] as $entityNode)
                        @include('helpdesk::livewire.partials.sidebar-entity-node', [
                            'node' => $entityNode,
                            'typeIcon' => $typeGroup['type_icon'] ?? null,
                        ])
                    @endforeach
                </div>
            @endforeach

            {{-- Unverknüpfte Boards --}}
            @if($unlinkedBoards->isNotEmpty())
                <div class="px-2 py-1">
                    <div class="text-[10px] font-medium text-gray-400 uppercase tracking-wide px-2 mb-1">Unverknüpft</div>
                    @foreach($unlinkedBoards as $board)
                        <a wire:key="unlinked-board-{{ $board->id }}"
                           href="{{ route('helpdesk.boards.show', ['helpdeskBoard' => $board]) }}"
                           wire:navigate
                           title="{{ $board->name }}"
                           class="flex items-center gap-1.5 py-0.5 pl-3 pr-2 text-gray-600 hover:text-[#049b5c] transition truncate">
                            <span class="w-1 h-1 rounded-full flex-shrink-0 bg-gray-300"></span>
                            <span class="truncate text-[11px]">{{ $board->name }}</span>
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- Button zum Ein-/Ausblenden aller Boards --}}
            @if($hasMoreBoards)
                <div class="px-3 py-2">
                    <button
                        type="button"
                        wire:click="toggleShowAllBoards"
                        x-on:click="localStorage.setItem('helpdesk.showAllBoards', (!$wire.showAllBoards).toString())"
                        class="flex items-center gap-2 text-xs text-gray-400 hover:text-gray-700 transition-colors"
                    >
                        @if($showAllBoards)
                            @svg('heroicon-o-eye-slash', 'w-4 h-4')
                            <span>Nur meine Boards</span>
                        @else
                            @svg('heroicon-o-eye', 'w-4 h-4')
                            <span>Alle Boards anzeigen</span>
                        @endif
                    </button>
                </div>
            @endif

            {{-- Keine Boards --}}
            @if($entityTypeGroups->isEmpty() && $unlinkedBoards->isEmpty())
                <div class="px-3 py-1 text-xs text-gray-400">
                    @if($showAllBoards)
                        Keine Helpdesk Boards vorhanden
                    @else
                        Keine Boards mit Tickets
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
