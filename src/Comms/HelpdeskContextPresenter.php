<?php

namespace Platform\Helpdesk\Comms;

use Platform\Comms\Contracts\ContextPresenterInterface;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Models\HelpdeskTicket;

class HelpdeskContextPresenter implements ContextPresenterInterface
{
    public function present(string $contextType, int $contextId): ?array
    {
        if ($contextType === HelpdeskTicket::class) {
            $t = HelpdeskTicket::find($contextId);
            if (!$t) return null;
            return [
                'title' => $t->title ?: ('Ticket #' . $t->id),
                'subtitle' => 'Helpdesk Ticket #' . $t->id,
                'url' => route('helpdesk.tickets.show', $t),
            ];
        }

        if ($contextType === HelpdeskBoard::class) {
            $b = HelpdeskBoard::find($contextId);
            if (!$b) return null;
            return [
                'title' => $b->name ?: ('Board #' . $b->id),
                'subtitle' => 'Helpdesk Board #' . $b->id,
                'url' => route('helpdesk.boards.show', $b),
            ];
        }

        return null;
    }
}


