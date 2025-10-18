<x-ui-modal size="md" model="modalShow" header="Ticket-Gruppe Settings">

    @if($ticketGroup)
    <div class="p-4">
        <x-ui-form-grid :cols="1" :gap="4">
            <x-ui-input-text
                name="ticketGroup.name"
                label="Gruppenname"
                wire:model="ticketGroup.name"
                placeholder="Name der Gruppe eingeben"
                errorKey="ticketGroup.name"
            />
            
            <div class="d-flex justify-end">
                <x-ui-confirm-button action="deleteTicketGroup" text="Gruppe löschen" confirmText="Wirklich löschen?" />
            </div>
        </x-ui-form-grid>
    </div>
    @endif
    
    <x-slot name="footer">
        <x-ui-button variant="success" wire:click="save">Speichern</x-ui-button>
    </x-slot>
</x-ui-modal>
