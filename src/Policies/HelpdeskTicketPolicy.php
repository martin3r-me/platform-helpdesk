<?php

namespace Platform\Helpdesk\Policies;

use Platform\Core\Models\User;
use Platform\Helpdesk\Models\HelpdeskTicket;

class HelpdeskTicketPolicy
{
    /**
     * Darf der User dieses Ticket sehen?
     */
    public function view(User $user, HelpdeskTicket $ticket): bool
    {
        // Persönliches Ticket (Owner)
        if ($ticket->user_id === $user->id) {
            return true;
        }

        // Team-Ticket: User ist im aktuellen Team
        if (
            $ticket->team_id &&
            $user->currentTeam &&
            $ticket->team_id === $user->currentTeam->id
        ) {
            return true;
        }

        // User ist verantwortlich für das Ticket
        if ($ticket->user_in_charge_id === $user->id) {
            return true;
        }

        // Kein Zugriff
        return false;
    }

    /**
     * Darf der User dieses Ticket bearbeiten?
     */
    public function update(User $user, HelpdeskTicket $ticket): bool
    {
        // Persönliches Ticket (Owner)
        if ($ticket->user_id === $user->id) {
            return true;
        }

        // Team-Ticket: User ist im aktuellen Team
        if (
            $ticket->team_id &&
            $user->currentTeam &&
            $ticket->team_id === $user->currentTeam->id
        ) {
            return true;
        }

        // User ist verantwortlich für das Ticket
        if ($ticket->user_in_charge_id === $user->id) {
            return true;
        }

        // Kein Zugriff
        return false;
    }

    /**
     * Darf der User dieses Ticket löschen?
     */
    public function delete(User $user, HelpdeskTicket $ticket): bool
    {
        // Persönliches Ticket (Owner)
        if ($ticket->user_id === $user->id) {
            return true;
        }

        // Team-Ticket: User ist im aktuellen Team
        if (
            $ticket->team_id &&
            $user->currentTeam &&
            $ticket->team_id === $user->currentTeam->id
        ) {
            return true;
        }

        // Kein Zugriff
        return false;
    }

    /**
     * Darf der User dieses Ticket als erledigt markieren?
     */
    public function complete(User $user, HelpdeskTicket $ticket): bool
    {
        // Persönliches Ticket (Owner)
        if ($ticket->user_id === $user->id) {
            return true;
        }

        // User ist verantwortlich für das Ticket
        if ($ticket->user_in_charge_id === $user->id) {
            return true;
        }

        // Team-Ticket: User ist im aktuellen Team
        if (
            $ticket->team_id &&
            $user->currentTeam &&
            $ticket->team_id === $user->currentTeam->id
        ) {
            return true;
        }

        // Kein Zugriff
        return false;
    }

    // Weitere Methoden nach Bedarf (create, assign, ...)
}
