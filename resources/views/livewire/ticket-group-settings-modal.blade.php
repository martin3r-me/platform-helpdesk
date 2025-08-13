<x-ui-modal size="md" wire:model="modalShow">
    <x-slot name="header">
        <div class="d-flex items-center gap-2">
            <x-heroicon-o-rectangle-stack class="w-5 h-5"/>
            Ticket-Gruppe bearbeiten
        </div>
    </x-slot>

    @if($ticketGroup)
        <div class="space-y-6">
            {{-- Gruppen-Info --}}
            <div class="p-4 bg-muted-5 rounded-lg">
                <div class="d-flex items-center gap-2 mb-2">
                    <x-heroicon-o-information-circle class="w-4 h-4 text-primary"/>
                    <span class="font-medium text-sm">Gruppen-Informationen</span>
                </div>
                <div class="text-sm text-gray-600">
                    Bearbeite den Namen und die Einstellungen dieser Ticket-Gruppe.
                </div>
            </div>

            {{-- Gruppenname --}}
            <div>
                <x-ui-input-text 
                    name="ticketGroup.name"
                    label="Gruppenname"
                    wire:model.live.debounce.500ms="ticketGroup.name"
                    placeholder="z.B. Dringend, Wartend, In Bearbeitung"
                    required
                    :errorKey="'ticketGroup.name'"
                />
            </div>

            {{-- Statistiken --}}
            <div class="p-4 bg-muted-5 rounded-lg">
                <div class="d-flex items-center gap-2 mb-3">
                    <x-heroicon-o-chart-bar class="w-4 h-4 text-primary"/>
                    <span class="font-medium text-sm">Gruppen-Statistiken</span>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-primary">
                            {{ $ticketGroup->tickets()->count() }}
                        </div>
                        <div class="text-xs text-gray-500">Tickets</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-success">
                            {{ $ticketGroup->tickets()->where('is_done', true)->count() }}
                        </div>
                        <div class="text-xs text-gray-500">Erledigt</div>
                    </div>
                </div>
            </div>

            {{-- Warnung bei Tickets --}}
            @if($ticketGroup->tickets()->count() > 0)
                <div class="p-4 bg-warning-10 border border-warning rounded-lg">
                    <div class="d-flex items-center gap-2 mb-2">
                        <x-heroicon-o-exclamation-triangle class="w-4 h-4 text-warning"/>
                        <span class="font-medium text-sm text-warning">Achtung</span>
                    </div>
                    <div class="text-sm text-warning">
                        Diese Gruppe enthält noch {{ $ticketGroup->tickets()->count() }} Ticket(s). 
                        Beim Löschen werden alle Tickets in diese Gruppe verschoben.
                    </div>
                </div>
            @endif
        </div>
    @endif

    <x-slot name="footer">
        <div class="d-flex justify-between items-center gap-4">
            <div class="flex-shrink-0">
                @if($ticketGroup && $ticketGroup->tickets()->count() === 0)
                    <x-ui-confirm-button 
                        action="deleteTicketGroup" 
                        text="Gruppe löschen" 
                        confirmText="Wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden." 
                        variant="danger-outline"
                        :icon="@svg('heroicon-o-trash', 'w-4 h-4')->toHtml()"
                    />
                @endif
            </div>
            <div class="d-flex gap-2 flex-shrink-0">
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
                    <div class="d-flex items-center gap-2">
                        @svg('heroicon-o-check', 'w-4 h-4')
                        Speichern
                    </div>
                </x-ui-button>
            </div>
        </div>
    </x-slot>
</x-ui-modal>
