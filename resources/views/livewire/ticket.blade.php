<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="{{ $ticket->title }}" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true" storeKey="sidebarOpen" side="left">
            <div class="p-6 space-y-6">
                {{-- Navigation Buttons --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Navigation</h3>
                    <div class="space-y-2">
                    @if($ticket->helpdeskBoard)
                            <x-ui-button
                                variant="secondary-outline"
                                size="sm"
                                wire:click="navigateToBoard"
                                class="w-full"
                            >
                                <span class="flex items-center gap-2">
                                    @svg('heroicon-o-rectangle-stack', 'w-4 h-4')
                                    Zum Board
                                </span>
                            </x-ui-button>
                        @endif
                        <x-ui-button
                            variant="secondary-outline"
                            size="sm"
                            wire:click="navigateToMyTickets"
                            class="w-full"
                        >
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-clipboard-document-list', 'w-4 h-4')
                                Zu meinen Tickets
                            </span>
                        </x-ui-button>
                    </div>
                </div>

                {{-- Status Cards --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Status</h3>
                    <div class="space-y-3">
                        @can('update', $ticket)
                            <button type="button" wire:click="toggleDone"
                                class="w-full flex items-center justify-between py-3 px-4 rounded-lg border transition-colors"
                                :class="{
                                    'border-[var(--ui-success)] bg-[var(--ui-success-5)] hover:bg-[var(--ui-success-10)]': {{ $ticket->is_done ? 'true' : 'false' }},
                                    'border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)] hover:bg-[var(--ui-primary-5)]': !({{ $ticket->is_done ? 'true' : 'false' }})
                                }">
                                <span class="text-sm font-medium" :class="{ 'text-[var(--ui-success)]': {{ $ticket->is_done ? 'true' : 'false' }}, 'text-[var(--ui-secondary)]': !({{ $ticket->is_done ? 'true' : 'false' }}) }">Erledigt</span>
                                @if($ticket->is_done)
                                    @svg('heroicon-o-check-circle', 'w-5 h-5 text-[var(--ui-success)]')
                                @else
                                    @svg('heroicon-o-circle-stack', 'w-5 h-5 text-[var(--ui-muted)]')
                                @endif
                            </button>
                        @else
                            <div class="w-full flex items-center justify-between py-3 px-4 rounded-lg border border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)]">
                                <span class="text-sm font-medium text-[var(--ui-secondary)]">Erledigt</span>
                                @if($ticket->is_done)
                                    @svg('heroicon-o-check-circle', 'w-5 h-5 text-[var(--ui-success)]')
                                @else
                                    @svg('heroicon-o-circle-stack', 'w-5 h-5 text-[var(--ui-muted)]')
                                @endif
                            </div>
                        @endcan

                        {{-- Ticket Sperren/Entsperren --}}
                        @can('lock', $ticket)
                            @if($ticket->isLocked())
                                <button type="button" wire:click="unlockTicket"
                                    class="w-full flex items-center justify-between py-3 px-4 rounded-lg border border-[var(--ui-warning)] bg-[var(--ui-warning-5)] hover:bg-[var(--ui-warning-10)] transition-colors">
                                    <span class="text-sm font-medium text-[var(--ui-warning)]">Gesperrt</span>
                                    @svg('heroicon-o-lock-closed', 'w-5 h-5 text-[var(--ui-warning)]')
                                </button>
                            @else
                                <button type="button" wire:click="lockTicket"
                                    class="w-full flex items-center justify-between py-3 px-4 rounded-lg border border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)] hover:bg-[var(--ui-primary-5)] transition-colors">
                                    <span class="text-sm font-medium text-[var(--ui-secondary)]">Sperren</span>
                                    @svg('heroicon-o-lock-open', 'w-5 h-5 text-[var(--ui-muted)]')
                                </button>
                            @endif
                        @else
                            @if($ticket->isLocked())
                                <div class="w-full flex items-center justify-between py-3 px-4 rounded-lg border border-[var(--ui-warning)] bg-[var(--ui-warning-5)]">
                                    <span class="text-sm font-medium text-[var(--ui-warning)]">Gesperrt</span>
                                    @svg('heroicon-o-lock-closed', 'w-5 h-5 text-[var(--ui-warning)]')
                                </div>
                            @endif
                        @endcan
                    </div>
                </div>

                {{-- Quick Actions --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Aktionen</h3>
                    <div class="space-y-2">
                        @if($this->isDirty())
                            <x-ui-button x-transition.opacity variant="primary" size="sm" wire:click="save" class="w-full">
                                <span class="inline-flex items-center gap-2">
                                    @svg('heroicon-o-check','w-4 h-4')
                                    Speichern
                                </span>
                            </x-ui-button>
                    @endif
                        @can('delete', $ticket)
                            <x-ui-confirm-button
                                action="deleteTicket"
                                text="Ticket löschen"
                                confirmText="Wirklich löschen?"
                                variant="danger"
                                size="sm"
                                :icon="@svg('heroicon-o-trash', 'w-4 h-4')->toHtml()"
                                class="w-full"
                            />
                        @endcan
                    </div>
                </div>

                {{-- Ticket Info --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Ticket Info</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-[var(--ui-muted)]">Erstellt:</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $ticket->created_at->format('d.m.Y H:i') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[var(--ui-muted)]">Aktualisiert:</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $ticket->updated_at->format('d.m.Y H:i') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[var(--ui-muted)]">Erstellt von:</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $ticket->user?->name ?? 'Unbekannt' }}</span>
                        </div>
                        @if($ticket->userInCharge)
                            <div class="flex justify-between">
                                <span class="text-[var(--ui-muted)]">Zugewiesen an:</span>
                                <span class="font-medium text-[var(--ui-secondary)]">{{ $ticket->userInCharge->name }}</span>
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
                <div class="text-sm text-[var(--ui-muted)]">Letzte Aktivitäten</div>
                <div class="space-y-3 text-sm">
                    <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                        <div class="font-medium text-[var(--ui-secondary)] truncate">Ticket geladen</div>
                        <div class="text-[var(--ui-muted)]">gerade eben</div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container spacing="space-y-8">
        {{-- Header Block --}}
        <div class="bg-white rounded-lg border border-[var(--ui-border)]/60 p-8 {{ $ticket->isLocked() ? 'border-[var(--ui-warning)] bg-[var(--ui-warning-5)]' : '' }}">
            @if($ticket->isLocked())
                <div class="mb-4 flex items-center gap-2 px-4 py-2 rounded-lg bg-[var(--ui-warning)]/10 border border-[var(--ui-warning)]/30">
                    @svg('heroicon-o-lock-closed', 'w-5 h-5 text-[var(--ui-warning)]')
                    <span class="text-sm font-medium text-[var(--ui-warning)]">
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
            <div class="flex items-start justify-between">
                <div class="flex-1 min-w-0">
                    <h1 class="text-3xl font-bold text-[var(--ui-secondary)] mb-4 tracking-tight">{{ $ticket->title }}</h1>
                    <div class="flex items-center gap-6 text-sm text-[var(--ui-muted)]">
                        @if($ticket->helpdeskBoard)
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-rectangle-stack', 'w-4 h-4')
                                {{ $ticket->helpdeskBoard->name }}
                            </span>
                        @endif
                        @if($ticket->userInCharge)
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-user', 'w-4 h-4')
                                {{ $ticket->userInCharge->name }}
                            </span>
                        @endif
                        @if($ticket->due_date)
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-calendar', 'w-4 h-4')
                                {{ $ticket->due_date->format('d.m.Y H:i') }}
                            </span>
                        @endif
                        @if($ticket->story_points)
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-sparkles', 'w-4 h-4')
                                {{ $ticket->story_points->points() ?? $ticket->story_points }} SP
                            </span>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    @if($ticket->is_done)
                        <x-ui-badge variant="success" size="lg">Erledigt</x-ui-badge>
                    @endif
                </div>
            </div>
        </div>

            {{-- Ticket Details --}}
        <x-ui-panel title="Ticket-Details">
            <h4 class="text-xs font-semibold text-[var(--ui-muted)] uppercase tracking-wider mb-3">Grunddaten</h4>
            <x-ui-form-grid :cols="2" :gap="6">
                        @can('update', $ticket)
                            <x-ui-input-text 
                                name="ticket.title"
                                label="Ticket-Titel"
                                wire:model.live.debounce.500ms="ticket.title"
                                placeholder="Ticket-Titel eingeben..."
                                required
                                :errorKey="'ticket.title'"
                                :disabled="$ticket->isLocked()"
                            />
                        @else
                            <div>
                        <label class="font-semibold text-[var(--ui-secondary)]">Ticket-Titel:</label>
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">{{ $ticket->title }}</div>
                            </div>
                        @endcan

                @can('update', $ticket)
                    <x-ui-input-select
                        name="ticket.user_in_charge_id"
                        label="Zugewiesen an"
                        :options="$teamUsers"
                        optionValue="id"
                        optionLabel="name"
                        :nullable="true"
                        nullLabel="– Niemand zugewiesen –"
                        wire:model.live="ticket.user_in_charge_id"
                        :disabled="$ticket->isLocked()"
                    />
                @else
                    <div>
                        <label class="font-semibold text-[var(--ui-secondary)]">Zugewiesen an:</label>
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            {{ $ticket->userInCharge?->name ?? 'Niemand zugewiesen' }}
                        </div>
                    </div>
                @endcan
            </x-ui-form-grid>
            
            <div class="mt-6">
                <h4 class="text-xs font-semibold text-[var(--ui-muted)] uppercase tracking-wider mb-3">Anmerkungen</h4>
                        @can('update', $ticket)
                            <x-ui-input-textarea
                                name="ticket.notes"
                                :disabled="$ticket->isLocked()"
                                label="Anmerkung"
                                wire:model.live.debounce.500ms="ticket.notes"
                                placeholder="Anmerkung eingeben..."
                                rows="4"
                                :errorKey="'ticket.notes'"
                            />
                        @else
                            <div>
                        <label class="font-semibold text-[var(--ui-secondary)]">Anmerkung:</label>
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 whitespace-pre-wrap">{{ $ticket->notes ?: 'Keine Anmerkung vorhanden' }}</div>
                            </div>
                        @endcan
                    </div>
        </x-ui-panel>

        {{-- Definition of Done (DoD) --}}
        <x-ui-panel title="Definition of Done (DoD)">
            @php
                $dodProgress = $ticket->dod_progress;
                $dod = $ticket->dod ?? [];
            @endphp

            {{-- Fortschrittsanzeige --}}
            @if(count($dod) > 0)
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-[var(--ui-secondary)]">Fortschritt</span>
                        <span class="text-sm font-semibold text-[var(--ui-primary)]">
                            {{ $dodProgress['completed'] }} / {{ $dodProgress['total'] }} ({{ $dodProgress['percentage'] }}%)
                        </span>
                    </div>
                    <div class="w-full bg-[var(--ui-muted-10)] rounded-full h-2.5 overflow-hidden">
                        <div
                            class="h-2.5 rounded-full transition-all duration-300 {{ $dodProgress['percentage'] === 100 ? 'bg-[var(--ui-success)]' : 'bg-[var(--ui-primary)]' }}"
                            style="width: {{ $dodProgress['percentage'] }}%"
                        ></div>
                    </div>
                </div>
            @endif

            {{-- DoD-Liste --}}
            <h4 class="text-xs font-semibold text-[var(--ui-muted)] uppercase tracking-wider mb-3">Checkliste</h4>
            <div class="space-y-2">
                @forelse($dod as $index => $item)
                    <div class="flex items-start gap-3 p-3 rounded-lg border border-[var(--ui-border)]/40 bg-white hover:border-[var(--ui-primary)]/30 transition-colors group {{ ($item['checked'] ?? false) ? 'bg-[var(--ui-success-5)] border-[var(--ui-success)]/30' : '' }}">
                        @can('update', $ticket)
                            <button
                                type="button"
                                wire:click="toggleDodItem({{ $index }})"
                                class="flex-shrink-0 mt-0.5 w-5 h-5 rounded border-2 flex items-center justify-center transition-colors {{ ($item['checked'] ?? false) ? 'bg-[var(--ui-success)] border-[var(--ui-success)] text-white' : 'border-[var(--ui-border)] hover:border-[var(--ui-primary)]' }}"
                                :disabled="$ticket->isLocked()"
                            >
                                @if($item['checked'] ?? false)
                                    @svg('heroicon-o-check', 'w-3.5 h-3.5')
                                @endif
                            </button>
                        @else
                            <div class="flex-shrink-0 mt-0.5 w-5 h-5 rounded border-2 flex items-center justify-center {{ ($item['checked'] ?? false) ? 'bg-[var(--ui-success)] border-[var(--ui-success)] text-white' : 'border-[var(--ui-border)]' }}">
                                @if($item['checked'] ?? false)
                                    @svg('heroicon-o-check', 'w-3.5 h-3.5')
                                @endif
                            </div>
                        @endcan

                        <span class="flex-1 text-sm {{ ($item['checked'] ?? false) ? 'text-[var(--ui-muted)] line-through' : 'text-[var(--ui-secondary)]' }}">
                            {{ $item['text'] ?? '' }}
                        </span>

                        @can('update', $ticket)
                            @if(!$ticket->isLocked())
                                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button
                                        type="button"
                                        wire:click="moveDodItem({{ $index }}, 'up')"
                                        class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] rounded hover:bg-[var(--ui-muted-5)]"
                                        title="Nach oben"
                                        @if($index === 0) disabled @endif
                                    >
                                        @svg('heroicon-o-chevron-up', 'w-4 h-4')
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="moveDodItem({{ $index }}, 'down')"
                                        class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] rounded hover:bg-[var(--ui-muted-5)]"
                                        title="Nach unten"
                                        @if($index === count($dod) - 1) disabled @endif
                                    >
                                        @svg('heroicon-o-chevron-down', 'w-4 h-4')
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="removeDodItem({{ $index }})"
                                        wire:confirm="DoD-Eintrag wirklich löschen?"
                                        class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-danger)] rounded hover:bg-[var(--ui-danger-5)]"
                                        title="Löschen"
                                    >
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </button>
                                </div>
                            @endif
                        @endcan
                    </div>
                @empty
                    <div class="text-center py-6 text-sm text-[var(--ui-muted)]">
                        Noch keine Definition of Done vorhanden.
                    </div>
                @endforelse
            </div>

            {{-- Neuen Eintrag hinzufügen --}}
            @can('update', $ticket)
                @if(!$ticket->isLocked())
                    <div class="mt-4 pt-4 border-t border-[var(--ui-border)]/40">
                        <div class="flex gap-2">
                            <input
                                type="text"
                                wire:model="newDodItem"
                                wire:keydown.enter="addDodItem"
                                placeholder="Neuen DoD-Punkt hinzufügen..."
                                class="flex-1 px-3 py-2 text-sm rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20 focus:border-[var(--ui-primary)]"
                            >
                            <x-ui-button
                                variant="primary"
                                size="sm"
                                wire:click="addDodItem"
                            >
                                <span class="inline-flex items-center gap-1">
                                    @svg('heroicon-o-plus', 'w-4 h-4')
                                    Hinzufügen
                                </span>
                            </x-ui-button>
                        </div>
                    </div>
                @endif
            @endcan
        </x-ui-panel>

        {{-- Metadaten --}}
        <x-ui-panel title="Metadaten">
            <h4 class="text-xs font-semibold text-[var(--ui-muted)] uppercase tracking-wider mb-3">Status & Priorität</h4>
            <x-ui-form-grid :cols="2" :gap="6">
                        {{-- Status & Priorität --}}
                            @can('update', $ticket)
                                <x-ui-input-select
                                    name="ticket.status"
                                    label="Status"
                                    :options="\Platform\Helpdesk\Enums\TicketStatus::cases()"
                                    optionValue="value"
                                    optionLabel="label"
                                    :nullable="true"
                                    nullLabel="– Kein Status –"
                                    wire:model.live="ticket.status"
                                    :disabled="$ticket->isLocked()"
                                />
                            @else
                                <div>
                        <label class="font-semibold text-[var(--ui-secondary)]">Status:</label>
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">{{ $ticket->status?->label() ?? '–' }}</div>
                                </div>
                            @endcan

                            @can('update', $ticket)
                                <x-ui-input-select
                                    name="ticket.priority"
                                    label="Priorität"
                                    :options="\Platform\Helpdesk\Enums\TicketPriority::cases()"
                                    optionValue="value"
                                    optionLabel="label"
                                    :nullable="true"
                                    nullLabel="– Keine Priorität –"
                                    wire:model.live="ticket.priority"
                                    :disabled="$ticket->isLocked()"
                                />
                            @else
                                <div>
                        <label class="font-semibold text-[var(--ui-secondary)]">Priorität:</label>
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">{{ $ticket->priority?->label() ?? '–' }}</div>
                                </div>
                            @endcan

            </x-ui-form-grid>

            {{-- Story Points & Fälligkeitsdatum --}}
            <h4 class="text-xs font-semibold text-[var(--ui-muted)] uppercase tracking-wider mb-3 mt-6">Planung</h4>
            <x-ui-form-grid :cols="2" :gap="6">
                            @can('update', $ticket)
                                <x-ui-input-select
                                    name="ticket.story_points"
                                    label="Story Points"
                                    :options="\Platform\Helpdesk\Enums\TicketStoryPoints::cases()"
                                    optionValue="value"
                                    optionLabel="label"
                                    :nullable="true"
                                    nullLabel="– Kein Wert –"
                                    wire:model.live="ticket.story_points"
                                    :disabled="$ticket->isLocked()"
                                />
                            @else
                                <div>
                        <label class="font-semibold text-[var(--ui-secondary)]">Story Points:</label>
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">{{ $ticket->story_points?->label() ?? '–' }}</div>
                                </div>
                            @endcan

                            @can('update', $ticket)
                                <div>
                                    <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">
                                        Fälligkeitsdatum
                                    </label>
                                    <button
                                        type="button"
                                        wire:click="openDueDateModal"
                                        class="w-full px-4 py-2.5 text-left bg-[var(--ui-surface)] border border-[var(--ui-border)]/60 rounded-lg hover:border-[var(--ui-primary)]/60 hover:bg-[var(--ui-primary-5)] transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20 focus:border-[var(--ui-primary)] flex items-center justify-between group"
                                    >
                                        <span class="flex items-center gap-2 text-sm text-[var(--ui-secondary)]">
                                            @svg('heroicon-o-calendar', 'w-4 h-4 text-[var(--ui-muted)] group-hover:text-[var(--ui-primary)] transition-colors')
                                            @if($ticket->due_date)
                                                <span class="font-medium">{{ $ticket->due_date->format('d.m.Y H:i') }}</span>
                                            @else
                                                <span class="text-[var(--ui-muted)]">Kein Datum gesetzt</span>
                                            @endif
                                        </span>
                                        @svg('heroicon-o-chevron-right', 'w-4 h-4 text-[var(--ui-muted)] group-hover:text-[var(--ui-primary)] transition-colors')
                                    </button>
                                </div>
                            @else
                                <div>
                                    <label class="font-semibold text-[var(--ui-secondary)]">Fälligkeitsdatum:</label>
                                    <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                        {{ $ticket->due_date ? $ticket->due_date->format('d.m.Y H:i') : '–' }}
                                    </div>
                                </div>
                            @endcan
            </x-ui-form-grid>
        </x-ui-panel>

        {{-- GitHub Repositories --}}
        @if($linkedGithubRepositories->count() > 0 || $availableGithubRepositories->count() > 0 || !empty($githubRepositorySearch))
            <x-ui-panel title="GitHub Repositories">
                {{-- Verknüpfte Repositories --}}
                @if($linkedGithubRepositories->count() > 0)
                    <div class="mb-6">
                        <h4 class="text-xs font-semibold text-[var(--ui-muted)] uppercase tracking-wider mb-3">Verknüpfte Repositories</h4>
                        <div class="space-y-2">
                            @foreach($linkedGithubRepositories as $repo)
                                <div class="flex items-center justify-between p-3 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 rounded-lg">
                                    <div class="flex items-center gap-3 flex-1 min-w-0">
                                        <div class="flex-shrink-0 flex items-center justify-center w-10 h-10 rounded-lg bg-gray-100">
                                            @svg('heroicon-o-code-bracket', 'w-5 h-5 text-gray-700')
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <a href="{{ $repo->url }}" target="_blank" class="block hover:text-[var(--ui-primary)] transition-colors">
                                                <h4 class="text-sm font-semibold text-[var(--ui-secondary)] truncate">{{ $repo->full_name }}</h4>
                                            </a>
                                            @if($repo->description)
                                                <p class="text-xs text-[var(--ui-muted)] mt-0.5 line-clamp-1">{{ $repo->description }}</p>
                                            @endif
                                            <div class="flex items-center gap-3 mt-1 text-xs text-[var(--ui-muted)]">
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
                                        <x-ui-button 
                                            variant="danger-outline" 
                                            size="xs"
                                            wire:click="detachGithubRepository({{ $repo->id }})"
                                            wire:confirm="Repository wirklich vom Ticket trennen?"
                                        >
                                            @svg('heroicon-o-x-mark', 'w-3.5 h-3.5')
                                        </x-ui-button>
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
                            <h4 class="text-xs font-semibold text-[var(--ui-muted)] uppercase tracking-wider">Repository verknüpfen</h4>
                        </div>
                        
                        {{-- Suchfeld --}}
                        <div class="mb-3">
                            <x-ui-input-text
                                name="githubRepositorySearch"
                                label="Repository suchen"
                                wire:model.live.debounce.300ms="githubRepositorySearch"
                                placeholder="Nach Name, Beschreibung oder Owner suchen..."
                                :errorKey="'githubRepositorySearch'"
                            />
                        </div>

                        @if($availableGithubRepositories->count() > 0)
                            <div class="space-y-2">
                            @foreach($availableGithubRepositories as $repo)
                                <div class="flex items-center justify-between p-3 bg-white border border-[var(--ui-border)]/40 rounded-lg hover:border-[var(--ui-primary)]/60 transition-colors">
                                    <div class="flex items-center gap-3 flex-1 min-w-0">
                                        <div class="flex-shrink-0 flex items-center justify-center w-10 h-10 rounded-lg bg-gray-100">
                                            @svg('heroicon-o-code-bracket', 'w-5 h-5 text-gray-700')
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <a href="{{ $repo->url }}" target="_blank" class="block hover:text-[var(--ui-primary)] transition-colors">
                                                <h4 class="text-sm font-semibold text-[var(--ui-secondary)] truncate">{{ $repo->full_name }}</h4>
                                            </a>
                                            @if($repo->description)
                                                <p class="text-xs text-[var(--ui-muted)] mt-0.5 line-clamp-1">{{ $repo->description }}</p>
                                            @endif
                                            <div class="flex items-center gap-3 mt-1 text-xs text-[var(--ui-muted)]">
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
                                        <x-ui-button 
                                            variant="primary-outline" 
                                            size="xs"
                                            wire:click="attachGithubRepository({{ $repo->id }})"
                                        >
                                            <span class="inline-flex items-center gap-1">
                                                @svg('heroicon-o-plus', 'w-3.5 h-3.5')
                                                Verknüpfen
                                            </span>
                                        </x-ui-button>
                                    @endcan
                                </div>
                            @endforeach
                            </div>
                        @elseif(!empty($githubRepositorySearch))
                            <div class="p-4 text-center text-sm text-[var(--ui-muted)] bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 rounded-lg">
                                Keine Repositories gefunden für "{{ $githubRepositorySearch }}"
                            </div>
                        @endif
                    </div>
                @endif
            </x-ui-panel>
        @endif

    </x-ui-page-container>

    <!-- Due Date Modal -->
    <x-ui-modal size="md" wire:model="dueDateModalShow" :backdropClosable="true" :escClosable="true">
        <x-slot name="header">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 bg-[var(--ui-primary-10)] rounded-lg flex items-center justify-center">
                        @svg('heroicon-o-calendar', 'w-5 h-5 text-[var(--ui-primary)]')
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)]">Fälligkeitsdatum</h3>
                    <p class="text-sm text-[var(--ui-muted)]">Datum und Uhrzeit festlegen</p>
                </div>
            </div>
        </x-slot>

        <div class="space-y-6">
            <!-- Kalender Navigation -->
            <div class="flex items-center justify-between">
                <h2 class="flex-auto text-sm font-semibold text-[var(--ui-secondary)]">
                    {{ $this->calendarMonthName }}
                </h2>
                <div class="flex items-center gap-2">
                    <button 
                        type="button" 
                        wire:click="previousMonth"
                        class="flex flex-none items-center justify-center p-1.5 text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors rounded-lg hover:bg-[var(--ui-muted-5)]"
                    >
                        <span class="sr-only">Vorheriger Monat</span>
                        @svg('heroicon-o-chevron-left', 'w-5 h-5')
                    </button>
                    <button 
                        type="button" 
                        wire:click="nextMonth"
                        class="flex flex-none items-center justify-center p-1.5 text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors rounded-lg hover:bg-[var(--ui-muted-5)]"
                    >
                        <span class="sr-only">Nächster Monat</span>
                        @svg('heroicon-o-chevron-right', 'w-5 h-5')
                    </button>
                </div>
            </div>

            <!-- Wochentage Header -->
            <div class="grid grid-cols-7 text-center text-xs font-medium text-[var(--ui-muted)]">
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
                    <div class="py-2 {{ !$loop->first ? 'border-t border-[var(--ui-border)]/40' : '' }}">
                        <button
                            type="button"
                            wire:click="selectDate('{{ $day['date'] }}')"
                            class="mx-auto flex w-8 h-8 items-center justify-center rounded-full transition-all duration-200
                                {{ !$day['isCurrentMonth'] ? 'text-[var(--ui-muted)]/50' : '' }}
                                {{ $day['isCurrentMonth'] && !$day['isToday'] && !$day['isSelected'] ? 'text-[var(--ui-secondary)] hover:bg-[var(--ui-primary-5)] hover:text-[var(--ui-primary)]' : '' }}
                                {{ $day['isToday'] && !$day['isSelected'] ? 'font-semibold text-[var(--ui-primary)]' : '' }}
                                {{ $day['isSelected'] && !$day['isToday'] ? 'font-semibold text-white bg-[var(--ui-secondary)]' : '' }}
                                {{ $day['isSelected'] && $day['isToday'] ? 'font-semibold text-white bg-[var(--ui-primary)]' : '' }}
                            "
                        >
                            <time datetime="{{ $day['date'] }}">{{ $day['day'] }}</time>
                        </button>
                    </div>
                @endforeach
            </div>

            <!-- Zeitauswahl -->
            <div class="pt-4 border-t border-[var(--ui-border)]/60">
                <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-3">
                    Uhrzeit
                </label>

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div>
                            <label class="block text-xs font-medium text-[var(--ui-muted)] mb-1">Stunde</label>
                            <select
                                wire:model.live="selectedHour"
                                class="w-28 px-3 py-2 text-sm rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20 focus:border-[var(--ui-primary)]"
                            >
                                @for($h = 0; $h < 24; $h++)
                                    <option value="{{ $h }}">{{ str_pad($h, 2, '0', STR_PAD_LEFT) }}</option>
                                @endfor
                            </select>
                        </div>

                        <div class="text-2xl font-bold text-[var(--ui-muted)]">:</div>

                        <div>
                            <label class="block text-xs font-medium text-[var(--ui-muted)] mb-1">Minute</label>
                            <select
                                wire:model.live="selectedMinute"
                                class="w-28 px-3 py-2 text-sm rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20 focus:border-[var(--ui-primary)]"
                            >
                                @foreach([0, 15, 30, 45] as $minute)
                                    <option value="{{ $minute }}">{{ str_pad($minute, 2, '0', STR_PAD_LEFT) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="sm:text-right">
                        <span class="inline-flex items-center gap-2 px-3 py-2 text-sm font-semibold text-[var(--ui-primary)] bg-[var(--ui-primary-10)] rounded-lg border border-[var(--ui-primary)]/20">
                            @svg('heroicon-o-clock', 'w-4 h-4 text-[var(--ui-primary)]')
                            {{ sprintf('%02d:%02d', $selectedHour, $selectedMinute) }} Uhr
                        </span>
                    </div>
                </div>
            </div>

            <!-- Aktuelles Datum Anzeige -->
            @if($selectedDate)
                <div class="pt-4 border-t border-[var(--ui-border)]/60">
                    <div class="flex items-center gap-2 text-sm text-[var(--ui-muted)]">
                        @svg('heroicon-o-calendar-days', 'w-4 h-4')
                        <span>
                            Ausgewählt: 
                            <span class="font-medium text-[var(--ui-secondary)]">
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
                <div class="pt-4 border-t border-[var(--ui-border)]/60">
                    <x-ui-button 
                        variant="danger-outline" 
                        size="sm" 
                        wire:click="clearDueDate"
                        class="w-full"
                    >
                        <span class="inline-flex items-center gap-2">
                            @svg('heroicon-o-trash', 'w-4 h-4')
                            Datum entfernen
                        </span>
                    </x-ui-button>
                </div>
            @endif
        </div>
        
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-ui-button variant="secondary-outline" size="sm" wire:click="closeDueDateModal">
                    Abbrechen
                </x-ui-button>
                <button 
                    type="button"
                    wire:click="saveDueDate"
                    wire:loading.attr="disabled"
                    wire:target="saveDueDate"
                    wire:disabled="!selectedDate"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-[var(--ui-primary)] text-white hover:bg-[var(--ui-primary-80)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
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

</x-ui-page>
