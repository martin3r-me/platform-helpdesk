<?php

namespace Platform\Helpdesk\Policies;

use Platform\Core\Models\User;
use Platform\Helpdesk\Models\HelpdeskBoard;

class HelpdeskBoardPolicy
{
    /**
     * Darf der User dieses Helpdesk Board sehen?
     */
    public function view(User $user, HelpdeskBoard $board): bool
    {
        // Persönliches Board (Owner)
        if ($board->user_id === $user->id) {
            return true;
        }

        // Team-Board: User ist im aktuellen Team
        if (
            $board->team_id &&
            $user->currentTeam &&
            $board->team_id === $user->currentTeam->id
        ) {
            return true;
        }

        // Kein Zugriff
        return false;
    }

    /**
     * Darf der User dieses Helpdesk Board bearbeiten?
     */
    public function update(User $user, HelpdeskBoard $board): bool
    {
        // Persönliches Board (Owner)
        if ($board->user_id === $user->id) {
            return true;
        }

        // Team-Board: User ist im aktuellen Team
        if (
            $board->team_id &&
            $user->currentTeam &&
            $board->team_id === $user->currentTeam->id
        ) {
            return true;
        }

        // Kein Zugriff
        return false;
    }

    /**
     * Darf der User dieses Helpdesk Board löschen?
     */
    public function delete(User $user, HelpdeskBoard $board): bool
    {
        // Nur der Ersteller darf löschen!
        return $board->user_id === $user->id;
    }

    // Weitere Methoden nach Bedarf (create, assign, invite, ...)
}
