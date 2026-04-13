{{-- Rekursiver Entity-Knoten für Sidebar-Baum --}}
@props(['node', 'typeIcon' => null, 'depth' => 0])

<div wire:key="entity-{{ $node['entity_id'] }}"
     x-data="{ open: localStorage.getItem('helpdesk.entity.' + {{ $node['entity_id'] }}) === 'true' }"
     class="flex flex-col">
    {{-- Entity-Zeile --}}
    <button type="button"
            @click="open = !open; localStorage.setItem('helpdesk.entity.' + {{ $node['entity_id'] }}, open)"
            class="flex items-center gap-1 py-1 px-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition w-full text-left group">
        <span class="w-3 h-3 flex-shrink-0 flex items-center justify-center transition-transform text-[var(--ui-muted)]"
              :class="open ? 'rotate-90' : ''">
            @svg('heroicon-o-chevron-right', 'w-2.5 h-2.5')
        </span>
        <span class="truncate text-xs font-medium">{{ $node['entity_name'] }}</span>
        <span class="ml-auto text-[10px] tabular-nums text-[var(--ui-muted)] opacity-60">{{ $node['total_boards'] }}</span>
    </button>

    {{-- Aufgeklappter Inhalt --}}
    <div x-show="open" x-collapse class="flex flex-col ml-3 border-l border-[var(--ui-border)]">
        {{-- 1. Eigene Boards --}}
        @foreach($node['boards'] as $board)
            <a wire:key="entity-{{ $node['entity_id'] }}-board-{{ $board->id }}"
               href="{{ route('helpdesk.boards.show', ['helpdeskBoard' => $board]) }}"
               wire:navigate
               title="{{ $board->name }}"
               class="flex items-center gap-1.5 py-0.5 pl-3 pr-2 text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] transition truncate">
                <span class="w-1 h-1 rounded-full flex-shrink-0 bg-[var(--ui-muted)] opacity-40"></span>
                <span class="truncate text-[11px]">{{ $board->name }}</span>
            </a>
        @endforeach

        {{-- 2. Kind-Entities nach Typ gruppiert --}}
        @foreach($node['children_by_type'] as $typeGroup)
            <div wire:key="entity-{{ $node['entity_id'] }}-type-{{ $typeGroup['type_id'] }}"
                 x-data="{ groupOpen: localStorage.getItem('helpdesk.entity.' + {{ $node['entity_id'] }} + '.type.' + {{ $typeGroup['type_id'] }}) !== 'false' }"
                 class="flex flex-col">
                {{-- Typ-Label (nur wenn mehrere Gruppen oder eigene Boards) --}}
                @if($node['children_by_type']->count() > 1 || $node['boards']->isNotEmpty())
                    <button type="button"
                            @click="groupOpen = !groupOpen; localStorage.setItem('helpdesk.entity.' + {{ $node['entity_id'] }} + '.type.' + {{ $typeGroup['type_id'] }}, groupOpen)"
                            class="flex items-center gap-1 mt-1 mb-0.5 pl-2.5 pr-2 w-full text-left group cursor-pointer">
                        <span class="w-2.5 h-2.5 flex-shrink-0 flex items-center justify-center transition-transform text-[var(--ui-muted)] opacity-50"
                              :class="groupOpen ? 'rotate-90' : ''">
                            @svg('heroicon-o-chevron-right', 'w-2 h-2')
                        </span>
                        <span class="text-[9px] uppercase tracking-wider text-[var(--ui-muted)] opacity-60 group-hover:opacity-100 transition-opacity">
                            {{ $typeGroup['type_name'] }}
                        </span>
                    </button>
                @endif
                <div x-show="groupOpen" x-collapse class="flex flex-col">
                    @foreach($typeGroup['children'] as $child)
                        @include('helpdesk::livewire.partials.sidebar-entity-node', [
                            'node' => $child,
                            'typeIcon' => $typeGroup['type_icon'] ?? $typeIcon,
                            'depth' => $depth + 1,
                        ])
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
