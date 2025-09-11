<?php

namespace Platform\Helpdesk\Livewire;

use Livewire\Component;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Helpdesk\Models\HelpdeskBoardSlot;
use Platform\Helpdesk\Models\HelpdeskTicketGroup;
use Illuminate\Support\Facades\Auth;

class Board extends Component
{
    public HelpdeskBoard $helpdeskBoard;
    public $groups;

    public function mount(HelpdeskBoard $helpdeskBoard)
    {
        $this->helpdeskBoard = $helpdeskBoard;
        $this->loadGroups();
    }

    public function loadGroups()
    {
        // Lade alle Slots des Boards
        $slots = $this->helpdeskBoard->slots()->orderBy('order')->get();
        
        // Erstelle Gruppen für jedes Slot
        $this->groups = $slots->map(function ($slot) {
            $slot->label = $slot->name;
            $slot->tasks = $slot->tickets()
                ->orderBy('slot_order')
                ->orderBy('order')
                ->get();
            return $slot;
        });

        // Füge eine "Erledigt" Gruppe hinzu
        $doneGroup = new HelpdeskBoardSlot();
        $doneGroup->id = 'done';
        $doneGroup->name = 'ERLEDIGT';
        $doneGroup->label = 'ERLEDIGT';
        $doneGroup->isDoneGroup = true;
        $doneGroup->tasks = $this->helpdeskBoard->tickets()
            ->where('is_done', true)
            ->orderBy('done_at', 'desc')
            ->get();
        
        $this->groups->push($doneGroup);
    }

    public function createTicket($slotId = null)
    {
        $ticket = new HelpdeskTicket();
        $ticket->helpdesk_board_id = $this->helpdeskBoard->id;
        $ticket->helpdesk_board_slot_id = $slotId;
        $ticket->user_id = Auth::id();
        $ticket->team_id = $this->helpdeskBoard->team_id;
        $ticket->title = 'Neues Ticket';
        $ticket->priority = null;
        $ticket->status = null;
        $ticket->story_points = null;
        
        $ticket->is_done = false;
        $ticket->order = 0;
        $ticket->slot_order = 0;
        $ticket->save();

        $this->loadGroups();
        $this->dispatch('ticket-created', ticketId: $ticket->id);
    }

    public function createBoardSlot()
    {
        $slot = new HelpdeskBoardSlot();
        $slot->helpdesk_board_id = $this->helpdeskBoard->id;
        $slot->name = 'Neue Spalte';
        $slot->order = $this->helpdeskBoard->slots()->count();
        $slot->save();

        $this->loadGroups();
        $this->dispatch('slot-created', slotId: $slot->id);
    }

    public function updateTicketOrder($groups)
    {
        foreach ($groups as $group) {
            $slotId = ($group['value'] === 'null' || (int) $group['value'] === 0)
                ? null
                : (int) $group['value'];

            foreach ($group['items'] as $item) {
                $ticket = HelpdeskTicket::find($item['value']);

                if (!$ticket) {
                    continue;
                }

                // Bestimme das neue Slot basierend auf der Gruppe
                $newSlotId = null;
                if ($slotId !== 'done') {
                    $slot = $this->helpdeskBoard->slots()->find($slotId);
                    if ($slot) {
                        $newSlotId = $slot->id;
                    }
                }

                // Update Ticket
                $ticket->helpdesk_board_slot_id = $newSlotId;
                $ticket->slot_order = $item['order'];
                $ticket->order = $item['order'];
                $ticket->is_done = ($slotId === 'done');
                $ticket->done_at = ($slotId === 'done') ? now() : null;
                $ticket->save();
            }
        }

        // Nach Update State refresh
        $this->loadGroups();
    }

    /**
     * Aktualisiert Reihenfolge der Slots nach Drag&Drop.
     */
    public function updateTicketGroupOrder($groups)
    {
        foreach ($groups as $slotGroup) {
            $slotDb = HelpdeskBoardSlot::find($slotGroup['value']);
            if ($slotDb) {
                $slotDb->order = $slotGroup['order'];
                $slotDb->save();
            }
        }

        // Nach Update State refresh
        $this->loadGroups();
    }

    public function render()
    {
        return view('helpdesk::livewire.board')->layout('platform::layouts.app');
    }
}
