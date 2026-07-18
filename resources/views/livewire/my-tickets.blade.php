<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Meine Tickets" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Helpdesk', 'href' => route('helpdesk.dashboard'), 'icon' => 'lifebuoy'],
            ['label' => 'Meine Tickets'],
        ]">
            <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#049b5c] text-white text-[13px] font-medium hover:bg-[#038a52] transition-colors" wire:click="createTicket(null)">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Ticket</span>
            </button>
            <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-colors text-[13px]" wire:click="createTicketGroup">
                @svg('heroicon-o-square-2-stack', 'w-4 h-4')
                <span>Spalte</span>
            </button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true">
            <div class="p-4 space-y-5">
                {{-- Monatliche Performance --}}
                <div>
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-3">Monatliche Performance</div>
                    <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[13px] text-gray-500">Erstellt</span>
                            <span class="text-[13px] font-semibold text-amber-600">{{ $createdPoints }} SP</span>
                        </div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[13px] text-gray-500">Erledigt</span>
                            <span class="text-[13px] font-semibold text-green-600">{{ $donePoints }} SP</span>
                        </div>
                        <div class="border-t border-gray-200 pt-2 mt-2">
                            <div class="flex items-center justify-between">
                                <span class="text-[13px] text-gray-900">Performance-Score</span>
                                @if($monthlyPerformanceScore !== null)
                                    <span class="text-[13px] font-bold {{ $monthlyPerformanceScore >= 1 ? 'text-green-600' : 'text-amber-600' }}">
                                        {{ number_format($monthlyPerformanceScore * 100, 0) }}%
                                    </span>
                                @else
                                    <span class="text-[13px] text-gray-400">-</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Statistiken --}}
                <div>
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-3">Statistiken</div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="bg-white rounded-lg border border-gray-200 p-2.5">
                            <div class="text-[10px] font-medium text-gray-400 uppercase tracking-wide mb-0.5">SP (offen)</div>
                            <div class="text-base font-bold text-amber-600 tabular-nums">{{ $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks)->sum(fn($t) => $t->story_points?->points() ?? 0) }}</div>
                        </div>
                        <div class="bg-white rounded-lg border border-gray-200 p-2.5">
                            <div class="text-[10px] font-medium text-gray-400 uppercase tracking-wide mb-0.5">SP (erledigt)</div>
                            <div class="text-base font-bold text-green-600 tabular-nums">{{ ($groups->first(fn($g)=>($g->isDoneGroup ?? false))?->tasks ?? collect())->sum(fn($t) => $t->story_points?->points() ?? 0) }}</div>
                        </div>
                        <div class="bg-white rounded-lg border border-gray-200 p-2.5">
                            <div class="text-[10px] font-medium text-gray-400 uppercase tracking-wide mb-0.5">Offen</div>
                            <div class="text-base font-bold text-amber-600 tabular-nums">{{ $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->sum(fn($g) => $g->tasks->count()) }}</div>
                        </div>
                        <div class="bg-white rounded-lg border border-gray-200 p-2.5">
                            <div class="text-[10px] font-medium text-gray-400 uppercase tracking-wide mb-0.5">Gesamt</div>
                            <div class="text-base font-bold text-gray-900 tabular-nums">{{ $groups->flatMap(fn($g) => $g->tasks)->count() }}</div>
                        </div>
                        <div class="bg-white rounded-lg border border-gray-200 p-2.5">
                            <div class="text-[10px] font-medium text-gray-400 uppercase tracking-wide mb-0.5">Erledigt</div>
                            <div class="text-base font-bold text-green-600 tabular-nums">{{ ($groups->first(fn($g)=>($g->isDoneGroup ?? false))?->tasks ?? collect())->count() }}</div>
                        </div>
                        <div class="bg-white rounded-lg border border-gray-200 p-2.5">
                            <div class="text-[10px] font-medium text-gray-400 uppercase tracking-wide mb-0.5">Ohne Fälligk.</div>
                            <div class="text-base font-bold text-gray-900 tabular-nums">{{ $groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => !$t->due_date)->count() }}</div>
                        </div>
                        <div class="bg-white rounded-lg border border-gray-200 p-2.5">
                            <div class="text-[10px] font-medium text-gray-400 uppercase tracking-wide mb-0.5">Überfällig</div>
                            <div class="text-base font-bold text-red-600 tabular-nums">{{ $groups->flatMap(fn($g) => $g->tasks)->filter(fn($t) => $t->due_date && $t->due_date->isPast() && !$t->is_done)->count() }}</div>
                        </div>
                    </div>
                </div>

                {{-- Erledigte Tickets --}}
                @php $completedTickets = $groups->filter(fn($g) => $g->isDoneGroup ?? false)->flatMap(fn($g) => $g->tasks); @endphp
                @if($completedTickets->count() > 0)
                    <div>
                        <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-3">Erledigte Tickets ({{ $completedTickets->count() }})</div>
                        <div class="space-y-1 max-h-60 overflow-y-auto">
                            @foreach($completedTickets->take(10) as $ticket)
                                <a href="{{ route('helpdesk.tickets.show', $ticket) }}" class="block p-2 rounded-lg text-[13px] border border-gray-200 bg-gray-50 hover:bg-emerald-50/50 transition" wire:navigate>
                                    <div class="flex items-center gap-2">
                                        <x-heroicon-o-check-circle class="w-4 h-4 text-green-500"/>
                                        <span class="truncate">{{ $ticket->title }}</span>
                                    </div>
                                </a>
                            @endforeach
                            @if($completedTickets->count() > 10)
                                <div class="text-xs text-gray-400 italic text-center">+{{ $completedTickets->count() - 10 }} weitere</div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="text-[13px] text-gray-400 italic">Noch keine erledigten Tickets</div>
                @endif

                {{-- IN ERINNERUNGEN ABONNIEREN (CalDAV) --}}
                <div>
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-3">📅 In Erinnerungen abonnieren</div>
                    <div class="p-3 rounded-lg bg-white border border-gray-200 space-y-2">
                        <p class="text-[11px] text-gray-500 leading-relaxed m-0">
                            Deine Tickets als Liste in Apple Erinnerungen (CalDAV) — eigener Account, schreibgeschützt.
                        </p>

                        @if($newCaldavSecret)
                            <div class="rounded border border-amber-300 bg-amber-50 p-2 space-y-1.5">
                                <p class="text-[10px] font-medium text-amber-900 m-0">Jetzt am iPhone einrichten (Passwort wird nur einmal gezeigt):</p>
                                <div x-data="{ copied: false }" class="flex items-center gap-1">
                                    <code x-ref="hturl" class="flex-1 px-2 py-1 text-[10px] rounded bg-white border border-amber-300 font-mono break-all">{{ $newCaldavUrl }}</code>
                                    <button type="button" @click="navigator.clipboard.writeText($refs.hturl.textContent.trim()); copied=true; setTimeout(()=>copied=false,1500)" class="shrink-0 px-2 py-1 text-[10px] rounded border border-amber-300 text-amber-800">
                                        <span x-show="!copied">URL</span><span x-show="copied" x-cloak>✓</span>
                                    </button>
                                </div>
                                <div x-data="{ copied: false }" class="flex items-center gap-1">
                                    <code x-ref="htsecret" class="flex-1 px-2 py-1 text-[10px] rounded bg-white border border-amber-300 font-mono break-all">{{ $newCaldavSecret }}</code>
                                    <button type="button" @click="navigator.clipboard.writeText($refs.htsecret.textContent.trim()); copied=true; setTimeout(()=>copied=false,1500)" class="shrink-0 px-2 py-1 text-[10px] rounded bg-amber-600 text-white">
                                        <span x-show="!copied">Passwort</span><span x-show="copied" x-cloak>✓</span>
                                    </button>
                                </div>
                                <p class="text-[10px] text-amber-700 m-0">Server = obige URL, Benutzer beliebig, Passwort = das Secret.</p>
                            </div>
                        @endif

                        <div class="flex items-center gap-1">
                            <input type="text" wire:model="caldavName" placeholder="z. B. iPhone" class="flex-1 px-2 py-1 text-[10px] rounded border border-gray-200 bg-white text-gray-700 placeholder:text-gray-400" />
                            <button type="button" wire:click="createCaldavSubscription" class="shrink-0 px-2.5 py-1 text-[10px] font-medium rounded bg-gray-800 text-white hover:bg-gray-700">Abo</button>
                        </div>

                        @foreach($this->caldavSubscriptions() as $sub)
                            <div class="text-[10px]">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-700 font-medium truncate">{{ $sub->name }}</span>
                                    <button type="button" wire:click="revokeCaldavSubscription({{ $sub->id }})" wire:confirm="Abo widerrufen? Geräte verlieren den Zugriff." class="shrink-0 text-red-500 hover:underline">widerrufen</button>
                                </div>
                                <div x-data="{ copied: false }" class="flex items-center gap-1 mt-0.5">
                                    <code x-ref="hu{{ $sub->id }}" class="flex-1 px-2 py-0.5 text-[10px] rounded bg-gray-50 border border-gray-200 text-gray-500 font-mono break-all">{{ $this->caldavUrlFor($sub->handle) }}</code>
                                    <button type="button" @click="navigator.clipboard.writeText($refs.hu{{ $sub->id }}.textContent.trim()); copied=true; setTimeout(()=>copied=false,1500)" class="shrink-0 px-2 py-0.5 rounded border border-gray-200 text-gray-500">
                                        <span x-show="!copied">URL</span><span x-show="copied" x-cloak>✓</span>
                                    </button>
                                </div>
                            </div>
                        @endforeach
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
                        <div class="font-medium text-gray-900 truncate">Meine Tickets geladen</div>
                        <div class="text-gray-400">gerade eben</div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-kanban-container sortable="updateTicketGroupOrder" sortable-group="updateTicketOrder" wire:key="my-tickets-kanban-container">

        {{-- INBOX (nicht sortierbar als Gruppe) --}}
        @php $inbox = $groups->first(fn($g) => ($g->isInbox ?? false)); @endphp
        @if($inbox)
            <x-ui-kanban-column :sortable-id="null" :scrollable="true" :muted="true">
                <x-slot name="title">
                    <span class="flex items-center gap-1.5">
                        {{ $inbox->label ?? 'Posteingang' }}
                        @if(($unreadCount ?? 0) > 0)
                            <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold leading-none text-white bg-red-500 rounded-full">{{ $unreadCount }}</span>
                        @endif
                    </span>
                </x-slot>
                <x-slot name="headerActions">
                    <button
                        wire:click="createTicket(null)"
                        class="text-gray-400 hover:text-gray-700 transition-colors"
                        title="Neues Ticket in INBOX erstellen">
                        @svg('heroicon-o-plus-circle', 'w-4 h-4')
                    </button>
                </x-slot>
                @foreach(($inbox->tasks ?? []) as $ticket)
                    @include('helpdesk::livewire.ticket-preview-card', ['ticket' => $ticket])
                @endforeach
            </x-ui-kanban-column>
        @endif

        {{-- Mittlere Spalten (sortierbar) --}}
        @foreach($groups->filter(fn ($g) => !($g->isDoneGroup ?? false) && !($g->isInbox ?? false)) as $column)
            <x-ui-kanban-column :title="($column->label ?? $column->name ?? 'Spalte')" :sortable-id="$column->id" :scrollable="true">
                <x-slot name="headerActions">
                    <button
                        wire:click="createTicket('{{ $column->id }}')"
                        class="text-gray-400 hover:text-gray-700 transition-colors"
                        title="Neues Ticket in dieser Gruppe erstellen">
                        @svg('heroicon-o-plus-circle', 'w-4 h-4')
                    </button>
                    <button
                        @click="$dispatch('open-modal-ticket-group-settings', { ticketGroupId: {{ $column->id }} })"
                        class="text-gray-400 hover:text-gray-700 transition-colors"
                        title="Gruppen-Einstellungen"
                    >
                        @svg('heroicon-o-cog-6-tooth', 'w-4 h-4')
                    </button>
                </x-slot>
                @foreach(($column->tasks ?? []) as $ticket)
                    @include('helpdesk::livewire.ticket-preview-card', ['ticket' => $ticket])
                @endforeach
            </x-ui-kanban-column>
        @endforeach

        {{-- Erledigt (nicht sortierbar) --}}
        @php $done = $groups->first(fn($g) => ($g->isDoneGroup ?? false)); @endphp
        @if($done)
            <x-ui-kanban-column :title="($done->label ?? 'Erledigt')" :sortable-id="null" :scrollable="true" :muted="true">
                @foreach(($done->tasks ?? []) as $ticket)
                    @include('helpdesk::livewire.ticket-preview-card', ['ticket' => $ticket])
                @endforeach
            </x-ui-kanban-column>
        @endif

    </x-ui-kanban-container>

    <livewire:helpdesk.ticket-group-settings-modal/>

</x-ui-page>
