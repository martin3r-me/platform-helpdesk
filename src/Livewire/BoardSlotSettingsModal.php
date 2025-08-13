<?php

namespace Platform\Helpdesk\Livewire;

use Livewire\Component;
use Platform\Helpdesk\Models\HelpdeskBoardSlot;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

class BoardSlotSettingsModal extends Component
{
    public $modalShow = false;
    public $boardSlot;

    public function rules(): array
    {
        return [
            'boardSlot.name' => 'required|string|max:255',
        ];
    }

    public function mount()
    {
        $this->modalShow = false;
    }

    #[On('open-modal-board-slot-settings')] 
    public function openModalBoardSlotSettings($boardSlotId)
    {
        $this->boardSlot = HelpdeskBoardSlot::findOrFail($boardSlotId);
        $this->modalShow = true;
    }

    public function closeModal()
    {
        $this->modalShow = false;
        $this->reset(['boardSlot']);
    }

    public function save()
    {
        $this->boardSlot->save();
        $this->reset('boardSlot');
        $this->dispatch('boardSlotUpdated');
        $this->closeModal();
    }

    public function deleteBoardSlot(): void
    {
        $this->boardSlot->delete();
        $this->reset('boardSlot');
        $this->dispatch('boardSlotUpdated');
        $this->closeModal();
    }

    public function render()
    {
        return view('helpdesk::livewire.board-slot-settings-modal');
    }
}
