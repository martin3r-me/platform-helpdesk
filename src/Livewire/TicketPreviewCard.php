<?php

namespace Platform\Helpdesk\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Helpdesk\Models\HelpdeskTicket;

class TicketPreviewCard extends Component
{
    public HelpdeskTicket $ticket;

    public function render()
    {   
        return view('helpdesk::livewire.ticket-preview-card');
    }
}
