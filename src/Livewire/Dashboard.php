<?php

namespace Platform\Helpdesk\Livewire;

use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        return view('helpdesk::livewire.dashboard')->layout('platform::layouts.app');
    }
}