<?php

namespace Platform\Helpdesk\Services;

use Platform\Helpdesk\Contracts\ErrorTrackerContract;
use Platform\Helpdesk\Enums\TicketPriority;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Models\HelpdeskBoardErrorSettings;
use Platform\Helpdesk\Models\HelpdeskErrorOccurrence;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Integrations\Models\IntegrationsGithubRepository;
use Platform\Integrations\Services\IntegrationAccountLinkService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class ErrorTrackingService implements ErrorTrackerContract
{
    /**
     * Erfasst einen Fehler und erstellt bei Bedarf ein Ticket
     */
    public function capture(Throwable $e, array $context = []): ?HelpdeskErrorOccurrence
    {
        try {
            $httpCode = $context['http_code'] ?? null;
            $isConsole = $context['is_console'] ?? false;
            $occurrences = [];

            $enabledSettings = HelpdeskBoardErrorSettings::where('enabled', true)->get();

            foreach ($enabledSettings as $settings) {
                // Console-Errors nur erfassen, wenn explizit aktiviert
                if ($isConsole && !$settings->capture_console_errors) {
                    continue;
                }

                if (!$settings->shouldCaptureCode($httpCode)) {
                    continue;
                }

                $occurrence = $this->processError($e, $settings, $context);
                if ($occurrence) {
                    $occurrences[] = $occurrence;
                }
            }

            return $occurrences[0] ?? null;
        } catch (Throwable $captureError) {
            Log::error('ErrorTrackingService: Fehler beim Erfassen', [
                'error' => $captureError->getMessage(),
                'original_error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Verarbeitet einen Fehler für ein bestimmtes Board
     */
    protected function processError(
        Throwable $e,
        HelpdeskBoardErrorSettings $settings,
        array $context
    ): ?HelpdeskErrorOccurrence {
        $httpCode = $context['http_code'] ?? null;
        $hash = HelpdeskErrorOccurrence::generateHash($e, $httpCode);

        $existing = HelpdeskErrorOccurrence::findExistingInDedupeWindow(
            $settings->helpdesk_board_id,
            $hash,
            $settings->dedupe_window_hours
        );

        if ($existing) {
            return $this->updateExistingOccurrence($existing, $e, $settings, $context);
        }

        return $this->createNewOccurrence($e, $settings, $context, $hash);
    }

    /**
     * Aktualisiert eine existierende Occurrence
     */
    protected function updateExistingOccurrence(
        HelpdeskErrorOccurrence $occurrence,
        Throwable $e,
        HelpdeskBoardErrorSettings $settings,
        array $context
    ): HelpdeskErrorOccurrence {
        $sampleData = $this->buildSampleData($e, $settings, $context);

        return $occurrence->recordOccurrence($sampleData);
    }

    /**
     * Erstellt eine neue Occurrence
     */
    protected function createNewOccurrence(
        Throwable $e,
        HelpdeskBoardErrorSettings $settings,
        array $context,
        string $hash
    ): HelpdeskErrorOccurrence {
        $httpCode = $context['http_code'] ?? null;
        $sampleData = $this->buildSampleData($e, $settings, $context);

        $occurrence = HelpdeskErrorOccurrence::create([
            'helpdesk_board_id' => $settings->helpdesk_board_id,
            'team_id' => $settings->team_id,
            'error_hash' => $hash,
            'exception_class' => get_class($e),
            'message' => $this->truncateMessage($e->getMessage()),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'http_code' => $httpCode,
            'occurrence_count' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'sample_data' => $sampleData,
            'status' => HelpdeskErrorOccurrence::STATUS_OPEN,
        ]);

        if ($settings->auto_create_ticket) {
            $ticket = $this->createTicket($occurrence, $settings, $httpCode);
            if ($ticket) {
                $occurrence->update(['helpdesk_ticket_id' => $ticket->id]);
            }
        }

        Log::info('ErrorTrackingService: Neue Error Occurrence erstellt', [
            'occurrence_id' => $occurrence->id,
            'exception_class' => $occurrence->exception_class,
            'http_code' => $httpCode,
        ]);

        return $occurrence;
    }

    /**
     * Baut die Sample-Daten zusammen
     */
    protected function buildSampleData(
        Throwable $e,
        HelpdeskBoardErrorSettings $settings,
        array $context
    ): array {
        $data = [
            'url' => $context['url'] ?? request()->fullUrl() ?? null,
            'method' => $context['method'] ?? request()->method() ?? null,
            'user_id' => $context['user_id'] ?? auth()->id() ?? null,
            'user_agent' => $context['user_agent'] ?? request()->userAgent() ?? null,
            'ip' => $context['ip'] ?? request()->ip() ?? null,
            'timestamp' => now()->toIso8601String(),
        ];

        if ($settings->include_stack_trace) {
            $data['stack_trace'] = $this->getStackTrace($e, $settings->stack_trace_limit);
        }

        if (isset($context['extra'])) {
            $data['extra'] = $context['extra'];
        }

        return $data;
    }

    /**
     * Konstanten für Error-Tracking Defaults
     * Diese werden verwendet wenn kein User authentifiziert ist (z.B. Console/Scheduler)
     */
    protected const DEFAULT_ERROR_USER_ID = 21;
    protected const DEFAULT_GITHUB_REPOSITORY_ID = 4;

    /**
     * Erstellt ein Ticket für eine Error Occurrence
     */
    protected function createTicket(
        HelpdeskErrorOccurrence $occurrence,
        HelpdeskBoardErrorSettings $settings,
        ?int $httpCode
    ): ?HelpdeskTicket {
        try {
            $board = HelpdeskBoard::find($settings->helpdesk_board_id);
            if (!$board) {
                return null;
            }

            $priority = $this->mapPriority($settings->getPriorityForCode($httpCode));
            $title = $this->buildTicketTitle($occurrence);

            // User-ID ermitteln: Auth-User oder Fallback für Console/Scheduler
            $userId = Auth::id() ?? self::DEFAULT_ERROR_USER_ID;

            $ticket = HelpdeskTicket::create([
                'helpdesk_board_id' => $board->id,
                'team_id' => $settings->team_id,
                'user_id' => $userId,
                'title' => $title,
                'notes' => $this->buildTicketDescription($occurrence),
                'priority' => $priority,
            ]);

            // GitHub Repository verknüpfen (für Fehler aus dem Platforms-Core Repo)
            $this->linkDefaultGithubRepository($ticket);

            Log::info('ErrorTrackingService: Ticket erstellt', [
                'ticket_id' => $ticket->id,
                'occurrence_id' => $occurrence->id,
                'user_id' => $userId,
            ]);

            return $ticket;
        } catch (Throwable $e) {
            Log::error('ErrorTrackingService: Fehler beim Erstellen des Tickets', [
                'error' => $e->getMessage(),
                'occurrence_id' => $occurrence->id,
            ]);

            return null;
        }
    }

    /**
     * Verknüpft das Default GitHub Repository mit dem Ticket
     * Wird für Error-Tickets verwendet, um automatisch das Platforms-Core Repo zu verknüpfen
     */
    protected function linkDefaultGithubRepository(HelpdeskTicket $ticket): void
    {
        try {
            $githubRepository = IntegrationsGithubRepository::find(self::DEFAULT_GITHUB_REPOSITORY_ID);
            if (!$githubRepository) {
                Log::warning('ErrorTrackingService: Default GitHub Repository nicht gefunden', [
                    'repository_id' => self::DEFAULT_GITHUB_REPOSITORY_ID,
                    'ticket_id' => $ticket->id,
                ]);
                return;
            }

            $linkService = app(IntegrationAccountLinkService::class);
            $linkService->linkGithubRepository($githubRepository, $ticket);

            Log::info('ErrorTrackingService: GitHub Repository verknüpft', [
                'ticket_id' => $ticket->id,
                'repository_id' => self::DEFAULT_GITHUB_REPOSITORY_ID,
                'repository_name' => $githubRepository->full_name ?? 'unknown',
            ]);
        } catch (Throwable $e) {
            // Fehler beim Verknüpfen sollte nicht die Ticket-Erstellung verhindern
            Log::warning('ErrorTrackingService: GitHub Repository konnte nicht verknüpft werden', [
                'error' => $e->getMessage(),
                'ticket_id' => $ticket->id,
            ]);
        }
    }

    /**
     * Baut den Ticket-Titel zusammen
     */
    protected function buildTicketTitle(HelpdeskErrorOccurrence $occurrence): string
    {
        $shortClass = $occurrence->getShortExceptionClass();
        $httpCode = $occurrence->http_code ? "[{$occurrence->http_code}] " : '';
        $message = $this->truncateMessage($occurrence->message, 80);

        return "{$httpCode}{$shortClass}: {$message}";
    }

    /**
     * Baut die Ticket-Beschreibung zusammen
     */
    protected function buildTicketDescription(HelpdeskErrorOccurrence $occurrence): string
    {
        $lines = [
            "**Exception:** {$occurrence->exception_class}",
            "**Message:** {$occurrence->message}",
            "**Location:** {$occurrence->getFormattedLocation()}",
        ];

        if ($occurrence->http_code) {
            $lines[] = "**HTTP Code:** {$occurrence->http_code}";
        }

        $sampleData = $occurrence->sample_data ?? [];
        if (!empty($sampleData['url'])) {
            $lines[] = "**URL:** {$sampleData['url']}";
        }

        $lines[] = '';
        $lines[] = "**First Seen:** {$occurrence->first_seen_at->format('Y-m-d H:i:s')}";
        $lines[] = "**Error Occurrence ID:** {$occurrence->id}";

        return implode("\n", $lines);
    }

    /**
     * Mappt die Priority-Bezeichnung auf das Enum
     */
    protected function mapPriority(string $priority): TicketPriority
    {
        return match ($priority) {
            'high' => TicketPriority::High,
            'low' => TicketPriority::Low,
            default => TicketPriority::Normal,
        };
    }

    /**
     * Truncates a message to a maximum length
     */
    protected function truncateMessage(?string $message, int $maxLength = 500): string
    {
        if (empty($message)) {
            return '';
        }

        if (strlen($message) <= $maxLength) {
            return $message;
        }

        return substr($message, 0, $maxLength - 3) . '...';
    }

    /**
     * Extrahiert den Stack Trace
     */
    protected function getStackTrace(Throwable $e, int $limit): array
    {
        $trace = $e->getTrace();

        return array_slice(
            array_map(function ($frame) {
                return [
                    'file' => $frame['file'] ?? null,
                    'line' => $frame['line'] ?? null,
                    'function' => $frame['function'] ?? null,
                    'class' => $frame['class'] ?? null,
                ];
            }, $trace),
            0,
            $limit
        );
    }

    /**
     * Findet alle offenen Occurrences für ein Board
     */
    public function getOpenOccurrences(int $boardId): \Illuminate\Database\Eloquent\Collection
    {
        return HelpdeskErrorOccurrence::where('helpdesk_board_id', $boardId)
            ->where('status', HelpdeskErrorOccurrence::STATUS_OPEN)
            ->orderBy('last_seen_at', 'desc')
            ->get();
    }

    /**
     * Findet alle Occurrences für ein Board
     */
    public function getOccurrences(
        int $boardId,
        ?string $status = null,
        int $limit = 50
    ): \Illuminate\Database\Eloquent\Collection {
        $query = HelpdeskErrorOccurrence::where('helpdesk_board_id', $boardId);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->orderBy('last_seen_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
