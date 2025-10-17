<x-ui-modal size="md" model="modalShow">
    <x-slot name="header">
        Spalten-Einstellungen
    </x-slot>

    @if($boardSlot)
        <div class="space-y-6">
            <x-ui-input-text
                name="boardSlot.name"
                label="Spaltenname"
                wire:model="boardSlot.name"
                placeholder="z. B. Offen, In Bearbeitung"
                errorKey="boardSlot.name"
            />
            
            <div class="pt-4 border-t border-[var(--ui-border)]/60">
                <x-ui-confirm-button 
                    action="deleteBoardSlot" 
                    text="Spalte löschen" 
                    confirmText="Wirklich löschen?" 
                    variant="danger"
                />
            </div>
        </div>
    @endif
    
    <x-slot name="footer">
        <div class="flex justify-end gap-2">
            <x-ui-button variant="secondary-outline" wire:click="closeModal">Abbrechen</x-ui-button>
            <x-ui-button variant="primary" wire:click="save">Speichern</x-ui-button>
        </div>
    </x-slot>
</x-ui-modal>
