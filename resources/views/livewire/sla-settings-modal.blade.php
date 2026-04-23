<x-ui-modal size="md" model="modalShow">
    <x-slot name="header">
        SLA-Einstellungen
    </x-slot>

    @if($sla)
    <div class="space-y-4">
        <div>
            <label for="sla.name" class="block text-[11px] font-medium text-gray-500 mb-1">SLA-Name</label>
            <input type="text" id="sla.name" name="sla.name"
                wire:model.live="sla.name"
                required
                placeholder="z.B. Kritisch, Normal, Niedrig"
                class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c] transition-colors"
            >
        </div>

        <div>
            <label for="sla.description" class="block text-[11px] font-medium text-gray-500 mb-1">Beschreibung</label>
            <textarea id="sla.description" name="sla.description"
                wire:model.live="sla.description"
                placeholder="Beschreibung des SLAs (optional)"
                rows="3"
                class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c] transition-colors"
            ></textarea>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="sla.response_time_hours" class="block text-[11px] font-medium text-gray-500 mb-1">Reaktionszeit (Stunden)</label>
                <input type="number" id="sla.response_time_hours" name="sla.response_time_hours"
                    wire:model.live="sla.response_time_hours"
                    placeholder="z.B. 4"
                    min="1"
                    class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c] transition-colors"
                >
            </div>

            <div>
                <label for="sla.resolution_time_hours" class="block text-[11px] font-medium text-gray-500 mb-1">Lösungszeit (Stunden)</label>
                <input type="number" id="sla.resolution_time_hours" name="sla.resolution_time_hours"
                    wire:model.live="sla.resolution_time_hours"
                    placeholder="z.B. 24"
                    min="1"
                    class="w-full px-3 py-2 text-[13px] rounded-md border border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#049b5c]/20 focus:border-[#049b5c] transition-colors"
                >
            </div>
        </div>

        <label class="flex items-center gap-3 cursor-pointer p-3 rounded-lg bg-gray-50 border border-gray-200">
            <input type="checkbox"
                   wire:model="sla.is_active"
                   class="w-4 h-4 rounded border-gray-300 text-[#049b5c] focus:ring-[#049b5c] focus:ring-offset-0">
            <span class="text-[13px] text-gray-700">{{ $sla->is_active ? 'SLA ist aktiv' : 'SLA ist inaktiv' }}</span>
        </label>

        <x-ui-confirm-button action="deleteSla" text="SLA löschen" confirmText="Wirklich löschen?" variant="danger" />
    </div>
    @endif

    <x-slot name="footer">
        <div class="flex justify-end gap-2">
            <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-[13px] font-medium hover:bg-gray-50 transition-colors" wire:click="closeModal">Abbrechen</button>
            <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#049b5c] text-white text-[13px] font-medium hover:bg-[#038a52] transition-colors" wire:click="save">Speichern</button>
        </div>
    </x-slot>
</x-ui-modal>
