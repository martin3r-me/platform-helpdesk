<?php

namespace Platform\Helpdesk\Tools;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Tools\Concerns\ResolvesHelpdeskTeam;

class ListBoardSlotsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesHelpdeskTeam;

    public function getName(): string
    {
        return 'helpdesk.board_slots.GET';
    }

    public function getDescription(): string
    {
        return 'GET /helpdesk/board_slots - Listet alle Slots eines Boards. Parameter: board_id (required), team_id (optional).';
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

            $board = HelpdeskBoard::query()
                ->where('team_id', $teamId)
                ->find($boardId);

            if (!$board) {
                return ToolResult::error('NOT_FOUND', 'Board nicht gefunden (oder kein Zugriff).');
            }

            Gate::forUser($context->user)->authorize('view', $board);

            $slots = $board->slots()
                ->orderBy('order', 'asc')
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'uuid' => $s->uuid,
                    'name' => $s->name,
                    'description' => $s->description,
                    'order' => (int)$s->order,
                    'helpdesk_board_id' => $s->helpdesk_board_id,
                    'tickets_count' => $s->tickets()->count(),
                    'created_at' => $s->created_at?->toISOString(),
                    'updated_at' => $s->updated_at?->toISOString(),
                ])->toArray();

            return ToolResult::success([
                'data' => $slots,
                'board_id' => $board->id,
                'board_name' => $board->name,
                'team_id' => $teamId,
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf dieses Board.');
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Slots: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['helpdesk', 'board_slots', 'list'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
