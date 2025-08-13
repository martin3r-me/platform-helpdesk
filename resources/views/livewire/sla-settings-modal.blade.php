<x-ui-modal size="md" wire:model="modalShow">
    <x-slot name="header">
        SLA-Einstellungen
    </x-slot>

    @if($sla)
    <div class="space-y-4">
        <x-ui-input-text
            name="sla.name"
            label="SLA-Name"
            wire:model.live="sla.name"
            required
            placeholder="z.B. Kritisch, Normal, Niedrig"
        />

        <x-ui-input-textarea
            name="sla.description"
            label="Beschreibung"
            wire:model.live="sla.description"
            placeholder="Beschreibung des SLAs (optional)"
            rows="3"
        />

        <div class="grid grid-cols-2 gap-4">
            <x-ui-input-number
                name="sla.response_time_hours"
                label="Reaktionszeit (Stunden)"
                wire:model.live="sla.response_time_hours"
                placeholder="z.B. 4"
                :nullable="true"
                min="1"
            />
            
            <x-ui-input-number
                name="sla.resolution_time_hours"
                label="Lösungszeit (Stunden)"
                wire:model.live="sla.resolution_time_hours"
                placeholder="z.B. 24"
                :nullable="true"
                min="1"
            />
        </div>

        <x-ui-input-checkbox
            model="sla.is_active"
            checked-label="SLA ist aktiv"
            unchecked-label="SLA ist inaktiv"
            size="md"
            block="true"
        />
        
        <x-ui-confirm-button action="deleteSla" text="SLA löschen" confirmText="Wirklich löschen?" />
    </div>
    @endif
    
    <x-slot name="footer">
        <x-ui-button variant="success" wire:click="save">Speichern</x-ui-button>
    </x-slot>
</x-ui-modal>
