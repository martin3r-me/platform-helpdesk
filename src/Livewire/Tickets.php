<?php

namespace Platform\Helpdesk\Livewire;

use Livewire\Component;

class Tickets extends Component
{
    public function render()
    {
        return view('helpdesk::livewire.tickets')->layout('platform::layouts.app');
    }
}