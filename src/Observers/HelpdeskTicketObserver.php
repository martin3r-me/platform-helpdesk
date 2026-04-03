<?php

namespace Platform\Helpdesk\Observers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
        Log::info('[HelpdeskTicketObserver] created() fired', [
            'ticket_id' => $ticket->id,
            'team_id' => $ticket->team_id,
            'auth_id' => Auth::id(),
        ]);

        if (! $ticket->team_id) {
            Log::warning('[HelpdeskTicketObserver] No team_id, skipping');
            return;
        }

        $query = User::whereHas('teams', fn ($q) => $q->where('teams.id', $ticket->team_id));

        if (Auth::id()) {
            $query->where('id', '!=', Auth::id());
        }

        $recipients = $query->get();

        Log::info('[HelpdeskTicketObserver] Recipients', [
            'count' => $recipients->count(),
            'ids' => $recipients->pluck('id')->toArray(),
        ]);

        if ($recipients->isEmpty()) {
            return;
        }

        try {
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
            Log::info('[HelpdeskTicketObserver] Dispatch successful');
        } catch (\Throwable $e) {
            Log::error('[HelpdeskTicketObserver] Dispatch failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
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
