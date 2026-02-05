<?php

namespace Platform\Helpdesk\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Helpdesk\Models\HelpdeskTicketGroup;
use Livewire\Attributes\On;

class MyTickets extends Component
{
    #[On('updateDashboard')] 
    public function updateDashboard()
    {
        
    }

    #[On('ticketUpdated')]
    public function ticketsUpdated()
    {
        // Optional: neu rendern bei Event
    }

    #[On('ticketGroupUpdated')]
    public function ticketGroupUpdated()
    {
        // Optional: neu rendern bei Event
    }

    #[On('open-modal-ticket-group-settings')]
    public function openModalTicketGroupSettings($ticketGroupId)
    {
        $this->dispatch('open-modal-ticket-group-settings', ticketGroupId: $ticketGroupId);
    }

    public function render()
    {
        $user = Auth::user();
        $userId = $user->id;
        $startOfMonth = now()->startOfMonth();

        // === 1. INBOX ===
        $inboxTickets = HelpdeskTicket::query()
            ->whereNull('helpdesk_ticket_group_id')
            ->where('is_done', false)
            ->where(function ($q) use ($userId) {
                $q->where(function ($q) use ($userId) {
                    $q->whereNull('helpdesk_board_id')
                      ->where('user_id', $userId); // persönliches Ticket
                })->orWhere(function ($q) use ($userId) {
                    $q->whereNotNull('helpdesk_board_id')
                      ->where('user_in_charge_id', $userId)
                      ->whereNotNull('helpdesk_board_slot_id'); // zuständiges Board-Ticket im Slot
                });
            })
            ->orderBy('order')
            ->get();

        $inbox = (object) [
            'id' => null,
            'label' => 'INBOX',
            'isInbox' => true,
            'tasks' => $inboxTickets,
            'open_count' => $inboxTickets->count(),
            'open_points' => $inboxTickets->sum(fn ($ticket) => $ticket->story_points?->points() ?? 0),
        ];

        // === 2. GRUPPEN ===
        $grouped = HelpdeskTicketGroup::with(['tickets' => function ($q) use ($userId) {
            $q->where('is_done', false)
              ->where(function ($q) use ($userId) {
                  $q->where(function ($q) use ($userId) {
                      $q->whereNull('helpdesk_board_id')
                        ->where('user_id', $userId);
                  })->orWhere(function ($q) use ($userId) {
                      $q->whereNotNull('helpdesk_board_id')
                        ->where('user_in_charge_id', $userId)
                        ->whereNotNull('helpdesk_board_slot_id');
                  });
              })
              ->orderBy('order');
        }])
        ->where('user_id', $userId)
        ->orderBy('order')
        ->get()
        ->map(fn ($group) => (object) [
            'id' => $group->id,
            'label' => $group->name,
            'isInbox' => false,
            'tasks' => $group->tickets,
            'open_count' => $group->tickets->count(),
            'open_points' => $group->tickets->sum(fn ($ticket) => $ticket->story_points?->points() ?? 0),
        ]);

        // === 3. ERLEDIGT ===
        $doneTickets = HelpdeskTicket::query()
            ->where('is_done', true)
            ->where(function ($q) use ($userId) {
                $q->where(function ($q) use ($userId) {
                    $q->whereNull('helpdesk_board_id')
                      ->where('user_id', $userId);
                })->orWhere(function ($q) use ($userId) {
                    $q->whereNotNull('helpdesk_board_id')
                      ->where('user_in_charge_id', $userId)
                      ->whereNotNull('helpdesk_board_slot_id');
                });
            })
            ->orderByDesc('done_at')
            ->get();

        $completedGroup = (object) [
            'id' => 'done',
            'label' => 'Erledigt',
            'isInbox' => false,
            'isDoneGroup' => true,
            'tasks' => $doneTickets,
        ];

        // === 4. KOMPLETTE GRUPPENLISTE ===
        $groups = collect([$inbox])->concat($grouped)->push($completedGroup);

        // === 5. PERFORMANCE-BERECHNUNG ===
        $createdPoints = HelpdeskTicket::query()
            ->withTrashed()
            ->whereDate('created_at', '>=', $startOfMonth)
            ->where(function ($q) use ($userId) {
                $q->where(function ($q) use ($userId) {
                    $q->whereNull('helpdesk_board_id')
                      ->where('user_id', $userId);
                })->orWhere(function ($q) use ($userId) {
                    $q->whereNotNull('helpdesk_board_id')
                      ->where('user_in_charge_id', $userId)
                      ->whereNotNull('helpdesk_board_slot_id');
                });
            })
            ->get()
            ->sum(fn ($ticket) => $ticket->story_points?->points() ?? 0);

        $donePoints = HelpdeskTicket::query()
            ->withTrashed()
            ->whereDate('done_at', '>=', $startOfMonth)
            ->where(function ($q) use ($userId) {
                $q->where(function ($q) use ($userId) {
                    $q->whereNull('helpdesk_board_id')
                      ->where('user_id', $userId);
                })->orWhere(function ($q) use ($userId) {
                    $q->whereNotNull('helpdesk_board_id')
                      ->where('user_in_charge_id', $userId)
                      ->whereNotNull('helpdesk_board_slot_id');
                });
            })
            ->get()
            ->sum(fn ($ticket) => $ticket->story_points?->points() ?? 0);

        $monthlyPerformanceScore = $createdPoints > 0 ? round($donePoints / $createdPoints, 2) : null;

        return view('helpdesk::livewire.my-tickets', [
            'groups' => $groups,
            'monthlyPerformanceScore' => $monthlyPerformanceScore,
            'createdPoints' => $createdPoints,
            'donePoints' => $donePoints,
        ])->layout('platform::layouts.app');
    }

