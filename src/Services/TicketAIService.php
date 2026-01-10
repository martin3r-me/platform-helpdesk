<?php

namespace Platform\Helpdesk\Services;

use Illuminate\Support\Facades\Log;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Helpdesk\Models\HelpdeskBoardAiSettings;

class TicketAIService
{
    protected TicketClassificationService $classificationService;

    public function __construct(
        TicketClassificationService $classificationService
    ) {
        $this->classificationService = $classificationService;
    }

    /**
     * Verarbeitet ein neues Ticket mit KI (nur Klassifizierung)
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

            // Klassifizierung
            $classification = $this->classificationService->classify($ticket, $settings);
            
            if ($classification) {
                // Wende Klassifizierung an (wenn Confidence hoch genug)
                $this->applyClassification($ticket, $classification, $settings);
            }

            Log::info('Ticket AI-Verarbeitung abgeschlossen', [
                'ticket_id' => $ticket->id,
                'classification' => $classification !== null,
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
        return $settings->auto_assignment_enabled;
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

}

