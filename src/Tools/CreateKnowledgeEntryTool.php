<?php

namespace Platform\Helpdesk\Tools;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Models\HelpdeskKnowledgeEntry;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Helpdesk\Tools\Concerns\ResolvesHelpdeskTeam;

class CreateKnowledgeEntryTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHelpdeskTeam;

    public function getName(): string
    {
        return 'helpdesk.knowledge.POST';
    }

    public function getDescription(): string
    {
        return 'POST /helpdesk/knowledge - Erstellt einen Knowledge Entry (Wissensdatenbank-Eintrag). Parameter: board_id (required), title (required), problem (required), solution (required), tags (optional), source_ticket_id (optional).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'board_id' => [
                    'type' => 'integer',
                    'description' => 'Board-ID (ERFORDERLICH). Der Entry gehört zu diesem Board. team_id ergibt sich daraus.',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'Kurztitel des Problems/der Lösung (ERFORDERLICH).',
                ],
                'problem' => [
                    'type' => 'string',
                    'description' => 'Problem-Beschreibung (ERFORDERLICH).',
                ],
                'solution' => [
                    'type' => 'string',
                    'description' => 'Lösungs-Beschreibung / Schritte (ERFORDERLICH).',
                ],
                'tags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Optional: Tags für Kategorisierung und bessere Suche.',
                ],
                'source_ticket_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Referenz auf das Quell-Ticket (für Traceability).',
                ],
            ],
            'required' => ['board_id', 'title', 'problem', 'solution'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int)$resolved['team_id'];

            Gate::forUser($context->user)->authorize('create', HelpdeskKnowledgeEntry::class);

            // Board validieren
            $boardId = (int)($arguments['board_id'] ?? 0);
            if ($boardId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'board_id ist erforderlich.');
            }

            $board = HelpdeskBoard::where('team_id', $teamId)->find($boardId);
            if (!$board) {
                return ToolResult::error('NOT_FOUND', 'Board nicht gefunden (oder kein Zugriff).');
            }

            // Pflichtfelder validieren
            $title = trim((string)($arguments['title'] ?? ''));
            if ($title === '') {
                return ToolResult::error('VALIDATION_ERROR', 'title ist erforderlich.');
            }

            $problem = trim((string)($arguments['problem'] ?? ''));
            if ($problem === '') {
                return ToolResult::error('VALIDATION_ERROR', 'problem ist erforderlich.');
            }

            $solution = trim((string)($arguments['solution'] ?? ''));
            if ($solution === '') {
                return ToolResult::error('VALIDATION_ERROR', 'solution ist erforderlich.');
            }

            // Optional: source_ticket_id validieren
            $sourceTicketId = null;
            if (!empty($arguments['source_ticket_id'])) {
                $sourceTicketId = (int)$arguments['source_ticket_id'];
                $sourceTicket = HelpdeskTicket::where('team_id', $teamId)->find($sourceTicketId);
                if (!$sourceTicket) {
                    return ToolResult::error('NOT_FOUND', 'Quell-Ticket nicht gefunden (oder kein Zugriff).');
                }
            }

            // Tags verarbeiten
            $tags = null;
            if (isset($arguments['tags']) && is_array($arguments['tags'])) {
                $tags = array_values(array_filter(array_map('trim', $arguments['tags']), fn ($t) => $t !== ''));
            }

            $entry = HelpdeskKnowledgeEntry::create([
                'helpdesk_board_id' => $boardId,
                'team_id' => (int)$board->team_id,
                'title' => $title,
                'problem' => $problem,
                'solution' => $solution,
                'tags' => $tags,
                'source_ticket_id' => $sourceTicketId,
            ]);

            return ToolResult::success([
                'id' => $entry->id,
                'uuid' => $entry->uuid,
                'title' => $entry->title,
                'board_id' => $entry->helpdesk_board_id,
                'team_id' => $entry->team_id,
                'message' => 'Knowledge Entry erfolgreich erstellt.',
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ToolResult::error('ACCESS_DENIED', 'Du darfst keinen Knowledge Entry erstellen.');
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Knowledge Entry: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['helpdesk', 'knowledge', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
