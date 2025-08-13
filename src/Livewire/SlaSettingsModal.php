<?php

namespace Platform\Helpdesk\Livewire;

use Livewire\Component;
use Platform\Helpdesk\Models\HelpdeskBoardSla;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

class SlaSettingsModal extends Component
{
    public $modalShow = false;
    public $sla;

    public function rules(): array
    {
        return [
            'sla.name' => 'required|string|max:255',
            'sla.description' => 'nullable|string',
            'sla.is_active' => 'boolean',
            'sla.response_time_hours' => 'nullable|integer|min:1',
            'sla.resolution_time_hours' => 'nullable|integer|min:1',
        ];
    }

    public function mount()
    {
        $this->modalShow = false;
    }

    #[On('open-modal-sla-settings')] 
    public function openModalSlaSettings($slaId)
    {
        $this->sla = HelpdeskBoardSla::findOrFail($slaId);
        $this->modalShow = true;
    }

    public function closeModal()
    {
        $this->modalShow = false;
        $this->reset(['sla']);
    }

    public function save()
    {
        $this->sla->save();
        $this->reset('sla');
        $this->dispatch('slaUpdated');
        $this->closeModal();
    }

    public function deleteSla(): void
    {
        $this->sla->delete();
        $this->reset('sla');
        $this->dispatch('slaUpdated');
        $this->closeModal();
    }

    public function render()
    {
        return view('helpdesk::livewire.sla-settings-modal');
    }
}
