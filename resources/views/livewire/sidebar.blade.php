{{-- resources/views/vendor/helpdesk/livewire/sidebar-content.blade.php --}}
<div>
    {{-- Modul Header --}}
    <x-sidebar-module-header module-name="Helpdesk" />
    
    {{-- Abschnitt: Allgemein --}}
    <div>
        <h4 x-show="!collapsed" class="p-3 text-sm italic text-secondary uppercase">Allgemein</h4>

        {{-- Dashboard --}}
        <a href="{{ route('helpdesk.dashboard') }}"
           class="relative d-flex items-center p-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname === '/' || 
               window.location.pathname.endsWith('/helpdesk') || 
               window.location.pathname.endsWith('/helpdesk/') ||
               (window.location.pathname.split('/').length === 1 && window.location.pathname === '/')
                   ? 'bg-primary text-on-primary shadow-md'
                   : 'text-black hover:bg-primary-10 hover:text-primary hover:shadow-md',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            <x-heroicon-o-chart-bar class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">Dashboard</span>
        </a>

        {{-- Meine Tickets --}}
        <a href="{{ route('helpdesk.my-tickets') }}"
           class="relative d-flex items-center p-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname.includes('/my-tickets') || 
               window.location.pathname.endsWith('/my-tickets')
                   ? 'bg-primary text-on-primary shadow-md'
                   : 'text-black hover:bg-primary-10 hover:text-primary hover:shadow-md',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            <x-heroicon-o-home class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">Meine Tickets</span>
        </a>

        {{-- SLA-Verwaltung --}}
        <a href="{{ route('helpdesk.slas.index') }}"
           class="relative d-flex items-center p-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname.includes('/slas') || 
               window.location.pathname.endsWith('/slas')
                   ? 'bg-primary text-on-primary shadow-md'
                   : 'text-black hover:bg-primary-10 hover:text-primary hover:shadow-md',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            <x-heroicon-o-clock class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">SLA-Verwaltung</span>
        </a>

        {{-- Helpdesk Board anlegen --}}
        <a href="#"
           class="relative d-flex items-center p-2 my-1 rounded-md font-medium transition"
           :class="collapsed ? 'justify-center' : 'gap-3'"
           wire:click="createHelpdeskBoard">
            <x-heroicon-o-plus class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">Helpdesk Board anlegen</span>
        </a>
    </div>

    {{-- Abschnitt: Helpdesk Boards --}}
    <div x-show="!collapsed">
        <h4 class="p-3 text-sm italic text-secondary uppercase">Helpdesk Boards</h4>

        @foreach($helpdeskBoards as $board)
            <a href="{{ route('helpdesk.boards.show', ['helpdeskBoard' => $board]) }}"
               class="relative d-flex items-center p-2 my-1 rounded-md font-medium transition gap-3"
               :class="[
                   window.location.pathname.includes('/boards/{{ $board->id }}/') || 
                   window.location.pathname.includes('/boards/{{ $board->uuid }}/') ||
                   window.location.pathname.endsWith('/boards/{{ $board->id }}') ||
                   window.location.pathname.endsWith('/boards/{{ $board->uuid }}') ||
                   (window.location.pathname.split('/').length === 2 && window.location.pathname.endsWith('/{{ $board->id }}')) ||
                   (window.location.pathname.split('/').length === 2 && window.location.pathname.endsWith('/{{ $board->uuid }}'))
                       ? 'bg-primary text-on-primary shadow-md'
                       : 'text-black hover:bg-primary-10 hover:text-primary hover:shadow-md'
               ]"
               wire:navigate>
                <x-heroicon-o-folder class="w-6 h-6 flex-shrink-0"/>
                <span class="truncate">{{ $board->name }}</span>
            </a>
        @endforeach
    </div>
</div>
