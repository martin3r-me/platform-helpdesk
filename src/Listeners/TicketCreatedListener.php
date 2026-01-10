<?php

namespace Platform\Helpdesk\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Platform\Helpdesk\Events\TicketCreated;

class TicketCreatedListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(TicketCreated $event): void
    {
        // Auto-Response-Logik wurde entfernt
    }
}

