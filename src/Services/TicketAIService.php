<?php

namespace Platform\Helpdesk\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Helpdesk\Models\HelpdeskBoardAiSettings;
use Platform\Helpdesk\Jobs\SendDelayedAIResponseJob;

class TicketAIService
{
    protected TicketClassificationService $classificationService;
    protected TicketResponseService $responseService;
    protected TicketCommsService $commsService;

    public function __construct(
        TicketClassificationService $classificationService,
        TicketResponseService $responseService,
        TicketCommsService $commsService
    ) {
        $this->classificationService = $classificationService;
        $this->responseService = $responseService;
        $this->commsService = $commsService;
    }

    /**
     * Verarbeitet ein neues Ticket mit KI
     */
    public function processNewTicket(HelpdeskTicket $ticket): void
    {
        try {
            // Hole AI-Settings vom Board
            $settings = $ticket->getAiSettings();
            
            if (!$settings) {
                Log::debug('Keine AI-Settings für Ticket gefunden', [
                    'ticket_id' => $ticket->id,
                ]);
                return;
            }

            // Prüfe ob KI aktiviert ist
            if (!$this->isAiEnabled($ticket, $settings)) {
                Log::debug('KI ist für Ticket nicht aktiviert', [
                    'ticket_id' => $ticket->id,
                    'escalated' => $ticket->isEscalated(),
                    'ai_enabled_for_escalated' => $settings->ai_enabled_for_escalated,
                ]);
                return;
            }

            // 1. Klassifizierung
            $classification = $this->classificationService->classify($ticket, $settings);
            
            if ($classification) {
                // Wende Klassifizierung an (wenn Confidence hoch genug)
                $this->applyClassification($ticket, $classification, $settings);
            }

            // 2. Sofortige Bestätigung (wenn aktiviert)
            if ($settings->auto_response_immediate_enabled) {
                $immediateResponse = $this->responseService->generateImmediateResponse($ticket, $settings);
                
                if ($immediateResponse && $immediateResponse->confidence_score >= $settings->auto_response_confidence_threshold) {
                    // Sende sofort
                    $this->commsService->sendResponse($ticket, $immediateResponse);
                }
            }

            // 3. Verzögerte Auto-Response planen (wenn aktiviert)
            if ($settings->auto_response_enabled) {
                $delayMinutes = $settings->auto_response_timing_minutes;
                SendDelayedAIResponseJob::dispatch($ticket->id)
                    ->delay(now()->addMinutes($delayMinutes));
            }

            Log::info('Ticket AI-Verarbeitung abgeschlossen', [
                'ticket_id' => $ticket->id,
                'classification' => $classification !== null,
                'immediate_response' => $settings->auto_response_immediate_enabled,
                'delayed_response_scheduled' => $settings->auto_response_enabled,
            ]);
        } catch (\Exception $e) {
            Log::error('Fehler bei Ticket AI-Verarbeitung', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Prüft ob KI für dieses Ticket aktiviert ist
     */
    protected function isAiEnabled(
        HelpdeskTicket $ticket,
        HelpdeskBoardAiSettings $settings
    ): bool {
        // Wenn Ticket eskaliert ist und KI für Eskalationen deaktiviert
        if ($ticket->isEscalated() && !$settings->ai_enabled_for_escalated) {
            return false;
        }

        // Mindestens eine Funktion muss aktiviert sein
        return $settings->auto_response_enabled 
            || $settings->auto_response_immediate_enabled
            || $settings->auto_assignment_enabled;
    }

    /**
     * Wendet Klassifizierung auf Ticket an
     */
    protected function applyClassification(
        HelpdeskTicket $ticket,
        $classification,
        HelpdeskBoardAiSettings $settings
    ): void {
        // Priorität setzen (wenn Confidence hoch genug)
        if ($classification->priority_prediction 
            && $classification->confidence_score >= 0.7
        ) {
            try {
                $priorityEnum = \Platform\Helpdesk\Enums\TicketPriority::from($classification->priority_prediction);
                $ticket->update(['priority' => $priorityEnum]);
            } catch (\Exception $e) {
                Log::warning('Konnte Priorität nicht setzen', [
                    'ticket_id' => $ticket->id,
                    'priority' => $classification->priority_prediction,
                ]);
            }
        }

        // Auto-Assignment (wenn aktiviert und Confidence hoch genug)
        if ($settings->auto_assignment_enabled 
            && $classification->assignee_suggestion_user_id
            && $classification->confidence_score >= $settings->auto_assignment_confidence_threshold
        ) {
            $ticket->update([
                'user_in_charge_id' => $classification->assignee_suggestion_user_id,
            ]);
        }
    }

    /**
     * Generiert verzögerte Antwort (wird vom Job aufgerufen)
     */
    public function processDelayedResponse(HelpdeskTicket $ticket): void
    {
        $settings = $ticket->getAiSettings();
        
        if (!$settings || !$settings->auto_response_enabled) {
            return;
        }

        // Prüfe ob Ticket noch offen und nicht eskaliert
        if ($ticket->is_done || ($ticket->isEscalated() && !$settings->ai_enabled_for_escalated)) {
            Log::debug('Ticket ist erledigt oder eskaliert, überspringe verzögerte Antwort', [
                'ticket_id' => $ticket->id,
            ]);
            return;
        }

        // Generiere und sende Antwort
        $response = $this->responseService->generateDelayedResponse($ticket, $settings);
        
        if ($response && $response->sent_at) {
            // Response wurde bereits gesendet (kein Review nötig)
            $this->commsService->sendResponse($ticket, $response);
        } elseif ($response) {
            // Response benötigt Review (Human-in-the-Loop)
            Log::info('AI-Response benötigt Review', [
                'ticket_id' => $ticket->id,
                'response_id' => $response->id,
                'confidence' => $response->confidence_score,
            ]);
        }
    }
}

