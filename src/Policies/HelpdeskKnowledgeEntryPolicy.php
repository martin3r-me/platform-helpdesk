<?php

namespace Platform\Helpdesk\Policies;

use Platform\Core\Models\User;
use Platform\Helpdesk\Models\HelpdeskKnowledgeEntry;

class HelpdeskKnowledgeEntryPolicy
{
    /**
     * Darf der User Knowledge Entries listen?
     */
    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    /**
     * Darf der User einen Knowledge Entry erstellen?
     */
    public function create(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    /**
     * Darf der User diesen Knowledge Entry sehen?
     */
    public function view(User $user, HelpdeskKnowledgeEntry $entry): bool
    {
        if (
            $entry->team_id &&
            $user->currentTeam &&
            $entry->team_id === $user->currentTeam->id
        ) {
            return true;
        }

        return false;
    }

    /**
     * Darf der User diesen Knowledge Entry bearbeiten?
     */
    public function update(User $user, HelpdeskKnowledgeEntry $entry): bool
    {
        if (
            $entry->team_id &&
            $user->currentTeam &&
            $entry->team_id === $user->currentTeam->id
        ) {
            return true;
        }

        return false;
    }

    /**
     * Darf der User diesen Knowledge Entry löschen?
     */
    public function delete(User $user, HelpdeskKnowledgeEntry $entry): bool
    {
        if (
            $entry->team_id &&
            $user->currentTeam &&
            $entry->team_id === $user->currentTeam->id
        ) {
            return true;
        }

        return false;
    }
}
