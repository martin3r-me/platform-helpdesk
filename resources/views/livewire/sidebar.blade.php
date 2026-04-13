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
    <div x-show="!collapsed" class="p-3 text-sm italic text-[var(--ui-secondary)] uppercase border-b border-[var(--ui-border)] mb-2">
        Helpdesk
    </div>

    {{-- Abschnitt: Allgemein --}}
    <x-ui-sidebar-list label="Allgemein">
        <x-ui-sidebar-item :href="route('helpdesk.dashboard')">
            @svg('heroicon-o-chart-bar', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Dashboard</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('helpdesk.my-tickets')">
            @svg('heroicon-o-home', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Meine Tickets</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('helpdesk.slas.index')">
            @svg('heroicon-o-clock', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">SLA-Verwaltung</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Neues Helpdesk Board --}}
    <x-ui-sidebar-list>
        <x-ui-sidebar-item wire:click="createHelpdeskBoard">
            @svg('heroicon-o-plus-circle', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Helpdesk Board anlegen</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Collapsed: Icons-only für Allgemein --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[var(--ui-border)]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('helpdesk.dashboard') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
                @svg('heroicon-o-chart-bar', 'w-5 h-5')
            </a>
            <a href="{{ route('helpdesk.my-tickets') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
                @svg('heroicon-o-home', 'w-5 h-5')
            </a>
            <a href="{{ route('helpdesk.slas.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
                @svg('heroicon-o-clock', 'w-5 h-5')
            </a>
        </div>
    </div>
    <div x-show="collapsed" class="px-2 py-2 border-b border-[var(--ui-border)]">
        <button type="button" wire:click="createHelpdeskBoard" class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
            @svg('heroicon-o-plus-circle', 'w-5 h-5')
        </button>
    </div>

    {{-- Abschnitt: Helpdesk Boards (Entity-basierte Gruppierung) --}}
    <div>
        <div class="mt-2" x-show="!collapsed">
            {{-- Entity Type Gruppen (Baum-Darstellung) --}}
            @foreach($entityTypeGroups as $typeGroup)
                <x-ui-sidebar-list wire:key="type-group-{{ $typeGroup['type_id'] }}" :label="$typeGroup['type_name']">
                    @foreach($typeGroup['entities'] as $entityNode)
                        @include('helpdesk::livewire.partials.sidebar-entity-node', [
                            'node' => $entityNode,
                            'typeIcon' => $typeGroup['type_icon'] ?? null,
                        ])
                    @endforeach
                </x-ui-sidebar-list>
            @endforeach

            {{-- Unverknüpfte Boards --}}
            @if($unlinkedBoards->isNotEmpty())
                <x-ui-sidebar-list label="Unverknüpft">
                    @foreach($unlinkedBoards as $board)
                        <a wire:key="unlinked-board-{{ $board->id }}"
                           href="{{ route('helpdesk.boards.show', ['helpdeskBoard' => $board]) }}"
                           wire:navigate
                           title="{{ $board->name }}"
                           class="flex items-center gap-1.5 py-0.5 pl-3 pr-2 text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] transition truncate">
                            <span class="w-1 h-1 rounded-full flex-shrink-0 bg-[var(--ui-muted)] opacity-40"></span>
                            <span class="truncate text-[11px]">{{ $board->name }}</span>
                        </a>
                    @endforeach
                </x-ui-sidebar-list>
            @endif

            {{-- Button zum Ein-/Ausblenden aller Boards --}}
            @if($hasMoreBoards)
                <div class="px-3 py-2">
                    <button
                        type="button"
                        wire:click="toggleShowAllBoards"
                        x-on:click="localStorage.setItem('helpdesk.showAllBoards', (!$wire.showAllBoards).toString())"
                        class="flex items-center gap-2 text-xs text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
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
                <div class="px-3 py-1 text-xs text-[var(--ui-muted)]">
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
