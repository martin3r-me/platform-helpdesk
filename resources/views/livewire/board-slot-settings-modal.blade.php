<x-ui-modal size="md" model="modalShow">
    <x-slot name="header">
        Spalten-Einstellungen
    </x-slot>

    @if($boardSlot)
        <div class="space-y-6">
            <div>
                <label for="boardSlot.name" class="block text-[11px] font-medium text-gray-500 mb-1">Spaltenname</label>
                <input type="text" id="boardSlot.name" name="boardSlot.name"
                    wire:model="boardSlot.name"
                    placeholder="z. B. Offen, In Bearbeitung"
                    class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c] transition-colors"
                >
                @error('boardSlot.name')
                    <span class="text-xs text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>

            <div class="pt-4 border-t border-gray-200">
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
            <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-[13px] font-medium hover:bg-gray-50 transition-colors" wire:click="closeModal">Abbrechen</button>
            <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#049b5c] text-white text-[13px] font-medium hover:bg-[#038a52] transition-colors" wire:click="save">Speichern</button>
        </div>
    </x-slot>
</x-ui-modal>
