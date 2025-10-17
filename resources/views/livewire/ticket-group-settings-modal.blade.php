<x-ui-modal size="md" model="modalShow">
    <x-slot name="header">
        Ticket-Gruppe bearbeiten
    </x-slot>

    @if($ticketGroup)
        <div class="space-y-6">
            {{-- Gruppen-Info --}}
            <div class="p-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                <div class="flex items-center gap-2 mb-2">
                    @svg('heroicon-o-information-circle', 'w-4 h-4 text-[var(--ui-primary)]')
                    <span class="font-medium text-sm text-[var(--ui-secondary)]">Gruppen-Informationen</span>
                </div>
                <div class="text-sm text-[var(--ui-muted)]">
                    Bearbeite den Namen und die Einstellungen dieser Ticket-Gruppe.
                </div>
            </div>

            {{-- Gruppenname --}}
            <div>
                <x-ui-input-text 
                    name="ticketGroup.name"
                    label="Gruppenname"
                    wire:model="ticketGroup.name"
                    placeholder="z.B. Dringend, Wartend, In Bearbeitung"
                    required
                    :errorKey="'ticketGroup.name'"
                />
            </div>

            {{-- Statistiken --}}
            <div class="p-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                <div class="flex items-center gap-2 mb-3">
                    @svg('heroicon-o-chart-bar', 'w-4 h-4 text-[var(--ui-primary)]')
                    <span class="font-medium text-sm text-[var(--ui-secondary)]">Gruppen-Statistiken</span>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-[var(--ui-primary)]">
                            {{ $ticketGroup->tickets()->count() }}
                        </div>
                        <div class="text-xs text-[var(--ui-muted)]">Tickets</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-[var(--ui-success)]">
                            {{ $ticketGroup->tickets()->where('is_done', true)->count() }}
                        </div>
                        <div class="text-xs text-[var(--ui-muted)]">Erledigt</div>
                    </div>
                </div>
            </div>

            {{-- Warnung bei Tickets --}}
            @if($ticketGroup->tickets()->count() > 0)
                <div class="p-4 bg-[var(--ui-warning-5)] border border-[var(--ui-warning)]/60 rounded-lg">
                    <div class="flex items-center gap-2 mb-2">
                        @svg('heroicon-o-exclamation-triangle', 'w-4 h-4 text-[var(--ui-warning)]')
                        <span class="font-medium text-sm text-[var(--ui-warning)]">Achtung</span>
                    </div>
                    <div class="text-sm text-[var(--ui-warning)]">
                        Diese Gruppe enthält noch {{ $ticketGroup->tickets()->count() }} Ticket(s). 
                        Beim Löschen werden alle Tickets in diese Gruppe verschoben.
                    </div>
                </div>
            @endif
        </div>
    @endif

    <x-slot name="footer">
        <div class="flex justify-between items-center gap-4">
            <div class="flex-shrink-0">
                @if($ticketGroup && $ticketGroup->tickets()->count() === 0)
                    <x-ui-confirm-button 
                        action="deleteTicketGroup" 
                        text="Gruppe löschen" 
                        confirmText="Wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden." 
                        variant="danger"
                        :icon="@svg('heroicon-o-trash', 'w-4 h-4')->toHtml()"
                    />
                @endif
            </div>
            <div class="flex gap-2 flex-shrink-0">
                <x-ui-button 
                    type="button" 
                    variant="secondary-outline" 
                    wire:click="closeModal"
                >
                    Abbrechen
                </x-ui-button>
                <x-ui-button 
                    type="button" 
                    variant="primary" 
                    wire:click="save"
                >
                    <div class="flex items-center gap-2">
                        @svg('heroicon-o-check', 'w-4 h-4')
                        Speichern
                    </div>
                </x-ui-button>
            </div>
        </div>
    </x-slot>
</x-ui-modal>
