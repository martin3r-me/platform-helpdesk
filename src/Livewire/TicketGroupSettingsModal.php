<?php

namespace Platform\Helpdesk\Livewire;

use Livewire\Component;
use Platform\Core\PlatformCore;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Models\HelpdeskTicketGroup;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On; 

class TicketGroupSettingsModal extends Component
{
    public $modalShow;
    public $ticketGroup;

    public function rules(): array
    {
        return [
            'ticketGroup.name' => 'required|string|max:255',
        ];
    }

    #[On('open-modal-ticket-group-settings')] 
    public function openModalTicketGroupSettings($ticketGroupId)
    {
        $this->ticketGroup = HelpdeskTicketGroup::findOrFail($ticketGroupId);
        $this->modalShow = true;
    }

    public function mount()
    {
        $this->modalShow = false;
    }

    public function save()
    {
        $this->ticketGroup->save();
        $this->reset('ticketGroup');
        $this->dispatch('ticketGroupUpdated');
        $this->closeModal();
    }

    public function deleteTicketGroup(): void
    {
        $this->ticketGroup->delete();
        $this->reset('ticketGroup');
        $this->dispatch('ticketGroupUpdated');
        $this->closeModal();
    }

    public function closeModal()
    {
        $this->modalShow = false;
    }

    public function render()
    {
        return view('helpdesk::livewire.ticket-group-settings-modal')->layout('platform::layouts.app');
    }
}
