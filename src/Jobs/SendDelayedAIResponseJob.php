<?php

namespace Platform\Helpdesk\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Helpdesk\Services\TicketAIService;

class SendDelayedAIResponseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $ticketId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $ticketId)
    {
        $this->ticketId = $ticketId;
    }

    /**
     * Execute the job.
     */
    public function handle(TicketAIService $aiService): void
    {
        try {
            $ticket = HelpdeskTicket::find($this->ticketId);
            
            if (!$ticket) {
                Log::warning('Ticket nicht gefunden für verzögerte AI-Antwort', [
                    'ticket_id' => $this->ticketId,
                ]);
                return;
            }

            $aiService->processDelayedResponse($ticket);
        } catch (\Exception $e) {
            Log::error('Fehler bei SendDelayedAIResponseJob', [
                'ticket_id' => $this->ticketId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e; // Re-throw für Queue-Retry
        }
    }
}

