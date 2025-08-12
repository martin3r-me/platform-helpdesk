<?php

namespace Platform\Helpdesk\Livewire;

use Livewire\Component;

class Settings extends Component
{
    public function render()
    {
        return view('helpdesk::livewire.settings')->layout('platform::layouts.app');
    }
}