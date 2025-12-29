<?php

namespace Platform\Helpdesk\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Platform\Helpdesk\Models\HelpdeskTicket;

class TicketCreated
{
    use Dispatchable, SerializesModels;

    public HelpdeskTicket $ticket;

    /**
     * Create a new event instance.
     */
    public function __construct(HelpdeskTicket $ticket)
    {
        $this->ticket = $ticket;
    }
}

