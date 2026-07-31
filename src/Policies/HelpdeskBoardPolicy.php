<?php

namespace Platform\Helpdesk\Policies;

use Platform\Core\Models\User;
use Platform\Helpdesk\Models\HelpdeskBoard;

/**
 * Zugriff auf Boards = Ersteller (owns) ODER strukturell im Org-Graphen
 * erreichbar (may). Keine team-pauschale Sichtbarkeit mehr. read < write < manage.
 * Das Board ist das aufgehängte Objekt — Tickets/Knowledge erben davon.
 */
class HelpdeskBoardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function create(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function view(User $user, HelpdeskBoard $board): bool
    {
        return $this->graphAllows($user, $board, 'read');
    }

    public function update(User $user, HelpdeskBoard $board): bool
    {
        return $this->graphAllows($user, $board, 'write');
    }

    public function delete(User $user, HelpdeskBoard $board): bool
    {
        return $this->graphAllows($user, $board, 'manage');
    }

    /**
     * Graph-Autorisierung: Ersteller (owns) ODER strukturell erreichbar (may).
     */
    protected function graphAllows(User $user, HelpdeskBoard $board, string $cap): bool
    {
        if (! $board->id) {
            return false;
        }
        $resolver = app(\Platform\Core\Authz\AuthzResolver::class);

        return $resolver->may($user, $cap, HelpdeskBoard::class, (int) $board->id)
            || $resolver->owns($user, HelpdeskBoard::class, (int) $board->id);
    }
}
