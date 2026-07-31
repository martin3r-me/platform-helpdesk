<?php

namespace Platform\Helpdesk\Tools;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Tools\Concerns\ResolvesHelpdeskTeam;

class ListBoardCategoriesTool implements ToolContract, ToolMetadataContract
{
    use ResolvesHelpdeskTeam;

    public function getName(): string
    {
        return 'helpdesk.board_categories.GET';
    }

    public function getDescription(): string
    {
        return 'GET /helpdesk/board_categories - Listet die Kategorien eines Boards. Parameter: board_id (required).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team.'],
                'board_id' => ['type' => 'integer', 'description' => 'ID des Boards (ERFORDERLICH).'],
            ],
            'required' => ['board_id'],
        ];
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
            Gate::forUser($context->user)->authorize('view', $board);

            $categories = $board->categories()->orderBy('order')->get()->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'description' => $c->description,
                'examples' => $c->examples,
                'order' => (int) $c->order,
                'is_active' => $c->is_active,
                'ticket_count' => $c->tickets()->count(),
            ])->all();

            return ToolResult::success([
                'board_id' => $board->id,
                'board_name' => $board->name,
                'categories' => $categories,
                'count' => count($categories),
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf dieses Board.');
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Kategorien: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'lookup',
            'tags' => ['helpdesk', 'board_categories', 'list'],
            'risk_level' => 'read',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
