<?php

namespace Platform\Helpdesk\Observers;

use Illuminate\Support\Facades\Auth;
use Platform\Core\Models\User;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Helpdesk\Services\TicketRetrievalService;
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
                'metadata'       => ['url' => $this->ticketUrl($ticket)],
            ],
            $recipients
        );
    }

    /**
     * Ticket aktualisiert — bei Zuweisung den Verantwortlichen benachrichtigen.
     */
    public function updated(HelpdeskTicket $ticket): void
    {
        // Auto-Embedding beim LÖSEN: wird ein Ticket erledigt (oder eine erledigte Kategorie
        // geändert) und hat eine Kategorie, kommt es mit Admission-Gate (Covering-Set) in den
        // Board-Index. „gelöst" = ein Mensch hat's vertreten → kein Self-Reinforcement der
        // Worker-Ratungen. afterResponse: blockiert den Request nicht, braucht keinen Queue-Worker.
        if (($ticket->wasChanged('is_done') || $ticket->wasChanged('helpdesk_board_category_id'))
            && $ticket->is_done && $ticket->helpdesk_board_category_id) {
            $ticketId = $ticket->id;
            dispatch(function () use ($ticketId) {
                $t = HelpdeskTicket::with('category')->find($ticketId);
                if ($t) {
                    app(TicketRetrievalService::class)->indexIfNovel($t);
                }
            })->afterResponse();
        }

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
                            'metadata'       => ['url' => $this->ticketUrl($ticket)],
                        ],
                        [$recipient]
                    );
                }
            }
        }
    }

    /**
     * URL zum Ticket — route() funktioniert nicht in non-web Kontexten (z.B. Inbound-Webhook).
     */
    private function ticketUrl(HelpdeskTicket $ticket): string
    {
        try {
            return route('helpdesk.tickets.show', $ticket->id);
        } catch (\Throwable) {
            return rtrim(config('app.url'), '/') . '/helpdesk/tickets/' . $ticket->id;
        }
    }
}
