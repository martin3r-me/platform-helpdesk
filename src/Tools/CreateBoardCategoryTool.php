<?php

namespace Platform\Helpdesk\Tools;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Models\HelpdeskBoardCategory;
use Platform\Helpdesk\Tools\Concerns\ResolvesHelpdeskTeam;

class CreateBoardCategoryTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHelpdeskTeam;

    public function getName(): string
    {
        return 'helpdesk.board_categories.POST';
    }

    public function getDescription(): string
    {
        return 'POST /helpdesk/board_categories - Legt eine kuratierbare Kategorie in einem Board an. '
            .'Parameter: board_id (required), name (required), description (optional, was gehört rein/raus), '
            .'examples (optional, Array von Beispiel-Formulierungen), order (optional), is_active (optional).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team.'],
                'board_id' => ['type' => 'integer', 'description' => 'ID des Boards (ERFORDERLICH). Nutze "helpdesk.boards.GET".'],
                'name' => ['type' => 'string', 'description' => 'Name der Kategorie (ERFORDERLICH).'],
                'description' => ['type' => 'string', 'description' => 'Optional: Grenze der Kategorie (was gehört rein/raus).'],
                'examples' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Optional: Beispiel-Formulierungen (Few-Shot-Anker).'],
                'order' => ['type' => 'integer', 'description' => 'Optional: Sortierung. Default: ans Ende.'],
                'is_active' => ['type' => 'boolean', 'description' => 'Optional: aktiv (Default true).'],
            ],
            'required' => ['board_id', 'name'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int) $resolved['team_id'];

            $board = HelpdeskBoard::query()->where('team_id', $teamId)->find((int) ($arguments['board_id'] ?? 0));
            if (! $board) {
                return ToolResult::error('NOT_FOUND', 'Board nicht gefunden (oder kein Zugriff).');
            }
            Gate::forUser($context->user)->authorize('update', $board);

            $name = trim((string) ($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }

            $order = $arguments['order'] ?? (($board->categories()->max('order') ?? 0) + 1);
            $examples = is_array($arguments['examples'] ?? null)
                ? array_values(array_filter(array_map(fn ($e) => trim((string) $e), $arguments['examples'])))
                : null;

            $cat = HelpdeskBoardCategory::create([
                'helpdesk_board_id' => $board->id,
                'name' => $name,
                'description' => $arguments['description'] ?? null,
                'examples' => $examples ?: null,
                'order' => (int) $order,
                'is_active' => array_key_exists('is_active', $arguments) ? (bool) $arguments['is_active'] : true,
            ]);

            return ToolResult::success([
                'id' => $cat->id,
                'uuid' => $cat->uuid,
                'name' => $cat->name,
                'description' => $cat->description,
                'examples' => $cat->examples,
                'is_active' => $cat->is_active,
                'helpdesk_board_id' => $cat->helpdesk_board_id,
                'board_name' => $board->name,
                'message' => 'Kategorie erstellt.',
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ToolResult::error('ACCESS_DENIED', 'Du darfst in diesem Board keine Kategorien anlegen.');
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen der Kategorie: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['helpdesk', 'board_categories', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
