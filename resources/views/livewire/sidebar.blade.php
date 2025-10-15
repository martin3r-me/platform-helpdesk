{{-- resources/views/vendor/helpdesk/livewire/sidebar-content.blade.php --}}
@php
    if (!isset($helpdeskBoards)) {
        $teamId = auth()->user()?->currentTeam?->id;
        $helpdeskBoards = $teamId
            ? \Platform\Helpdesk\Models\HelpdeskBoard::where('team_id', $teamId)->orderBy('name')->get()
            : collect();
    }
@endphp
<div>
    {{-- Modul Header --}}
    <x-sidebar-module-header module-name="Helpdesk" />
    
    {{-- Abschnitt: Allgemein --}}
    <div>
        <h4 class="px-4 py-3 text-xs tracking-wide font-semibold text-[color:var(--ui-muted)] uppercase">Allgemein</h4>

        {{-- Dashboard --}}
        <a href="{{ route('helpdesk.dashboard') }}"
           class="relative flex items-center px-3 py-2 my-1 rounded-md font-medium transition gap-3"
           :class="(
               window.location.pathname === '/' || 
               window.location.pathname.endsWith('/helpdesk') || 
               window.location.pathname.endsWith('/helpdesk/') ||
               (window.location.pathname.split('/').length === 1 && window.location.pathname === '/')
           )
               ? 'bg-[color:var(--ui-primary)] text-[color:var(--ui-on-primary)] shadow'
               : 'text-[color:var(--ui-secondary)] hover:bg-[color:var(--ui-primary-5)] hover:text-[color:var(--ui-primary)]'"
           wire:navigate>
            <x-heroicon-o-chart-bar class="w-6 h-6 flex-shrink-0"/>
            <span class="truncate">Dashboard</span>
        </a>

        {{-- Meine Tickets --}}
        <a href="{{ route('helpdesk.my-tickets') }}"
           class="relative flex items-center px-3 py-2 my-1 rounded-md font-medium transition gap-3"
           :class="(
               window.location.pathname.includes('/my-tickets') || 
               window.location.pathname.endsWith('/my-tickets')
           )
               ? 'bg-[color:var(--ui-primary)] text-[color:var(--ui-on-primary)] shadow'
               : 'text-[color:var(--ui-secondary)] hover:bg-[color:var(--ui-primary-5)] hover:text-[color:var(--ui-primary)]'"
           wire:navigate>
            <x-heroicon-o-home class="w-6 h-6 flex-shrink-0"/>
            <span class="truncate">Meine Tickets</span>
        </a>

        {{-- SLA-Verwaltung --}}
        <a href="{{ route('helpdesk.slas.index') }}"
           class="relative flex items-center px-3 py-2 my-1 rounded-md font-medium transition gap-3"
           :class="(
               window.location.pathname.includes('/slas') || 
               window.location.pathname.endsWith('/slas')
           )
               ? 'bg-[color:var(--ui-primary)] text-[color:var(--ui-on-primary)] shadow'
               : 'text-[color:var(--ui-secondary)] hover:bg-[color:var(--ui-primary-5)] hover:text-[color:var(--ui-primary)]'"
           wire:navigate>
            <x-heroicon-o-clock class="w-6 h-6 flex-shrink-0"/>
            <span class="truncate">SLA-Verwaltung</span>
        </a>

        {{-- Helpdesk Board anlegen --}}
        <a href="#"
           class="relative flex items-center px-3 py-2 my-1 rounded-md font-medium transition gap-3"
           wire:click="createHelpdeskBoard">
            <x-heroicon-o-plus class="w-6 h-6 flex-shrink-0"/>
            <span class="truncate">Helpdesk Board anlegen</span>
        </a>
    </div>

    {{-- Abschnitt: Helpdesk Boards --}}
    <div>
        <h4 class="px-4 py-3 text-xs tracking-wide font-semibold text-[color:var(--ui-muted)] uppercase">Helpdesk Boards</h4>

        @foreach($helpdeskBoards as $board)
            <a href="{{ route('helpdesk.boards.show', ['helpdeskBoard' => $board]) }}"
               class="relative flex items-center px-3 py-2 my-1 rounded-md font-medium transition gap-3"
               :class="[
                   window.location.pathname.includes('/boards/{{ $board->id }}/') || 
                   window.location.pathname.includes('/boards/{{ $board->uuid }}/') ||
                   window.location.pathname.endsWith('/boards/{{ $board->id }}') ||
                   window.location.pathname.endsWith('/boards/{{ $board->uuid }}') ||
                   (window.location.pathname.split('/').length === 2 && window.location.pathname.endsWith('/{{ $board->id }}')) ||
                   (window.location.pathname.split('/').length === 2 && window.location.pathname.endsWith('/{{ $board->uuid }}'))
                       ? 'bg-[color:var(--ui-primary)] text-[color:var(--ui-on-primary)] shadow'
                       : 'text-[color:var(--ui-secondary)] hover:bg-[color:var(--ui-primary-5)] hover:text-[color:var(--ui-primary)]'
               ]"
               wire:navigate>
                <x-heroicon-o-folder class="w-6 h-6 flex-shrink-0"/>
                <span class="truncate">{{ $board->name }}</span>
            </a>
        @endforeach
    </div>
</div>
