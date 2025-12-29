<?php

namespace Platform\Helpdesk\Services;

use Illuminate\Support\Facades\Log;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Helpdesk\Models\HelpdeskAiClassification;
use Platform\Helpdesk\Models\HelpdeskBoardAiSettings;

class TicketClassificationService
{
    protected ?\Platform\AiAssistant\Services\OpenAIService $openAIService = null;

    public function __construct()
    {
        if (class_exists(\Platform\AiAssistant\Services\OpenAIService::class)) {
            $this->openAIService = app(\Platform\AiAssistant\Services\OpenAIService::class);
        }
    }

    /**
     * Klassifiziert ein Ticket mit KI
     */
    public function classify(HelpdeskTicket $ticket, HelpdeskBoardAiSettings $settings): ?HelpdeskAiClassification
    {
        if (!$this->openAIService) {
            Log::warning('OpenAI Service nicht verfügbar für Ticket-Klassifizierung', [
                'ticket_id' => $ticket->id,
            ]);
            return null;
        }

        try {
            $systemPrompt = $this->buildClassificationPrompt($ticket);
            $userPrompt = $this->buildUserPrompt($ticket);

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ];

            $response = $this->openAIService->chat($messages, $settings->ai_model);

            if (!$response['ok']) {
                Log::error('OpenAI Klassifizierung fehlgeschlagen', [
                    'ticket_id' => $ticket->id,
                    'error' => $response['error'] ?? 'Unknown error',
                ]);
                return null;
            }

            $content = $response['content'];
            $classificationData = $this->parseClassificationResponse($content);

            // Speichere Klassifizierung
            $classification = HelpdeskAiClassification::updateOrCreate(
                ['helpdesk_ticket_id' => $ticket->id],
                [
                    'category' => $classificationData['category'] ?? null,
                    'priority_prediction' => $classificationData['priority'] ?? null,
                    'assignee_suggestion_user_id' => $classificationData['assignee_user_id'] ?? null,
                    'confidence_score' => $classificationData['confidence'] ?? 0.0,
                    'ai_model_used' => $settings->ai_model,
                    'raw_response' => [
                        'content' => $content,
                        'usage' => $response['usage'] ?? null,
                    ],
                ]
            );

            return $classification;
        } catch (\Exception $e) {
            Log::error('Fehler bei Ticket-Klassifizierung', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Erstellt den System-Prompt für Klassifizierung
     */
    protected function buildClassificationPrompt(HelpdeskTicket $ticket): string
    {
        return <<<PROMPT
Du bist ein Helpdesk-Klassifizierungs-Assistent. Analysiere das folgende Ticket und gib eine JSON-Antwort zurück.

Antworte NUR mit einem gültigen JSON-Objekt im folgenden Format:
{
  "category": "Kategorie (z.B. Technik, Buchhaltung, Support, Allgemein)",
  "priority": "low|normal|high",
  "tags": ["tag1", "tag2"],
  "assignee_suggestion": "Begründung für Zuweisung (optional)",
  "confidence": 0.0-1.0
}

Wichtig:
- Antworte NUR mit JSON, keine zusätzlichen Erklärungen
- Kategorie sollte präzise sein
- Priority basierend auf Dringlichkeit und Auswirkung
- Tags sollten relevant und hilfreich sein
- Confidence: Wie sicher bist du bei der Klassifizierung?
PROMPT;
    }

    /**
     * Erstellt den User-Prompt mit Ticket-Informationen
     */
    protected function buildUserPrompt(HelpdeskTicket $ticket): string
    {
        $prompt = "Ticket analysieren:\n\n";
        $prompt .= "Titel: {$ticket->title}\n";
        
        if ($ticket->description) {
            $prompt .= "Beschreibung: {$ticket->description}\n";
        }
        
        if ($ticket->helpdeskBoard) {
            $prompt .= "Board: {$ticket->helpdeskBoard->name}\n";
        }
        
        if ($ticket->userInCharge) {
            $prompt .= "Aktuell verantwortlich: {$ticket->userInCharge->name}\n";
        }
        
        if ($ticket->priority) {
            $prompt .= "Aktuelle Priorität: {$ticket->priority->label()}\n";
        }

        return $prompt;
    }

    /**
     * Parst die JSON-Antwort von OpenAI
     */
    protected function parseClassificationResponse(string $content): array
    {
        // Versuche JSON zu extrahieren (falls zusätzlicher Text vorhanden)
        $jsonStart = strpos($content, '{');
        $jsonEnd = strrpos($content, '}');
        
        if ($jsonStart !== false && $jsonEnd !== false) {
            $json = substr($content, $jsonStart, $jsonEnd - $jsonStart + 1);
            $data = json_decode($json, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
        }

        // Fallback: Versuche gesamten Content als JSON
        $data = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $data;
        }

        Log::warning('Konnte Klassifizierungs-Response nicht parsen', [
            'content' => $content,
            'json_error' => json_last_error_msg(),
        ]);

        return [];
    }
}

