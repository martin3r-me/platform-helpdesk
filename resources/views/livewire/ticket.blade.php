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
                                :href="route('helpdesk.boards.show', $ticket->helpdeskBoard)"
                                wire:navigate
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
                            :href="route('helpdesk.my-tickets')"
                            wire:navigate
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
        <div class="bg-white rounded-lg border border-[var(--ui-border)]/60 p-8">
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
            <x-ui-form-grid :cols="2" :gap="6">
                        @can('update', $ticket)
                            <x-ui-input-text 
                                name="ticket.title"
                                label="Ticket-Titel"
                                wire:model.live.debounce.500ms="ticket.title"
                                placeholder="Ticket-Titel eingeben..."
                                required
                                :errorKey="'ticket.title'"
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
                        @can('update', $ticket)
                            <x-ui-input-textarea 
                                name="ticket.description"
                                label="Ticket Beschreibung"
                                wire:model.live.debounce.500ms="ticket.description"
                                placeholder="Ticket Beschreibung eingeben..."
                                rows="6"
                                :errorKey="'ticket.description'"
                            />
                        @else
                            <div>
                        <label class="font-semibold text-[var(--ui-secondary)]">Beschreibung:</label>
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 whitespace-pre-wrap">{{ $ticket->description ?: 'Keine Beschreibung vorhanden' }}</div>
                            </div>
                        @endcan
                    </div>
        </x-ui-panel>

        {{-- Metadaten --}}
        <x-ui-panel title="Metadaten">
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
                                />
                            @else
                                <div>
                        <label class="font-semibold text-[var(--ui-secondary)]">Priorität:</label>
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">{{ $ticket->priority?->label() ?? '–' }}</div>
                                </div>
                            @endcan

                        {{-- Story Points & Fälligkeitsdatum --}}
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
                                />
                            @else
                                <div>
                        <label class="font-semibold text-[var(--ui-secondary)]">Story Points:</label>
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">{{ $ticket->story_points?->label() ?? '–' }}</div>
                                </div>
                            @endcan

                            @can('update', $ticket)
                                <x-ui-input-date
                                    name="ticket.due_date"
                                    label="Fälligkeitsdatum"
                                    wire:model.live="ticket.due_date"
                                    :nullable="true"
                                    :errorKey="'ticket.due_date'"
                                />
                            @else
                                <div>
                        <label class="font-semibold text-[var(--ui-secondary)]">Fälligkeitsdatum:</label>
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                        {{ $ticket->due_date ? $ticket->due_date->format('d.m.Y') : '–' }}
                        </div>
                    </div>
                @endcan
            </x-ui-form-grid>
        </x-ui-panel>

    </x-ui-page-container>

</x-ui-page>
