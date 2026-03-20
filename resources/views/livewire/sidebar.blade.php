{{-- Helpdesk Sidebar - Organisationsstruktur-Gruppierung --}}
<div>
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
            {{-- Entity Type Gruppen --}}
            @foreach($entityTypeGroups as $typeGroup)
                <x-ui-sidebar-list :label="$typeGroup['type_name']">
                    @foreach($typeGroup['entities'] as $entityGroup)
                        {{-- Entity mit aufklappbaren Boards --}}
                        <div x-data="{ open: localStorage.getItem('helpdesk.entity.' + {{ $entityGroup['entity_id'] }}) === 'true' }"
                             class="flex flex-col">
                            <button type="button"
                                    @click="open = !open; localStorage.setItem('helpdesk.entity.' + {{ $entityGroup['entity_id'] }}, open)"
                                    class="flex items-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition w-full text-left">
                                <span class="w-4 h-4 flex-shrink-0 flex items-center justify-center transition-transform"
                                      :class="open ? 'rotate-90' : ''">
                                    @svg('heroicon-o-chevron-right', 'w-3 h-3')
                                </span>
                                @php $icon = $typeGroup['type_icon'] ?? null; @endphp
                                @if($icon && str_starts_with($icon, 'heroicon-'))
                                    @svg($icon, 'w-4 h-4 flex-shrink-0 ml-1 text-[var(--ui-muted)]')
                                @else
                                    @svg('heroicon-o-rectangle-group', 'w-4 h-4 flex-shrink-0 ml-1 text-[var(--ui-muted)]')
                                @endif
                                <span class="ml-1.5 text-sm font-medium truncate">{{ $entityGroup['entity_name'] }}</span>
                                <span class="ml-auto text-xs text-[var(--ui-muted)]">{{ $entityGroup['boards']->count() }}</span>
                            </button>
                            <div x-show="open" x-collapse class="flex flex-col gap-0.5 pl-4">
                                @foreach($entityGroup['boards'] as $board)
                                    <x-ui-sidebar-item :href="route('helpdesk.boards.show', ['helpdeskBoard' => $board])" :title="$board->name">
                                        @svg('heroicon-o-folder', 'w-5 h-5 flex-shrink-0 text-[var(--ui-secondary)]')
                                        <div class="flex-1 min-w-0 ml-2">
                                            <span class="truncate text-sm font-medium">{{ $board->name }}</span>
                                        </div>
                                    </x-ui-sidebar-item>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </x-ui-sidebar-list>
            @endforeach

            {{-- Unverknüpfte Boards --}}
            @if($unlinkedBoards->isNotEmpty())
                <x-ui-sidebar-list label="Unverknüpft">
                    @foreach($unlinkedBoards as $board)
                        <x-ui-sidebar-item :href="route('helpdesk.boards.show', ['helpdeskBoard' => $board])" :title="$board->name">
                            @svg('heroicon-o-folder', 'w-5 h-5 flex-shrink-0 text-[var(--ui-secondary)]')
                            <div class="flex-1 min-w-0 ml-2">
                                <span class="truncate text-sm font-medium">{{ $board->name }}</span>
                            </div>
                        </x-ui-sidebar-item>
                    @endforeach
                </x-ui-sidebar-list>
            @endif

            {{-- Keine Boards --}}
            @if($entityTypeGroups->isEmpty() && $unlinkedBoards->isEmpty())
                <div class="px-3 py-1 text-xs text-[var(--ui-muted)]">
                    Keine Helpdesk Boards vorhanden
                </div>
            @endif
        </div>
    </div>
</div>
