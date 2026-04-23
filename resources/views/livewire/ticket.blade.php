<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="array_filter([
            ['label' => 'Helpdesk', 'href' => route('helpdesk.dashboard'), 'icon' => 'lifebuoy'],
            ['label' => 'Meine Tickets', 'href' => route('helpdesk.my-tickets')],
            $ticket->helpdeskBoard ? ['label' => $ticket->helpdeskBoard->name, 'href' => route('helpdesk.boards.show', $ticket->helpdeskBoard)] : null,
            ['label' => Str::limit($ticket->title, 40)],
        ])">
            @can('update', $ticket)
                @if($this->isDirty())
                    <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#049b5c] text-white text-[13px] font-medium hover:bg-[#038a52] transition-colors" wire:click="save">
                        @svg('heroicon-o-check', 'w-4 h-4')
                        <span>Speichern</span>
                    </button>
                @endif
            @endcan
            @can('delete', $ticket)
                <x-ui-confirm-button
                    action="deleteTicket"
                    text="Löschen"
                    confirmText="Wirklich löschen?"
                    variant="danger"
                    :icon="@svg('heroicon-o-trash', 'w-4 h-4')->toHtml()"
                />
            @endcan
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true" storeKey="sidebarOpen" side="left">
            <div class="p-4 space-y-5">
                {{-- Status --}}
                <div class="space-y-2">
                    @can('update', $ticket)
                        <button type="button" wire:click="toggleDone" class="w-full text-left flex items-center justify-between py-2 px-3 rounded-lg bg-gray-50 border border-gray-200 hover:bg-emerald-50/50 transition-colors cursor-pointer">
                            <div class="flex items-center gap-2">
                                @svg('heroicon-o-check-circle', 'w-4 h-4 text-green-500')
                                <span class="text-[13px] text-gray-700">Status</span>
                            </div>
                            <span class="text-[13px] font-semibold text-gray-900">{{ $ticket->is_done ? 'Erledigt' : 'Offen' }}</span>
                        </button>
                    @else
                        <div class="w-full flex items-center justify-between py-2 px-3 rounded-lg bg-gray-50 border border-gray-200">
                            <div class="flex items-center gap-2">
                                @svg('heroicon-o-check-circle', 'w-4 h-4 text-green-500')
                                <span class="text-[13px] text-gray-700">Status</span>
                            </div>
                            <span class="text-[13px] font-semibold text-gray-900">{{ $ticket->is_done ? 'Erledigt' : 'Offen' }}</span>
                        </div>
                    @endcan

                    {{-- Ticket Sperren/Entsperren --}}
                    @if($ticket->isLocked())
                        @can('unlock', $ticket)
                            <button type="button" wire:click="unlockTicket" class="w-full text-left flex items-center justify-between py-2 px-3 rounded-lg bg-gray-50 border border-gray-200 hover:bg-emerald-50/50 transition-colors cursor-pointer">
                                <div class="flex items-center gap-2">
                                    @svg('heroicon-o-lock-closed', 'w-4 h-4 text-amber-500')
                                    <span class="text-[13px] text-gray-700">Sperre</span>
                                </div>
                                <span class="text-[13px] font-semibold text-amber-600">Gesperrt</span>
                            </button>
                        @else
                            <div class="w-full flex items-center justify-between py-2 px-3 rounded-lg bg-gray-50 border border-gray-200">
                                <div class="flex items-center gap-2">
                                    @svg('heroicon-o-lock-closed', 'w-4 h-4 text-amber-500')
                                    <span class="text-[13px] text-gray-700">Sperre</span>
                                </div>
                                <span class="text-[13px] font-semibold text-amber-600">Gesperrt</span>
                            </div>
                        @endcan
                    @else
                        @can('lock', $ticket)
                            <button type="button" wire:click="lockTicket" class="w-full text-left flex items-center justify-between py-2 px-3 rounded-lg bg-gray-50 border border-gray-200 hover:bg-emerald-50/50 transition-colors cursor-pointer">
                                <div class="flex items-center gap-2">
                                    @svg('heroicon-o-lock-open', 'w-4 h-4 text-gray-400')
                                    <span class="text-[13px] text-gray-700">Sperre</span>
                                </div>
                                <span class="text-[13px] font-semibold text-gray-900">Offen</span>
                            </button>
                        @endcan
                    @endif
                </div>

                {{-- Ticket Info --}}
                <div>
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-3">Ticket Info</div>
                    <div class="space-y-2 text-[13px]">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Erstellt:</span>
                            <span class="font-medium text-gray-900">{{ $ticket->created_at->format('d.m.Y H:i') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Aktualisiert:</span>
                            <span class="font-medium text-gray-900">{{ $ticket->updated_at->format('d.m.Y H:i') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Erstellt von:</span>
                            <span class="font-medium text-gray-900">{{ $ticket->user?->name ?? 'Unbekannt' }}</span>
                        </div>
                        @if($ticket->userInCharge)
                            <div class="flex justify-between">
                                <span class="text-gray-400">Zugewiesen an:</span>
                                <span class="font-medium text-gray-900">{{ $ticket->userInCharge->name }}</span>
                            </div>
                        @endif
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
                        <div class="font-medium text-gray-900 truncate">Ticket geladen</div>
                        <div class="text-gray-400">gerade eben</div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container spacing="space-y-6">
        {{-- Header Block --}}
        <section class="bg-white rounded-lg border border-gray-200 overflow-hidden {{ $ticket->isLocked() ? 'border-amber-300 bg-amber-50' : '' }}">
            <div class="py-4 px-6">
                @if($ticket->isLocked())
                    <div class="mb-3 flex items-center gap-2 px-3 py-1.5 rounded-lg bg-amber-100 border border-amber-200 max-h-9">
                        @svg('heroicon-o-lock-closed', 'w-4 h-4 text-amber-600')
                        <span class="text-xs font-medium text-amber-700">
                            Ticket ist gesperrt
                            @if($ticket->lockedByUser)
                                (von {{ $ticket->lockedByUser->name }})
                            @endif
                            @if($ticket->locked_at)
                                am {{ $ticket->locked_at->format('d.m.Y H:i') }}
                            @endif
                        </span>
                    </div>
                @endif
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <h1 class="text-xl font-semibold text-gray-900 mb-2 tracking-tight">{{ $ticket->title }}</h1>
                        <div class="flex flex-wrap items-center gap-5 text-[13px] text-gray-500">
                            @if($ticket->helpdeskBoard)
                                <span class="flex items-center gap-1.5">
                                    @svg('heroicon-o-rectangle-stack', 'w-4 h-4')
                                    {{ $ticket->helpdeskBoard->name }}
                                </span>
                            @endif
                            @if($ticket->userInCharge)
                                <span class="flex items-center gap-1.5">
                                    @svg('heroicon-o-user', 'w-4 h-4')
                                    {{ $ticket->userInCharge->name }}
                                </span>
                            @endif
                            @if($ticket->due_date)
                                @php
                                    $isOverdue = $ticket->due_date->isPast() && !$ticket->is_done;
                                    $isToday = $ticket->due_date->isToday();
                                    $isTomorrow = $ticket->due_date->isTomorrow();
                                    $dueDateColor = $isOverdue ? 'text-red-600' : ($isToday || $isTomorrow ? 'text-amber-600' : '');
                                @endphp
                                <span class="flex items-center gap-1.5 {{ $dueDateColor }}">
                                    @svg('heroicon-o-calendar', 'w-4 h-4')
                                    {{ $ticket->due_date->format('d.m.Y H:i') }}
                                </span>
                            @endif
                            @if($ticket->story_points)
                                <span class="flex items-center gap-1.5">
                                    @svg('heroicon-o-sparkles', 'w-4 h-4')
                                    {{ $ticket->story_points->points() ?? $ticket->story_points }} SP
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        @if($ticket->is_done)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Erledigt</span>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        {{-- Ticket Details --}}
        <section class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-900">Grunddaten</h3>
            </div>
            <div class="p-4">
            <x-ui-form-grid :cols="2" :gap="6">
                        @can('update', $ticket)
                            <div>
                                <label for="ticket.title" class="block text-[11px] font-medium text-gray-500 mb-1">Ticket-Titel</label>
                                <input type="text" id="ticket.title" name="ticket.title"
                                    wire:model.live.debounce.500ms="ticket.title"
                                    placeholder="Ticket-Titel eingeben..."
                                    required
                                    @if($this->isLockedForCurrentUser()) disabled @endif
                                    class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c] transition-colors disabled:bg-gray-50 disabled:text-gray-400"
                                >
                                @error('ticket.title')
                                    <span class="text-xs text-red-600 mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        @else
                            <div>
                                <label class="block text-[11px] font-medium text-gray-500 mb-1">Ticket-Titel</label>
                                <div class="p-3 bg-gray-50 rounded-md border border-gray-200 text-[13px] text-gray-900">{{ $ticket->title }}</div>
                            </div>
                        @endcan

                @can('update', $ticket)
                    <div>
                        <label for="ticket.user_in_charge_id" class="block text-[11px] font-medium text-gray-500 mb-1">Zugewiesen an</label>
                        <select id="ticket.user_in_charge_id" name="ticket.user_in_charge_id"
                            wire:model.live="ticket.user_in_charge_id"
                            @if($this->isLockedForCurrentUser()) disabled @endif
                            class="w-full appearance-none px-3 py-2 pr-10 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg+xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22+viewBox%3D%220+0+20+20%22+fill%3D%22%236b7280%22%3E%3Cpath+fill-rule%3D%22evenodd%22+d%3D%22M5.23+7.21a.75.75+0+011.06.02L10+11.168l3.71-3.938a.75.75+0+111.08+1.04l-4.25+4.5a.75.75+0+01-1.08+0l-4.25-4.5a.75.75+0+01.02-1.06z%22+clip-rule%3D%22evenodd%22%2F%3E%3C%2Fsvg%3E')] bg-[length:20px_20px] bg-[position:right_8px_center] bg-no-repeat focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c] transition-colors disabled:bg-gray-50 disabled:text-gray-400"
                        >
                            <option value="">– Niemand zugewiesen –</option>
                            @foreach($teamUsers as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div>
                        <label class="block text-[11px] font-medium text-gray-500 mb-1">Zugewiesen an</label>
                        <div class="p-3 bg-gray-50 rounded-md border border-gray-200 text-[13px] text-gray-900">
                            {{ $ticket->userInCharge?->name ?? 'Niemand zugewiesen' }}
                        </div>
                    </div>
                @endcan
            </x-ui-form-grid>

            <div class="mt-6">
                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-2">Anmerkungen</div>
                        @can('update', $ticket)
                            <div wire:ignore.self
                                x-data="{
                                    autoGrow(el) {
                                        el.style.height = 'auto';
                                        el.style.height = (el.scrollHeight) + 'px';
                                    }
                                }"
                            >
                                <label for="ticket.notes" class="block text-[11px] font-medium text-gray-500 mb-1">Anmerkung</label>
                                <textarea
                                    id="ticket.notes"
                                    name="ticket.notes"
                                    rows="4"
                                    wire:model.live.debounce.500ms="ticket.notes"
                                    placeholder="Anmerkung eingeben..."
                                    @if($this->isLockedForCurrentUser()) disabled @endif
                                    x-init="autoGrow($el)"
                                    @input="autoGrow($el)"
                                    @focus="autoGrow($el)"
                                    class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c] transition-colors disabled:bg-gray-50 disabled:text-gray-400"
                                    style="resize: vertical; min-height: 100px;"
                                ></textarea>
                                @error('ticket.notes')
                                    <span class="text-xs text-red-600 mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        @else
                            <div>
                                <label class="block text-[11px] font-medium text-gray-500 mb-1">Anmerkung</label>
                                <div class="p-3 bg-gray-50 rounded-md border border-gray-200 text-[13px] text-gray-900 whitespace-pre-wrap">{{ $ticket->notes ?: 'Keine Anmerkung vorhanden' }}</div>
                            </div>
                        @endcan
                    </div>
            </div>
        </section>

        {{-- Definition of Done (DoD) --}}
        <section class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-900">Definition of Done (DoD)</h3>
            </div>
            <div class="p-4">
            @php
                $dodProgress = $ticket->dod_progress;
                $dod = $ticket->dod ?? [];
            @endphp

            {{-- Fortschrittsanzeige --}}
            @if(count($dod) > 0)
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[13px] font-medium text-gray-900">Fortschritt</span>
                        <span class="text-[13px] font-semibold text-[#049b5c]">
                            {{ $dodProgress['completed'] }} / {{ $dodProgress['total'] }} ({{ $dodProgress['percentage'] }}%)
                        </span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
                        <div
                            class="h-2.5 rounded-full transition-all duration-300 {{ $dodProgress['percentage'] === 100 ? 'bg-green-500' : 'bg-[#049b5c]' }}"
                            style="width: {{ $dodProgress['percentage'] }}%"
                        ></div>
                    </div>
                </div>
            @endif

            {{-- DoD-Liste --}}
            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-3">Checkliste</div>
            <div class="space-y-2">
                @forelse($dod as $index => $item)
                    <div class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 bg-white hover:border-[#049b5c]/30 transition-colors group {{ ($item['checked'] ?? false) ? 'bg-green-50 border-green-200' : '' }}">
                        @can('update', $ticket)
                            <button
                                type="button"
                                wire:click="toggleDodItem({{ $index }})"
                                class="flex-shrink-0 mt-0.5 w-5 h-5 rounded border-2 flex items-center justify-center transition-colors {{ ($item['checked'] ?? false) ? 'bg-green-500 border-green-500 text-white' : 'border-gray-300 hover:border-[#049b5c]' }}"
                                :disabled="$this->isLockedForCurrentUser()"
                            >
                                @if($item['checked'] ?? false)
                                    @svg('heroicon-o-check', 'w-3.5 h-3.5')
                                @endif
                            </button>
                        @else
                            <div class="flex-shrink-0 mt-0.5 w-5 h-5 rounded border-2 flex items-center justify-center {{ ($item['checked'] ?? false) ? 'bg-green-500 border-green-500 text-white' : 'border-gray-300' }}">
                                @if($item['checked'] ?? false)
                                    @svg('heroicon-o-check', 'w-3.5 h-3.5')
                                @endif
                            </div>
                        @endcan

                        <span class="flex-1 text-[13px] {{ ($item['checked'] ?? false) ? 'text-gray-400 line-through' : 'text-gray-900' }}">
                            {{ $item['text'] ?? '' }}
                        </span>

                        @can('update', $ticket)
                            @if(!$this->isLockedForCurrentUser())
                                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button
                                        type="button"
                                        wire:click="moveDodItem({{ $index }}, 'up')"
                                        class="p-1 text-gray-400 hover:text-gray-700 rounded hover:bg-gray-100"
                                        title="Nach oben"
                                        @if($index === 0) disabled @endif
                                    >
                                        @svg('heroicon-o-chevron-up', 'w-4 h-4')
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="moveDodItem({{ $index }}, 'down')"
                                        class="p-1 text-gray-400 hover:text-gray-700 rounded hover:bg-gray-100"
                                        title="Nach unten"
                                        @if($index === count($dod) - 1) disabled @endif
                                    >
                                        @svg('heroicon-o-chevron-down', 'w-4 h-4')
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="removeDodItem({{ $index }})"
                                        wire:confirm="DoD-Eintrag wirklich löschen?"
                                        class="p-1 text-gray-400 hover:text-red-600 rounded hover:bg-red-50"
                                        title="Löschen"
                                    >
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </button>
                                </div>
                            @endif
                        @endcan
                    </div>
                @empty
                    <div class="text-center py-6 text-[13px] text-gray-400">
                        Noch keine Definition of Done vorhanden.
                    </div>
                @endforelse
            </div>

            {{-- Neuen Eintrag hinzufügen --}}
            @can('update', $ticket)
                @if(!$this->isLockedForCurrentUser())
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <div class="flex gap-2">
                            <input
                                type="text"
                                wire:model="newDodItem"
                                wire:keydown.enter="addDodItem"
                                placeholder="Neuen DoD-Punkt hinzufügen..."
                                class="flex-1 px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c] transition-colors"
                            >
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#049b5c] text-white text-[13px] font-medium hover:bg-[#038a52] transition-colors"
                                wire:click="addDodItem"
                            >
                                @svg('heroicon-o-plus', 'w-4 h-4')
                                Hinzufügen
                            </button>
                        </div>
                    </div>
                @endif
            @endcan
            </div>
        </section>

        {{-- Metadaten --}}
        <section class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-900">Priorität</h3>
            </div>
            <div class="p-4">
            <x-ui-form-grid :cols="2" :gap="6">
                            @can('update', $ticket)
                                <div>
                                    <label for="ticket.priority" class="block text-[11px] font-medium text-gray-500 mb-1">Priorität</label>
                                    <select id="ticket.priority" name="ticket.priority"
                                        wire:model.live="ticket.priority"
                                        @if($this->isLockedForCurrentUser()) disabled @endif
                                        class="w-full appearance-none px-3 py-2 pr-10 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg+xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22+viewBox%3D%220+0+20+20%22+fill%3D%22%236b7280%22%3E%3Cpath+fill-rule%3D%22evenodd%22+d%3D%22M5.23+7.21a.75.75+0+011.06.02L10+11.168l3.71-3.938a.75.75+0+111.08+1.04l-4.25+4.5a.75.75+0+01-1.08+0l-4.25-4.5a.75.75+0+01.02-1.06z%22+clip-rule%3D%22evenodd%22%2F%3E%3C%2Fsvg%3E')] bg-[length:20px_20px] bg-[position:right_8px_center] bg-no-repeat focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c] transition-colors disabled:bg-gray-50 disabled:text-gray-400"
                                    >
                                        <option value="">– Keine Priorität –</option>
                                        @foreach(\Platform\Helpdesk\Enums\TicketPriority::cases() as $priority)
                                            <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @else
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Priorität</label>
                                    <div class="p-3 bg-gray-50 rounded-md border border-gray-200 text-[13px] text-gray-900">{{ $ticket->priority?->label() ?? '–' }}</div>
                                </div>
                            @endcan

            </x-ui-form-grid>

            {{-- Story Points & Fälligkeitsdatum --}}
            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-2 mt-6">Planung</div>
            <x-ui-form-grid :cols="2" :gap="6">
                            @can('update', $ticket)
                                <div>
                                    <label for="ticket.story_points" class="block text-[11px] font-medium text-gray-500 mb-1">Story Points</label>
                                    <select id="ticket.story_points" name="ticket.story_points"
                                        wire:model.live="ticket.story_points"
                                        @if($this->isLockedForCurrentUser()) disabled @endif
                                        class="w-full appearance-none px-3 py-2 pr-10 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg+xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22+viewBox%3D%220+0+20+20%22+fill%3D%22%236b7280%22%3E%3Cpath+fill-rule%3D%22evenodd%22+d%3D%22M5.23+7.21a.75.75+0+011.06.02L10+11.168l3.71-3.938a.75.75+0+111.08+1.04l-4.25+4.5a.75.75+0+01-1.08+0l-4.25-4.5a.75.75+0+01.02-1.06z%22+clip-rule%3D%22evenodd%22%2F%3E%3C%2Fsvg%3E')] bg-[length:20px_20px] bg-[position:right_8px_center] bg-no-repeat focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c] transition-colors disabled:bg-gray-50 disabled:text-gray-400"
                                    >
                                        <option value="">– Kein Wert –</option>
                                        @foreach(\Platform\Helpdesk\Enums\TicketStoryPoints::cases() as $sp)
                                            <option value="{{ $sp->value }}">{{ $sp->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @else
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Story Points</label>
                                    <div class="p-3 bg-gray-50 rounded-md border border-gray-200 text-[13px] text-gray-900">{{ $ticket->story_points?->label() ?? '–' }}</div>
                                </div>
                            @endcan

                            @can('update', $ticket)
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-500 mb-1">
                                        Fälligkeitsdatum
                                    </label>
                                    <button
                                        type="button"
                                        wire:click="openDueDateModal"
                                        class="w-full px-3 py-2 text-left bg-white border border-gray-300 rounded-md hover:border-[#049b5c] hover:bg-emerald-50/50 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c] flex items-center justify-between group"
                                    >
                                        <span class="flex items-center gap-2 text-[13px] text-gray-900">
                                            @svg('heroicon-o-calendar', 'w-4 h-4 text-gray-400 group-hover:text-[#049b5c] transition-colors')
                                            @if($ticket->due_date)
                                                <span class="font-medium">{{ $ticket->due_date->format('d.m.Y H:i') }}</span>
                                            @else
                                                <span class="text-gray-400">Kein Datum gesetzt</span>
                                            @endif
                                        </span>
                                        @svg('heroicon-o-chevron-right', 'w-4 h-4 text-gray-400 group-hover:text-[#049b5c] transition-colors')
                                    </button>
                                </div>
                            @else
                                <div>
                                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Fälligkeitsdatum</label>
                                    <div class="p-3 bg-gray-50 rounded-md border border-gray-200 text-[13px] text-gray-900">
                                        {{ $ticket->due_date ? $ticket->due_date->format('d.m.Y H:i') : '–' }}
                                    </div>
                                </div>
                            @endcan
            </x-ui-form-grid>
            </div>
        </section>

        <x-core-extra-fields-section
            :definitions="$this->extraFieldDefinitions"
            :model="$ticket"
        />

        {{-- GitHub Repositories (collapsible) --}}
        @if($linkedGithubRepositories->count() > 0 || $availableGithubRepositories->count() > 0 || !empty($githubRepositorySearch))
            <div x-data="{ open: localStorage.getItem('ticket-repos-{{ $ticket->id }}') === 'true' }"
                 x-effect="localStorage.setItem('ticket-repos-{{ $ticket->id }}', open)"
                 class="bg-white rounded-lg border border-gray-200">
                <button @click="open = !open" class="w-full flex items-center justify-between p-4 text-left hover:bg-gray-50 rounded-lg transition-colors">
                    <span class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                        @svg('heroicon-o-code-bracket', 'w-4 h-4')
                        GitHub Repositories
                        @if($linkedGithubRepositories->count() > 0)
                            <span class="text-xs font-normal text-gray-400">({{ $linkedGithubRepositories->count() }})</span>
                        @endif
                    </span>
                    <svg :class="open && 'rotate-180'" class="w-4 h-4 text-gray-400 transition-transform duration-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                </button>
                <div x-show="open" x-collapse>
                    <div class="px-4 pb-4">
                        {{-- Verknüpfte Repositories --}}
                        @if($linkedGithubRepositories->count() > 0)
                            <div class="mb-6">
                                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-3">Verknüpfte Repositories</div>
                                <div class="space-y-2">
                                    @foreach($linkedGithubRepositories as $repo)
                                        <div class="flex items-center justify-between p-3 bg-gray-50 border border-gray-200 rounded-lg">
                                            <div class="flex items-center gap-3 flex-1 min-w-0">
                                                <div class="flex-shrink-0 flex items-center justify-center w-10 h-10 rounded-lg bg-gray-100">
                                                    @svg('heroicon-o-code-bracket', 'w-5 h-5 text-gray-700')
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <a href="{{ $repo->url }}" target="_blank" class="block hover:text-[#049b5c] transition-colors">
                                                        <h4 class="text-[13px] font-semibold text-gray-900 truncate">{{ $repo->full_name }}</h4>
                                                    </a>
                                                    @if($repo->description)
                                                        <p class="text-xs text-gray-400 mt-0.5 line-clamp-1">{{ $repo->description }}</p>
                                                    @endif
                                                    <div class="flex items-center gap-3 mt-1 text-xs text-gray-400">
                                                        @if($repo->language)
                                                            <span class="inline-flex items-center gap-1">
                                                                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                                                {{ $repo->language }}
                                                            </span>
                                                        @endif
                                                        <span class="inline-flex items-center gap-1">
                                                            @svg('heroicon-o-star', 'w-3 h-3')
                                                            {{ $repo->stars_count }}
                                                        </span>
                                                        @if($repo->is_private)
                                                            <span class="inline-flex items-center gap-1 text-orange-600">
                                                                @svg('heroicon-o-lock-closed', 'w-3 h-3')
                                                                Privat
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            @can('update', $ticket)
                                                <button type="button"
                                                    class="inline-flex items-center gap-1 px-2 py-1 rounded-md border border-red-300 text-red-600 text-xs font-medium hover:bg-red-50 transition-colors"
                                                    wire:click="detachGithubRepository({{ $repo->id }})"
                                                    wire:confirm="Repository wirklich vom Ticket trennen?"
                                                >
                                                    @svg('heroicon-o-x-mark', 'w-3.5 h-3.5')
                                                </button>
                                            @endcan
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Verfügbare Repositories zum Verknüpfen --}}
                        @if($availableGithubRepositories->count() > 0 || !empty($githubRepositorySearch))
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Repository verknüpfen</div>
                                </div>

                                {{-- Suchfeld --}}
                                <div class="mb-3">
                                    <label for="githubRepositorySearch" class="block text-[11px] font-medium text-gray-500 mb-1">Repository suchen</label>
                                    <input type="text" id="githubRepositorySearch" name="githubRepositorySearch"
                                        wire:model.live.debounce.300ms="githubRepositorySearch"
                                        placeholder="Nach Name, Beschreibung oder Owner suchen..."
                                        class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c] transition-colors"
                                    >
                                </div>

                                @if($availableGithubRepositories->count() > 0)
                                    <div class="space-y-2">
                                    @foreach($availableGithubRepositories as $repo)
                                        <div class="flex items-center justify-between p-3 bg-white border border-gray-200 rounded-lg hover:border-[#049b5c]/60 transition-colors">
                                            <div class="flex items-center gap-3 flex-1 min-w-0">
                                                <div class="flex-shrink-0 flex items-center justify-center w-10 h-10 rounded-lg bg-gray-100">
                                                    @svg('heroicon-o-code-bracket', 'w-5 h-5 text-gray-700')
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <a href="{{ $repo->url }}" target="_blank" class="block hover:text-[#049b5c] transition-colors">
                                                        <h4 class="text-[13px] font-semibold text-gray-900 truncate">{{ $repo->full_name }}</h4>
                                                    </a>
                                                    @if($repo->description)
                                                        <p class="text-xs text-gray-400 mt-0.5 line-clamp-1">{{ $repo->description }}</p>
                                                    @endif
                                                    <div class="flex items-center gap-3 mt-1 text-xs text-gray-400">
                                                        @if($repo->language)
                                                            <span class="inline-flex items-center gap-1">
                                                                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                                                {{ $repo->language }}
                                                            </span>
                                                        @endif
                                                        <span class="inline-flex items-center gap-1">
                                                            @svg('heroicon-o-star', 'w-3 h-3')
                                                            {{ $repo->stars_count }}
                                                        </span>
                                                        @if($repo->is_private)
                                                            <span class="inline-flex items-center gap-1 text-orange-600">
                                                                @svg('heroicon-o-lock-closed', 'w-3 h-3')
                                                                Privat
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            @can('update', $ticket)
                                                <button type="button"
                                                    class="inline-flex items-center gap-1 px-2 py-1 rounded-md border border-[#049b5c] text-[#049b5c] text-xs font-medium hover:bg-emerald-50 transition-colors"
                                                    wire:click="attachGithubRepository({{ $repo->id }})"
                                                >
                                                    @svg('heroicon-o-plus', 'w-3.5 h-3.5')
                                                    Verknüpfen
                                                </button>
                                            @endcan
                                        </div>
                                    @endforeach
                                    </div>
                                @elseif(!empty($githubRepositorySearch))
                                    <div class="p-4 text-center text-[13px] text-gray-400 bg-gray-50 border border-gray-200 rounded-lg">
                                        Keine Repositories gefunden für "{{ $githubRepositorySearch }}"
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

    </x-ui-page-container>

    <!-- Due Date Modal -->
    <x-ui-modal size="md" wire:model="dueDateModalShow" :backdropClosable="true" :escClosable="true">
        <x-slot name="header">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center">
                        @svg('heroicon-o-calendar', 'w-5 h-5 text-[#049b5c]')
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Fälligkeitsdatum</h3>
                    <p class="text-[13px] text-gray-400">Datum und Uhrzeit festlegen</p>
                </div>
            </div>
        </x-slot>

        <div class="space-y-6">
            <!-- Kalender Navigation -->
            <div class="flex items-center justify-between">
                <h2 class="flex-auto text-sm font-semibold text-gray-900">
                    {{ $this->calendarMonthName }}
                </h2>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        wire:click="previousMonth"
                        class="p-1.5 rounded-md text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-colors"
                    >
                        <span class="sr-only">Vorheriger Monat</span>
                        @svg('heroicon-o-chevron-left', 'w-5 h-5')
                    </button>
                    <button
                        type="button"
                        wire:click="nextMonth"
                        class="p-1.5 rounded-md text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-colors"
                    >
                        <span class="sr-only">Nächster Monat</span>
                        @svg('heroicon-o-chevron-right', 'w-5 h-5')
                    </button>
                </div>
            </div>

            <!-- Wochentage Header -->
            <div class="grid grid-cols-7 text-center text-xs font-medium text-gray-400">
                <div>Mo</div>
                <div>Di</div>
                <div>Mi</div>
                <div>Do</div>
                <div>Fr</div>
                <div>Sa</div>
                <div>So</div>
            </div>

            <!-- Kalender Grid -->
            <div class="grid grid-cols-7 gap-1 text-sm">
                @foreach($this->calendarDays as $day)
                    <div class="py-2 {{ !$loop->first ? 'border-t border-gray-100' : '' }}">
                        <button
                            type="button"
                            wire:click="selectDate('{{ $day['date'] }}')"
                            class="mx-auto flex w-8 h-8 items-center justify-center rounded-full transition-all duration-200
                                {{ !$day['isCurrentMonth'] ? 'text-gray-300' : '' }}
                                {{ $day['isCurrentMonth'] && !$day['isToday'] && !$day['isSelected'] ? 'text-gray-900 hover:bg-emerald-50 hover:text-[#049b5c]' : '' }}
                                {{ $day['isToday'] && !$day['isSelected'] ? 'font-semibold text-[#049b5c]' : '' }}
                                {{ $day['isSelected'] && !$day['isToday'] ? 'font-semibold text-white bg-gray-900' : '' }}
                                {{ $day['isSelected'] && $day['isToday'] ? 'font-semibold text-white bg-[#049b5c]' : '' }}
                            "
                        >
                            <time datetime="{{ $day['date'] }}">{{ $day['day'] }}</time>
                        </button>
                    </div>
                @endforeach
            </div>

            <!-- Zeitauswahl -->
            <div class="pt-4 border-t border-gray-200">
                <label class="block text-[11px] font-medium text-gray-500 mb-3">
                    Uhrzeit
                </label>

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div>
                            <label class="block text-[11px] font-medium text-gray-400 mb-1">Stunde</label>
                            <select
                                wire:model.live="selectedHour"
                                class="w-28 appearance-none px-3 py-2 pr-10 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg+xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22+viewBox%3D%220+0+20+20%22+fill%3D%22%236b7280%22%3E%3Cpath+fill-rule%3D%22evenodd%22+d%3D%22M5.23+7.21a.75.75+0+011.06.02L10+11.168l3.71-3.938a.75.75+0+111.08+1.04l-4.25+4.5a.75.75+0+01-1.08+0l-4.25-4.5a.75.75+0+01.02-1.06z%22+clip-rule%3D%22evenodd%22%2F%3E%3C%2Fsvg%3E')] bg-[length:20px_20px] bg-[position:right_8px_center] bg-no-repeat focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c]"
                            >
                                @for($h = 0; $h < 24; $h++)
                                    <option value="{{ $h }}">{{ str_pad($h, 2, '0', STR_PAD_LEFT) }}</option>
                                @endfor
                            </select>
                        </div>

                        <div class="text-2xl font-bold text-gray-400">:</div>

                        <div>
                            <label class="block text-[11px] font-medium text-gray-400 mb-1">Minute</label>
                            <select
                                wire:model.live="selectedMinute"
                                class="w-28 appearance-none px-3 py-2 pr-10 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg+xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22+viewBox%3D%220+0+20+20%22+fill%3D%22%236b7280%22%3E%3Cpath+fill-rule%3D%22evenodd%22+d%3D%22M5.23+7.21a.75.75+0+011.06.02L10+11.168l3.71-3.938a.75.75+0+111.08+1.04l-4.25+4.5a.75.75+0+01-1.08+0l-4.25-4.5a.75.75+0+01.02-1.06z%22+clip-rule%3D%22evenodd%22%2F%3E%3C%2Fsvg%3E')] bg-[length:20px_20px] bg-[position:right_8px_center] bg-no-repeat focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c]"
                            >
                                @foreach([0, 15, 30, 45] as $minute)
                                    <option value="{{ $minute }}">{{ str_pad($minute, 2, '0', STR_PAD_LEFT) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="sm:text-right">
                        <span class="inline-flex items-center gap-2 px-3 py-2 text-[13px] font-semibold text-[#049b5c] bg-emerald-50 rounded-lg border border-[#049b5c]/20">
                            @svg('heroicon-o-clock', 'w-4 h-4 text-[#049b5c]')
                            {{ sprintf('%02d:%02d', $selectedHour, $selectedMinute) }} Uhr
                        </span>
                    </div>
                </div>
            </div>

            <!-- Aktuelles Datum Anzeige -->
            @if($selectedDate)
                <div class="pt-4 border-t border-gray-200">
                    <div class="flex items-center gap-2 text-[13px] text-gray-400">
                        @svg('heroicon-o-calendar-days', 'w-4 h-4')
                        <span>
                            Ausgewählt:
                            <span class="font-medium text-gray-900">
                                {{ \Carbon\Carbon::parse($selectedDate)->locale('de')->isoFormat('dddd, D. MMMM YYYY') }}
                                @if($selectedTime)
                                    um {{ $selectedTime }} Uhr
                                @endif
                            </span>
                        </span>
                    </div>
                </div>
            @endif

            <!-- Entfernen Button -->
            @if($ticket->due_date)
                <div class="pt-4 border-t border-gray-200">
                    <button
                        type="button"
                        class="w-full inline-flex items-center justify-center gap-2 px-3 py-1.5 rounded-md border border-red-300 text-red-600 text-[13px] font-medium hover:bg-red-50 transition-colors"
                        wire:click="clearDueDate"
                    >
                        @svg('heroicon-o-trash', 'w-4 h-4')
                        Datum entfernen
                    </button>
                </div>
            @endif
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-[13px] font-medium hover:bg-gray-50 transition-colors" wire:click="closeDueDateModal">
                    Abbrechen
                </button>
                <button
                    type="button"
                    wire:click="saveDueDate"
                    wire:loading.attr="disabled"
                    wire:target="saveDueDate"
                    wire:disabled="!selectedDate"
                    class="inline-flex items-center gap-2 px-4 py-2 text-[13px] font-medium rounded-md bg-[#049b5c] text-white hover:bg-[#038a52] focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
                >
                    <span wire:loading.remove wire:target="saveDueDate" class="inline-flex items-center gap-2">
                        @svg('heroicon-o-check', 'w-4 h-4')
                        Speichern
                    </span>
                    <span wire:loading wire:target="saveDueDate" class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Speichern...
                    </span>
                </button>
            </div>
        </x-slot>
    </x-ui-modal>

    {{-- Auto-Unlock beim Verlassen der Seite --}}
    @if($ticket->isLocked() && $ticket->locked_by_user_id === auth()->id())
        @script
        <script>
            const unlockUrl = @js(route('helpdesk.tickets.unlock', $ticket));
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

            const sendUnlock = () => {
                const data = new FormData();
                data.append('_token', csrfToken);
                navigator.sendBeacon(unlockUrl, data);
            };

            window.addEventListener('beforeunload', sendUnlock);
            document.addEventListener('livewire:navigating', sendUnlock);

            $cleanup(() => {
                window.removeEventListener('beforeunload', sendUnlock);
                document.removeEventListener('livewire:navigating', sendUnlock);
            });
        </script>
        @endscript
    @endif

</x-ui-page>
