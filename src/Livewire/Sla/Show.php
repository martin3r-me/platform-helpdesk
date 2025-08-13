<?php

namespace Platform\Helpdesk\Livewire\Sla;

use Livewire\Component;
use Platform\Helpdesk\Models\HelpdeskBoardSla;
use Illuminate\Support\Facades\Auth;

class Show extends Component
{
    public $sla;
    public $isDirty = false;

    public function rules(): array
    {
        return [
            'sla.name' => 'required|string|max:255',
            'sla.description' => 'nullable|string|max:1000',
            'sla.is_active' => 'boolean',
            'sla.response_time_hours' => 'nullable|integer|min:1',
            'sla.resolution_time_hours' => 'nullable|integer|min:1',
        ];
    }

    public function mount(HelpdeskBoardSla $helpdeskBoardSla)
    {
        $this->sla = $helpdeskBoardSla;
    }

    public function updatedSla($property, $value)
    {
        $this->isDirty = true;
    }

    public function save()
    {
        $this->validate();
        $this->sla->save();
        $this->isDirty = false;
        $this->dispatch('slaUpdated');
    }

    public function deleteSla()
    {
        $this->sla->delete();
        return redirect()->route('helpdesk.slas.index');
    }

    public function render()
    {
        // Boards die dieses SLA verwenden
        $boardsUsingThisSla = $this->sla->helpdeskBoards()->with('team')->get();
        
        // Tickets die dieses SLA verwenden (über Boards)
        $ticketsUsingThisSla = \Platform\Helpdesk\Models\HelpdeskTicket::query()
            ->whereHas('helpdeskBoard', function ($query) {
                $query->where('helpdesk_board_sla_id', $this->sla->id);
            })
            ->with(['helpdeskBoard', 'userInCharge'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('helpdesk::livewire.sla.show', [
            'boardsUsingThisSla' => $boardsUsingThisSla,
            'ticketsUsingThisSla' => $ticketsUsingThisSla,
        ])->layout('platform::layouts.app');
    }
}
