<?php

namespace Platform\Helpdesk\Tools;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Helpdesk\Models\HelpdeskKnowledgeEntry;
use Platform\Helpdesk\Tools\Concerns\ResolvesHelpdeskTeam;

class DeleteKnowledgeEntryTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHelpdeskTeam;

    public function getName(): string
    {
        return 'helpdesk.knowledge.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /helpdesk/knowledge/{id} - Löscht einen Knowledge Entry. Parameter: entry_id (required), confirm=true (required).';
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
                    'description' => 'ID des Knowledge Entry (ERFORDERLICH).',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'ERFORDERLICH: Setze confirm=true um wirklich zu löschen.',
                ],
            ],
            'required' => ['entry_id', 'confirm'],
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

            if (!($arguments['confirm'] ?? false)) {
                return ToolResult::error('CONFIRMATION_REQUIRED', 'Bitte bestätige mit confirm: true.');
            }

            $entryId = (int)($arguments['entry_id'] ?? 0);
            if ($entryId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'entry_id ist erforderlich.');
            }

            $entry = HelpdeskKnowledgeEntry::where('team_id', $teamId)->find($entryId);
            if (!$entry) {
                return ToolResult::error('NOT_FOUND', 'Knowledge Entry nicht gefunden (oder kein Zugriff).');
            }

            Gate::forUser($context->user)->authorize('delete', $entry);

            $id = (int)$entry->id;
            $title = (string)$entry->title;
            $entry->delete();

            return ToolResult::success([
                'entry_id' => $id,
                'title' => $title,
                'message' => 'Knowledge Entry gelöscht.',
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ToolResult::error('ACCESS_DENIED', 'Du darfst diesen Knowledge Entry nicht löschen.');
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Knowledge Entry: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['helpdesk', 'knowledge', 'delete'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
