<?php

namespace Platform\Helpdesk\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Models\HelpdeskBoardSlot;
use Livewire\Attributes\On; 

class Sidebar extends Component
{
    #[On('updateSidebar')] 
    public function updateSidebar()
    {
        
    }

    #[On('create-helpdesk-board')]
    public function createHelpdeskBoard()
    {
        $user = Auth::user();
        $teamId = $user->currentTeam->id;

        // 1. Neues Helpdesk Board anlegen
        $board = new HelpdeskBoard();
        $board->name = 'Neues Helpdesk Board';
        $board->user_id = $user->id;
        $board->team_id = $teamId;
        $board->order = HelpdeskBoard::where('team_id', $teamId)->max('order') + 1;
        $board->save();

        // 2. Standard-Slots anlegen: Offen, In Bearbeitung, Wartend, Gelöst
        $defaultSlots = ['Offen', 'In Bearbeitung', 'Wartend', 'Gelöst'];
        foreach ($defaultSlots as $index => $name) {
            HelpdeskBoardSlot::create([
                'helpdesk_board_id' => $board->id,
                'name' => $name,
                'order' => $index + 1,
            ]);
        }

        return redirect()->route('helpdesk.boards.show', ['helpdeskBoard' => $board->id]);
    }

    public function render()
    {
        // Dynamische Helpdesk Boards holen, z. B. team-basiert
        $helpdeskBoards = HelpdeskBoard::query()
            ->where('team_id', auth()->user()?->currentTeam->id ?? null)
            ->orderBy('name')
            ->get();

        return view('helpdesk::livewire.sidebar', [
            'helpdeskBoards' => $helpdeskBoards,
        ]);
    }
}
