<?php

namespace Platform\Helpdesk\Tools;

use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Models\HelpdeskBoardSnapshot;

class GetBoardSnapshotTrendTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'helpdesk.board_snapshots.trend';
    }

    public function getDescription(): string
    {
        return 'GET /board-snapshots/trend - Zeitreihe Health-Score + Achsen + Ticket-Counts eines Helpdesk-Boards. Default: letzte 30 Tage.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'board_id' => ['type' => 'integer', 'description' => 'Board-ID (ERFORDERLICH).'],
                'days' => ['type' => 'integer', 'description' => 'Optional: letzte N Tage (1..365). Default 30.'],
                'from' => ['type' => 'string', 'description' => 'Optional: Startdatum YYYY-MM-DD.'],
                'to' => ['type' => 'string', 'description' => 'Optional: Enddatum YYYY-MM-DD. Default heute.'],
            ],
            'required' => ['board_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User.');
            }
            if (empty($arguments['board_id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'board_id erforderlich.');
            }

            $board = HelpdeskBoard::find($arguments['board_id']);
            if (!$board) {
                return ToolResult::error('BOARD_NOT_FOUND', 'Board nicht gefunden.');
            }

            try {
                Gate::forUser($context->user)->authorize('view', $board);
            } catch (AuthorizationException $e) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Lesezugriff.');
            }

            $to = !empty($arguments['to']) ? Carbon::parse($arguments['to']) : now();
            if (!empty($arguments['from'])) {
                $from = Carbon::parse($arguments['from']);
            } else {
                $days = max(1, min(365, (int) ($arguments['days'] ?? 30)));
                $from = $to->copy()->subDays($days - 1);
            }

            $snapshots = HelpdeskBoardSnapshot::where('helpdesk_board_id', $board->id)
                ->whereBetween('taken_on', [$from->toDateString(), $to->toDateString()])
                ->orderBy('taken_on')
                ->get();

            $points = $snapshots->map(fn (HelpdeskBoardSnapshot $s) => [
                'taken_on' => $s->taken_on?->toDateString(),
                'health_score' => $s->health_score,
                'health_color' => $s->health_color,
                'worst_axis' => $s->worst_axis,
                'axis_scores' => $s->axis_scores,
                'confidence_score' => $s->confidence_score,
                'tickets_total' => $s->tickets_total,
                'tickets_open' => $s->tickets_open,
                'tickets_done' => $s->tickets_done,
                'tickets_overdue' => $s->tickets_overdue,
                'tickets_escalated' => $s->tickets_escalated,
                'tickets_breaching_resolution' => $s->tickets_breaching_resolution,
                'story_points_open' => $s->story_points_open,
                'story_points_done' => $s->story_points_done,
            ])->all();

            return ToolResult::success([
                'board_id' => $board->id,
                'board_name' => $board->name,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'count' => count($points),
                'points' => $points,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['helpdesk', 'board', 'snapshot', 'trend', 'timeseries'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
