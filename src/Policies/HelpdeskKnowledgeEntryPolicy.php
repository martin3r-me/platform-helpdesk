<?php

namespace Platform\Helpdesk\Policies;

use Platform\Core\Models\User;
use Platform\Helpdesk\Models\HelpdeskKnowledgeEntry;
use Platform\Helpdesk\Models\HelpdeskBoard;

/**
 * Knowledge erbt den Zugriff von ihrem Board (graph-erreichbar). Kein Ersteller-
 * Konzept (keine user_id); board-lose Einträge sind nicht sichtbar, bis sie an
 * ein Board gehängt werden. read < write < manage.
 */
class HelpdeskKnowledgeEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function create(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function view(User $user, HelpdeskKnowledgeEntry $entry): bool
    {
        return $this->boardGraphAllows($user, $entry, 'read');
    }

    public function update(User $user, HelpdeskKnowledgeEntry $entry): bool
    {
        return $this->boardGraphAllows($user, $entry, 'write');
    }

    public function delete(User $user, HelpdeskKnowledgeEntry $entry): bool
    {
        return $this->boardGraphAllows($user, $entry, 'manage');
    }

    protected function boardGraphAllows(User $user, HelpdeskKnowledgeEntry $entry, string $cap): bool
    {
        $boardId = $entry->helpdesk_board_id;
        if (! $boardId) {
            return false;
        }
        $resolver = app(\Platform\Core\Authz\AuthzResolver::class);

        return $resolver->may($user, $cap, HelpdeskBoard::class, (int) $boardId)
            || $resolver->owns($user, HelpdeskBoard::class, (int) $boardId);
    }
}
