<?php

namespace Platform\Helpdesk\Observers;

use Illuminate\Support\Facades\Auth;
use Platform\Core\Models\User;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Notifications\NotificationDispatcher;

class HelpdeskTicketObserver
{
    /**
     * Neues Ticket erstellt — alle Team-Mitglieder benachrichtigen.
     */
    public function created(HelpdeskTicket $ticket): void
    {
        if (! $ticket->team_id) {
            return;
        }

        $query = User::whereHas('teams', fn ($q) => $q->where('teams.id', $ticket->team_id));

        if (Auth::id()) {
            $query->where('id', '!=', Auth::id());
        }

        $recipients = $query->get();

        if ($recipients->isEmpty()) {
            return;
        }

        app(NotificationDispatcher::class)->dispatch(
            'helpdesk.ticket.created',
            [
                'title'          => 'Neues Ticket',
                'message'        => "Neues Ticket: \"{$ticket->title}\"",
                'noticable_type' => HelpdeskTicket::class,
                'noticable_id'   => $ticket->id,
                'team_id'        => $ticket->team_id,
                'metadata'       => ['url' => route('helpdesk.tickets.show', $ticket->id)],
            ],
            $recipients
        );
    }

    /**
     * Ticket aktualisiert — bei Zuweisung den Verantwortlichen benachrichtigen.
     */
    public function updated(HelpdeskTicket $ticket): void
    {
        // Ticket wurde jemandem zugewiesen
        if ($ticket->wasChanged('user_in_charge_id') && $ticket->user_in_charge_id) {
            if ($ticket->user_in_charge_id !== Auth::id()) {
                $recipient = $ticket->userInCharge;

                if ($recipient) {
                    app(NotificationDispatcher::class)->dispatch(
                        'helpdesk.ticket.assigned',
                        [
                            'title'          => 'Ticket zugewiesen',
                            'message'        => "Dir wurde das Ticket \"{$ticket->title}\" zugewiesen.",
                            'noticable_type' => HelpdeskTicket::class,
                            'noticable_id'   => $ticket->id,
                            'team_id'        => $ticket->team_id,
                            'metadata'       => ['url' => route('helpdesk.tickets.show', $ticket->id)],
                        ],
                        [$recipient]
                    );
                }
            }
        }
    }
}
