<?php

namespace Platform\Helpdesk\Services;

use Illuminate\Support\Facades\Log;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Helpdesk\Models\HelpdeskAiResponse;
use Platform\Helpdesk\Models\HelpdeskBoardAiSettings;
use Platform\Helpdesk\Services\TicketKnowledgeService;

class TicketResponseService
{
    protected ?\Platform\AiAssistant\Services\OpenAIService $openAIService = null;
    protected TicketKnowledgeService $knowledgeService;

    public function __construct(TicketKnowledgeService $knowledgeService)
    {
        $this->knowledgeService = $knowledgeService;
        
        if (class_exists(\Platform\AiAssistant\Services\OpenAIService::class)) {
            $this->openAIService = app(\Platform\AiAssistant\Services\OpenAIService::class);
        }
    }

    /**
     * Generiert eine sofortige Bestätigung
     */
    public function generateImmediateResponse(
        HelpdeskTicket $ticket,
        HelpdeskBoardAiSettings $settings
    ): ?HelpdeskAiResponse {
        if (!$this->openAIService) {
            Log::warning('OpenAI Service nicht verfügbar für sofortige Antwort', [
                'ticket_id' => $ticket->id,
            ]);
            return null;
        }

        try {
            $systemPrompt = $this->buildImmediateResponsePrompt();
            $userPrompt = $this->buildImmediateResponseUserPrompt($ticket);

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ];

            $response = $this->openAIService->chat($messages, $settings->ai_model);

            if (!$response['ok']) {
                Log::error('OpenAI sofortige Antwort fehlgeschlagen', [
                    'ticket_id' => $ticket->id,
                    'error' => $response['error'] ?? 'Unknown error',
                ]);
                return null;
            }

            $responseText = trim($response['content']);
            $confidence = $this->estimateConfidence($responseText);

            // Speichere Response
            $aiResponse = HelpdeskAiResponse::create([
                'helpdesk_ticket_id' => $ticket->id,
                'comms_channel_id' => $ticket->comms_channel_id,
                'response_type' => 'immediate',
                'response_text' => $responseText,
                'confidence_score' => $confidence,
                'ai_model_used' => $settings->ai_model,
            ]);

            return $aiResponse;
        } catch (\Exception $e) {
            Log::error('Fehler bei sofortiger Antwort-Generierung', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Generiert eine verzögerte vollständige Antwort
     */
    public function generateDelayedResponse(
        HelpdeskTicket $ticket,
        HelpdeskBoardAiSettings $settings
    ): ?HelpdeskAiResponse {
        if (!$this->openAIService) {
            Log::warning('OpenAI Service nicht verfügbar für verzögerte Antwort', [
                'ticket_id' => $ticket->id,
            ]);
            return null;
        }

        try {
            // Suche ähnliche Einträge in Knowledge Base
            $categories = $settings->knowledge_base_categories ?? [];
            $similarEntries = $this->knowledgeService->searchSimilar($ticket, $categories);
            
            $classification = $ticket->aiClassifications()->first();
            $category = $classification?->category;

            $systemPrompt = $this->buildDelayedResponsePrompt();
            $userPrompt = $this->buildDelayedResponseUserPrompt($ticket, $similarEntries, $category);

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ];

            $response = $this->openAIService->chat($messages, $settings->ai_model);

            if (!$response['ok']) {
                Log::error('OpenAI verzögerte Antwort fehlgeschlagen', [
                    'ticket_id' => $ticket->id,
                    'error' => $response['error'] ?? 'Unknown error',
                ]);
                return null;
            }

            $responseText = trim($response['content']);
            $confidence = $this->estimateConfidence($responseText);

            // Prüfe ob Human-in-the-Loop nötig
            $needsReview = $settings->human_in_loop_enabled 
                && $confidence < $settings->human_in_loop_threshold;

            // Finde verwendeten KB-Eintrag (falls vorhanden)
            $kbEntryId = null;
            if ($similarEntries->isNotEmpty()) {
                $kbEntryId = $similarEntries->first()->id;
            }

            // Speichere Response
            $aiResponse = HelpdeskAiResponse::create([
                'helpdesk_ticket_id' => $ticket->id,
                'comms_channel_id' => $ticket->comms_channel_id,
                'response_type' => 'delayed',
                'response_text' => $responseText,
                'confidence_score' => $confidence,
                'ai_model_used' => $settings->ai_model,
                'knowledge_base_entry_id' => $kbEntryId,
                'sent_at' => $needsReview ? null : now(), // Nur senden wenn kein Review nötig
            ]);

            return $aiResponse;
        } catch (\Exception $e) {
            Log::error('Fehler bei verzögerter Antwort-Generierung', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * System-Prompt für sofortige Bestätigung
     */
    protected function buildImmediateResponsePrompt(): string
    {
        return <<<PROMPT
Du bist ein Helpdesk-Assistent. Erstelle eine kurze, freundliche Bestätigung für ein neues Ticket auf Deutsch.

Die Antwort sollte folgende Struktur haben:
1. Kurze Zusammenfassung des Problems (1-2 Sätze)
2. Frage: "Haben wir Ihr Problem richtig erkannt?" (nur wenn das Problem unklar ist)
3. SLA-Kommunikation: "Wir bearbeiten Ihr Ticket innerhalb von X Stunden."

Antworte direkt, ohne zusätzliche Formatierung oder Markdown.
PROMPT;
    }

    /**
     * User-Prompt für sofortige Bestätigung
     */
    protected function buildImmediateResponseUserPrompt(HelpdeskTicket $ticket): string
    {
        $prompt = "Erstelle eine sofortige Bestätigung für folgendes Ticket:\n\n";
        $prompt .= "Titel: {$ticket->title}\n";
        
        if ($ticket->description) {
            $prompt .= "Beschreibung: {$ticket->description}\n";
        }
        
        // SLA-Informationen
        if ($ticket->sla) {
            $responseTime = $ticket->sla->response_time_hours;
            $resolutionTime = $ticket->sla->resolution_time_hours;
            
            $prompt .= "\nSLA:\n";
            if ($responseTime) {
                $prompt .= "- Reaktionszeit: {$responseTime} Stunden\n";
            }
            if ($resolutionTime) {
                $prompt .= "- Lösungszeit: {$resolutionTime} Stunden\n";
            }
        }

        return $prompt;
    }

    /**
     * System-Prompt für verzögerte vollständige Antwort
     */
    protected function buildDelayedResponsePrompt(): string
    {
        return <<<PROMPT
Du bist ein Helpdesk-Assistent. Erstelle eine hilfreiche, professionelle Antwort auf Deutsch basierend auf der Knowledge Base und ähnlichen gelösten Tickets.

Die Antwort sollte:
- Das Problem klar ansprechen
- Konkrete Lösungsvorschläge bieten
- Professionell und freundlich sein
- Auf Deutsch verfasst sein
- Keine Markdown-Formatierung verwenden

Falls keine passende Lösung in der Knowledge Base gefunden wurde, antworte trotzdem hilfreich und professionell.
PROMPT;
    }

    /**
     * User-Prompt für verzögerte Antwort
     */
    protected function buildDelayedResponseUserPrompt(
        HelpdeskTicket $ticket,
        $similarEntries,
        ?string $category
    ): string {
        $prompt = "Erstelle eine vollständige Antwort für folgendes Ticket:\n\n";
        $prompt .= "Titel: {$ticket->title}\n";
        
        if ($ticket->description) {
            $prompt .= "Beschreibung: {$ticket->description}\n";
        }
        
        if ($category) {
            $prompt .= "Kategorie: {$category}\n";
        }
        
        if ($similarEntries->isNotEmpty()) {
            $prompt .= "\nÄhnliche Lösungen aus der Knowledge Base:\n";
            foreach ($similarEntries->take(3) as $entry) {
                $prompt .= "- {$entry->title}: {$entry->content}\n";
            }
        }

        return $prompt;
    }

    /**
     * Schätzt die Confidence basierend auf Response-Text
     * (vereinfacht - könnte später durch KI verbessert werden)
     */
    protected function estimateConfidence(string $responseText): float
    {
        // Einfache Heuristik: Länge und Struktur
        $length = strlen($responseText);
        $hasQuestion = strpos($responseText, '?') !== false;
        $hasSolution = preg_match('/\b(lösung|hilfe|schritt|anleitung)\b/i', $responseText);
        
        $confidence = 0.7; // Basis
        
        if ($length > 100) {
            $confidence += 0.1;
        }
        
        if ($hasSolution) {
            $confidence += 0.1;
        }
        
        if ($hasQuestion) {
            $confidence -= 0.05; // Frage deutet auf Unsicherheit
        }
        
        return min(1.0, max(0.0, $confidence));
    }
}

