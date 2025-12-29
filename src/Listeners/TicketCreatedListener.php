<?php

namespace Platform\Helpdesk\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Platform\Helpdesk\Events\TicketCreated;
use Platform\Helpdesk\Jobs\ProcessTicketAIJob;

class TicketCreatedListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(TicketCreated $event): void
    {
        try {
            // Dispatche Job für asynchrone Verarbeitung
            ProcessTicketAIJob::dispatch($event->ticket->id);
            
            Log::debug('TicketCreated Event verarbeitet, AI-Job dispatched', [
                'ticket_id' => $event->ticket->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Fehler bei TicketCreatedListener', [
                'ticket_id' => $event->ticket->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

