<?php

namespace Platform\Helpdesk\Policies;

use Platform\Core\Models\User;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Helpdesk\Models\HelpdeskBoard;

/**
 * Zugriff auf Tickets erbt vom Board (graph-erreichbar) — plus Ersteller und
 * Zuständiger. Tickets werden einzeln nicht aufgehängt. read < write < manage.
 * Sperr-Logik (Lock) bleibt erhalten.
 */
class HelpdeskTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function create(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function view(User $user, HelpdeskTicket $ticket): bool
    {
        if ($this->ownerOrAssignee($user, $ticket)) {
            return true;
        }

        return $this->boardGraphAllows($user, $ticket, 'read');
    }

    public function update(User $user, HelpdeskTicket $ticket): bool
    {
        // Gesperrte Tickets nur vom sperrenden User.
        if ($ticket->isLocked() && $ticket->locked_by_user_id !== $user->id) {
            return false;
        }
        if ($ticket->user_id === $user->id) {
            return true;
        }

        return $this->boardGraphAllows($user, $ticket, 'write');
    }

    public function delete(User $user, HelpdeskTicket $ticket): bool
    {
        if ($ticket->isLocked() && $ticket->locked_by_user_id !== $user->id) {
            return false;
        }
        if ($ticket->user_id === $user->id) {
            return true;
        }

        return $this->boardGraphAllows($user, $ticket, 'manage');
    }

    public function complete(User $user, HelpdeskTicket $ticket): bool
    {
        if ($this->ownerOrAssignee($user, $ticket)) {
            return true;
        }

        return $this->boardGraphAllows($user, $ticket, 'write');
    }

    public function lock(User $user, HelpdeskTicket $ticket): bool
    {
        if ($ticket->isLocked()) {
            return false;
        }
        if ($this->ownerOrAssignee($user, $ticket)) {
            return true;
        }

        return $this->boardGraphAllows($user, $ticket, 'write');
    }

    public function unlock(User $user, HelpdeskTicket $ticket): bool
    {
        if (! $ticket->isLocked()) {
            return false;
        }
        // Der sperrende User oder der Ersteller darf immer entsperren.
        if ($ticket->locked_by_user_id === $user->id || $ticket->user_id === $user->id) {
            return true;
        }

        return $this->boardGraphAllows($user, $ticket, 'write');
    }

    protected function ownerOrAssignee(User $user, HelpdeskTicket $ticket): bool
    {
        return $ticket->user_id === $user->id
            || $ticket->user_in_charge_id === $user->id;
    }

    /**
     * Ticket-Zugriff erbt vom Board: Ersteller/erreichbar auf dem Board des Tickets.
     * Ticket ohne Board (persönlich) → nur über Owner/Assignee (oben).
     */
    protected function boardGraphAllows(User $user, HelpdeskTicket $ticket, string $cap): bool
    {
        $boardId = $ticket->helpdesk_board_id;
        if (! $boardId) {
            return false;
        }
        $resolver = app(\Platform\Core\Authz\AuthzResolver::class);

        return $resolver->may($user, $cap, HelpdeskBoard::class, (int) $boardId)
            || $resolver->owns($user, HelpdeskBoard::class, (int) $boardId);
    }
}