    public function createTicketGroup()
    {
        $user = Auth::user();

        $newTicketGroup = new HelpdeskTicketGroup();
        $newTicketGroup->name = "Neue Gruppe";
        $newTicketGroup->user_id = $user->id;
        $newTicketGroup->team_id = $user->currentTeam->id;
        $newTicketGroup->order = HelpdeskTicketGroup::where('user_id', $user->id)->max('order') + 1;
        $newTicketGroup->save();
    }

    public function createTicket($ticketGroupId = null)
    {
        $user = Auth::user();
        
        $lowestOrder = HelpdeskTicket::where('user_id', Auth::id())
            ->where('team_id', Auth::user()->currentTeam->id)
            ->min('order') ?? 0;

        $order = $lowestOrder - 1;

        $newTicket = HelpdeskTicket::create([
            'user_id' => Auth::id(),
            'user_in_charge_id' => $user->id,
            'helpdesk_board_id' => null,
            'helpdesk_ticket_group_id' => $ticketGroupId,
            'title' => 'Neues Ticket',
            'notes' => null,
            'dod' => null,
            'due_date' => null,
            'priority' => null,
            'status' => null,
            'story_points' => null,
            'team_id' => Auth::user()->currentTeam->id,
            'order' => $order,
        ]);
    }

    public function deleteTicket($ticketId)
    {
        $ticket = HelpdeskTicket::findOrFail($ticketId);
        $this->authorize('delete', $ticket);
        $ticket->delete();
    }

    public function toggleDone($ticketId)
    {
        $ticket = HelpdeskTicket::findOrFail($ticketId);
        $userId = auth()->id();

        // Erlaubt für: Ersteller ODER verantwortliche Person
        if ($ticket->user_id !== $userId && $ticket->user_in_charge_id !== $userId) {
            abort(403);
        }

        $ticket->update([
            'is_done' => ! $ticket->is_done,
            'done_at' => ! $ticket->is_done ? now() : null,
        ]);
    }

    public function updateTicketOrder($groups)
    {
        foreach ($groups as $group) {
            $ticketGroupId = ($group['value'] === 'null' || (int) $group['value'] === 0)
                ? null
                : (int) $group['value'];

            foreach ($group['items'] as $item) {
                $ticket = HelpdeskTicket::find($item['value']);

                if (! $ticket) {
                    continue;
                }

                $ticket->order = $item['order'];
                $ticket->helpdesk_ticket_group_id = $ticketGroupId;
                $ticket->save();
            }
        }
    }

    public function updateTicketGroupOrder($groups)
    {
        foreach ($groups as $ticketGroup) {
            $ticketGroupDb = HelpdeskTicketGroup::find($ticketGroup['value']);
            if ($ticketGroupDb) {
                $ticketGroupDb->order = $ticketGroup['order'];
                $ticketGroupDb->save();
            }
        }
    }
}
