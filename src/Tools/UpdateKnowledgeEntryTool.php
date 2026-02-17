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

class UpdateKnowledgeEntryTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHelpdeskTeam;

    public function getName(): string
    {
        return 'helpdesk.knowledge.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /helpdesk/knowledge/{id} - Aktualisiert einen Knowledge Entry. Parameter: entry_id (required), title/problem/solution/tags/board_id/source_ticket_id (optional).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'entry_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Knowledge Entry (ERFORDERLICH). Nutze "helpdesk.knowledge.GET".',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Titel.',
                ],
                'problem' => [
                    'type' => 'string',
                    'description' => 'Optional: Neue Problem-Beschreibung.',
                ],
                'solution' => [
                    'type' => 'string',
                    'description' => 'Optional: Neue Lösungs-Beschreibung.',
                ],
                'tags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Optional: Neue Tags (ersetzt bestehende).',
                ],
                'board_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Entry auf ein anderes Board verschieben.',
                ],
                'source_ticket_id' => [
                    'type' => ['integer', 'null'],
                    'description' => 'Optional: Quell-Ticket-Referenz ändern (null zum Entfernen).',
                ],
            ],
            'required' => ['entry_id'],
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

            $entryId = (int)($arguments['entry_id'] ?? 0);
            if ($entryId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'entry_id ist erforderlich.');
            }

            $entry = HelpdeskKnowledgeEntry::where('team_id', $teamId)->find($entryId);
            if (!$entry) {
                return ToolResult::error('NOT_FOUND', 'Knowledge Entry nicht gefunden (oder kein Zugriff).');
            }

            Gate::forUser($context->user)->authorize('update', $entry);

            $update = [];

            // Textfelder
            foreach (['title', 'problem', 'solution'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $value = trim((string)$arguments[$field]);
                    if ($value === '') {
                        return ToolResult::error('VALIDATION_ERROR', "$field darf nicht leer sein.");
                    }
                    $update[$field] = $value;
                }
            }

            // Tags
            if (array_key_exists('tags', $arguments)) {
                if ($arguments['tags'] === null) {
                    $update['tags'] = null;
                } elseif (is_array($arguments['tags'])) {
                    $update['tags'] = array_values(array_filter(array_map('trim', $arguments['tags']), fn ($t) => $t !== ''));
                }
            }

            // Board verschieben
            if (array_key_exists('board_id', $arguments)) {
                $newBoardId = (int)$arguments['board_id'];
                $newBoard = HelpdeskBoard::where('team_id', $teamId)->find($newBoardId);
                if (!$newBoard) {
                    return ToolResult::error('NOT_FOUND', 'Ziel-Board nicht gefunden (oder kein Zugriff).');
                }
                $update['helpdesk_board_id'] = $newBoardId;
            }

            // Source Ticket
            if (array_key_exists('source_ticket_id', $arguments)) {
                if ($arguments['source_ticket_id'] === null) {
                    $update['source_ticket_id'] = null;
                } else {
                    $sourceTicketId = (int)$arguments['source_ticket_id'];
                    $sourceTicket = HelpdeskTicket::where('team_id', $teamId)->find($sourceTicketId);
                    if (!$sourceTicket) {
                        return ToolResult::error('NOT_FOUND', 'Quell-Ticket nicht gefunden (oder kein Zugriff).');
                    }
                    $update['source_ticket_id'] = $sourceTicketId;
                }
            }

            if (!empty($update)) {
                $entry->update($update);
            }

            return ToolResult::success([
                'id' => $entry->id,
                'uuid' => $entry->uuid,
                'title' => $entry->title,
                'problem' => $entry->problem,
                'solution' => $entry->solution,
                'tags' => $entry->tags,
                'board_id' => $entry->helpdesk_board_id,
                'source_ticket_id' => $entry->source_ticket_id,
                'team_id' => $entry->team_id,
                'message' => 'Knowledge Entry aktualisiert.',
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ToolResult::error('ACCESS_DENIED', 'Du darfst diesen Knowledge Entry nicht bearbeiten.');
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Knowledge Entry: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['helpdesk', 'knowledge', 'update'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
