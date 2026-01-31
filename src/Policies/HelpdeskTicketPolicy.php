<?php

namespace Platform\Helpdesk\Policies;

use Platform\Core\Models\User;
use Platform\Helpdesk\Models\HelpdeskTicket;

class HelpdeskTicketPolicy
{
    /**
     * Darf der User Tickets listen?
     */
    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    /**
     * Darf der User ein Ticket erstellen?
     */
    public function create(User $user): bool
    {
        return $user->currentTeam !== null;
    }

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
        // Gesperrte Tickets können nur vom sperrenden User bearbeitet werden
        if ($ticket->isLocked() && $ticket->locked_by_user_id !== $user->id) {
            return false;
        }

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
     * Darf der User dieses Ticket sperren?
     */
    public function lock(User $user, HelpdeskTicket $ticket): bool
    {
        // Bereits gesperrte Tickets können nicht erneut gesperrt werden
        if ($ticket->isLocked()) {
            return false;
        }

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

        return false;
    }

    /**
     * Darf der User dieses Ticket entsperren?
     */
    public function unlock(User $user, HelpdeskTicket $ticket): bool
    {
        // Nur gesperrte Tickets können entsperrt werden
        if (!$ticket->isLocked()) {
            return false;
        }

        // Der User der gesperrt hat, darf entsperren
        if ($ticket->locked_by_user_id === $user->id) {
            return true;
        }

        // Persönliches Ticket (Owner)
        if ($ticket->user_id === $user->id) {
            return true;
        }

        // Team-Ticket: User ist im aktuellen Team (für Notfall-Entsperrung)
        if (
            $ticket->team_id &&
            $user->currentTeam &&
            $ticket->team_id === $user->currentTeam->id
        ) {
            return true;
        }

        return false;
    }

    /**
     * Darf der User dieses Ticket löschen?
     */
    public function delete(User $user, HelpdeskTicket $ticket): bool
    {
        // Gesperrte Tickets können nur vom sperrenden User gelöscht werden
        if ($ticket->isLocked() && $ticket->locked_by_user_id !== $user->id) {
            return false;
        }

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
