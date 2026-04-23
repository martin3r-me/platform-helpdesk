<x-ui-modal size="md" model="modalShow" header="Ticket-Gruppe Settings">

    @if($ticketGroup)
    <div class="p-4">
        <x-ui-form-grid :cols="1" :gap="4">
            <div>
                <label for="ticketGroup.name" class="block text-[11px] font-medium text-gray-500 mb-1">Gruppenname</label>
                <input type="text" id="ticketGroup.name" name="ticketGroup.name"
                    wire:model="ticketGroup.name"
                    placeholder="Name der Gruppe eingeben"
                    class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c] transition-colors"
                >
                @error('ticketGroup.name')
                    <span class="text-xs text-red-600 mt-1">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex justify-end">
                <x-ui-confirm-button action="deleteTicketGroup" text="Gruppe löschen" confirmText="Wirklich löschen?" />
            </div>
        </x-ui-form-grid>
    </div>
    @endif

    <x-slot name="footer">
        <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#049b5c] text-white text-[13px] font-medium hover:bg-[#038a52] transition-colors" wire:click="save">Speichern</button>
    </x-slot>
</x-ui-modal>
