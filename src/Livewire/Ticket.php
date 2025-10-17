<?php

namespace Platform\Helpdesk\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Helpdesk\Models\HelpdeskTicket;

class Ticket extends Component
{
    public $ticket;

    protected $rules = [
        'ticket.title' => 'required|string|max:255',
        'ticket.description' => 'nullable|string',

        'ticket.is_done' => 'boolean',
        'ticket.due_date' => 'nullable|date',
        'ticket.user_in_charge_id' => 'nullable|integer',
        'ticket.priority' => 'nullable|in:low,normal,high',
        'ticket.status' => 'nullable|in:open,in_progress,waiting,resolved,closed',
        'ticket.story_points' => 'nullable|in:xs,s,m,l,xl,xxl',
        'ticket.helpdesk_board_id' => 'nullable|integer',
    ];

    public function mount(HelpdeskTicket $helpdeskTicket)
    {
        $this->authorize('view', $helpdeskTicket);
        $this->ticket = $helpdeskTicket;
    }

    public function rendered()
    {
        $this->dispatch('comms', [
            'model' => get_class($this->ticket),                                // z. B. 'Platform\Helpdesk\Models\HelpdeskTicket'
            'modelId' => $this->ticket->id,
            'subject' => $this->ticket->title,
            'description' => $this->ticket->description ?? '',
            'url' => route('helpdesk.tickets.show', $this->ticket),            // absolute URL zum Ticket
            'source' => 'helpdesk.ticket.view',                                // eindeutiger Quell-Identifier (frei wählbar)
            'recipients' => [$this->ticket->user_in_charge_id],                // falls vorhanden, sonst leer
            'meta' => [
                'priority' => $this->ticket->priority,
                'status' => $this->ticket->status,
                'due_date' => $this->ticket->due_date,
                'story_points' => $this->ticket->story_points,
            ],
        ]);
    }

    public function updatedTicket($property, $value)
    {
        $this->validateOnly("ticket.$property");
        $this->ticket->save();
    }

    public function deleteTicket()
    {
        $this->authorize('delete', $this->ticket);
        $this->ticket->delete();
        return $this->redirect(route('helpdesk.my-tickets'), navigate: true);
    }

    public function toggleDone()
    {
        $this->ticket->is_done = !$this->ticket->is_done;
        $this->ticket->done_at = $this->ticket->is_done ? now() : null;
        $this->ticket->save();
    }

    public function toggleFrog()
    {
        $this->ticket->is_frog = !$this->ticket->is_frog;
        $this->ticket->save();
    }

    public function save()
    {
        $this->validate();
        $this->ticket->save();
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Ticket gespeichert',
        ]);
    }

    public function isDirty()
    {
        return $this->ticket->isDirty();
    }

    public function render()
    {        
        // Teammitglieder für Zuweisung laden
        $teamUsers = Auth::user()
            ->currentTeam
            ->users()
            ->orderBy('name')
            ->get();

        return view('helpdesk::livewire.ticket', [
            'teamUsers' => $teamUsers,
        ]);
    }
}
