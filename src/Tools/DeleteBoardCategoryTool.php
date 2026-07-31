<?php

namespace Platform\Helpdesk\Tools;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Helpdesk\Models\HelpdeskBoardCategory;
use Platform\Helpdesk\Tools\Concerns\ResolvesHelpdeskTeam;

class DeleteBoardCategoryTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHelpdeskTeam;

    public function getName(): string
    {
        return 'helpdesk.board_categories.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /helpdesk/board_categories/{id} - Löscht eine Kategorie (confirm=true). '
            .'Zugeordnete Tickets behalten kein Kategorie-Feld (nullOnDelete).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team.'],
                'category_id' => ['type' => 'integer', 'description' => 'ID der Kategorie (ERFORDERLICH).'],
                'confirm' => ['type' => 'boolean', 'description' => 'ERFORDERLICH: true zum Bestätigen.'],
            ],
            'required' => ['category_id', 'confirm'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (empty($arguments['confirm'])) {
                return ToolResult::error('CONFIRM_REQUIRED', 'Zum Löschen confirm=true setzen.');
            }
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int) $resolved['team_id'];

            $cat = HelpdeskBoardCategory::with('helpdeskBoard')->find((int) ($arguments['category_id'] ?? 0));
            if (! $cat) {
                return ToolResult::error('NOT_FOUND', 'Kategorie nicht gefunden.');
            }
            $board = $cat->helpdeskBoard;
            if (! $board || (int) $board->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf diese Kategorie.');
            }
            Gate::forUser($context->user)->authorize('update', $board);

            $cat->delete();

            return ToolResult::success(['message' => 'Kategorie gelöscht.']);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ToolResult::error('ACCESS_DENIED', 'Du darfst diese Kategorie nicht löschen.');
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen der Kategorie: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['helpdesk', 'board_categories', 'delete'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
