<?php

namespace Platform\Helpdesk\Tools;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Tools\Concerns\ResolvesHelpdeskTeam;

class GetBoardTool implements ToolContract, ToolMetadataContract
{
    use ResolvesHelpdeskTeam;

    public function getName(): string
    {
        return 'helpdesk.board.GET';
    }

    public function getDescription(): string
    {
        return 'GET /helpdesk/boards/{id} - Ruft ein Board ab. Parameter: board_id (required), team_id (optional), include_slots (optional), include_stats (optional).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'board_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Boards (ERFORDERLICH). Nutze "helpdesk.boards.GET".',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'include_slots' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'include_stats' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
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
            $teamId = (int)$resolved['team_id'];

            $boardId = (int)($arguments['board_id'] ?? 0);
            if ($boardId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'board_id ist erforderlich.');
            }

            $includeSlots = (bool)($arguments['include_slots'] ?? true);
            $includeStats = (bool)($arguments['include_stats'] ?? true);

            $with = [];
            if ($includeSlots) {
                $with[] = 'slots';
            }

            $board = HelpdeskBoard::query()
                ->with($with)
                ->where('team_id', $teamId)
                ->find($boardId);

            if (!$board) {
                return ToolResult::error('NOT_FOUND', 'Board nicht gefunden (oder kein Zugriff).');
            }

            Gate::forUser($context->user)->authorize('view', $board);

            $stats = null;
            if ($includeStats) {
                $stats = [
                    'tickets_total' => $board->tickets()->count(),
                    'tickets_open' => $board->tickets()->where('is_done', false)->count(),
                    'tickets_done' => $board->tickets()->where('is_done', true)->count(),
                ];
            }

            return ToolResult::success([
                'id' => $board->id,
                'uuid' => $board->uuid,
                'name' => $board->name,
                'description' => $board->description,
                'order' => (int)$board->order,
                'team_id' => $board->team_id,
                'user_id' => $board->user_id,
                'slots' => $includeSlots ? $board->slots->map(fn ($s) => [
                    'id' => $s->id,
                    'uuid' => $s->uuid,
                    'name' => $s->name,
                    'description' => $s->description,
                    'order' => (int)$s->order,
                ])->values()->toArray() : null,
                'stats' => $stats,
                'created_at' => $board->created_at?->toISOString(),
                'updated_at' => $board->updated_at?->toISOString(),
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf dieses Board.');
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des Boards: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['helpdesk', 'board', 'get'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}


