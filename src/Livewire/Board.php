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
    public bool $showDone = false;

    public function mount(HelpdeskBoard $helpdeskBoard)
    {
        $this->helpdeskBoard = $helpdeskBoard;
        $this->loadGroups();
    }

    public function rendered()
    {
        // Organization-Kontext setzen - beides erlauben: Zeiten + Entity-Verknüpfung (analog zu Project)
        $this->dispatch('organization', [
            'context_type' => get_class($this->helpdeskBoard),
            'context_id' => $this->helpdeskBoard->id,
            'allow_time_entry' => true,
            'allow_entities' => true,
            'allow_dimensions' => true,
            // Verfügbare Relations für Children-Cascade (z.B. Tickets mit/ohne Slots)
            'include_children_relations' => ['tickets', 'slots.tickets'],
        ]);
    }

    public function loadGroups()
    {
        $this->groups = collect();

        // Backlog/Inbox (Tickets ohne Slot, nicht erledigt)
        $backlog = new HelpdeskBoardSlot();
        $backlog->id = 'backlog';
        $backlog->name = 'BACKLOG';
        $backlog->label = 'BACKLOG / Inbox';
        $backlog->isBacklog = true;
        $backlog->tasks = $this->helpdeskBoard->tickets()
            ->whereNull('helpdesk_board_slot_id')
            ->where('is_done', false)
            ->orderBy('slot_order')
            ->orderBy('order')
            ->get();
        $this->groups->push($backlog);

        // Lade alle Slots des Boards
        $slots = $this->helpdeskBoard->slots()->orderBy('order')->get();
        
        // Erstelle Gruppen für jedes Slot
        $slots->each(function ($slot) {
            $slot->label = $slot->name;
            $slot->isBacklog = false;
            $slot->tasks = $slot->tickets()
                ->where('is_done', false)
                ->orderBy('slot_order')
                ->orderBy('order')
                ->get();
            $this->groups->push($slot);
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

                // Update Ticket - nur Position ändern, NICHT den Erledigt-Status
                // is_done und done_at werden bewusst nicht geändert, da Sortierung
                // nur slot und order beeinflussen soll (siehe Ticket #119)
                $ticket->helpdesk_board_slot_id = $newSlotId;
                $ticket->slot_order = $item['order'];
                $ticket->order = $item['order'];
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

    public function deleteTicket($ticketId)
    {
        $ticket = HelpdeskTicket::findOrFail($ticketId);
        $this->authorize('delete', $ticket);
        $ticket->delete();
        $this->loadGroups();
    }

    public function toggleShowDone()
    {
        $this->showDone = !$this->showDone;
    }

    public function render()
    {
        return view('helpdesk::livewire.board')->layout('platform::layouts.app');
    }
}
