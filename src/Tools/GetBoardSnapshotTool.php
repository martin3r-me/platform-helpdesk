<?php

namespace Platform\Helpdesk\Tools;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Models\HelpdeskBoardSnapshot;
use Platform\Helpdesk\Services\HelpdeskBoardSnapshotService;

/**
 * Liefert den juengsten Board-Snapshot (oder historischen via taken_on)
 * inkl. Sub-Daten (top_tickets, people, slots). Optional fresh=true
 * erzwingt einen neuen Snapshot.
 */
class GetBoardSnapshotTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'helpdesk.board_snapshots.GET';
    }

    public function getDescription(): string
    {
        return 'GET /board-snapshots - Holt den juengsten Snapshot eines Helpdesk-Boards (oder einen historischen via taken_on) inkl. Health-Ampel, Achsen (backlog/sla/escalation/workload), Story-Points, Top-Tickets, Workload-Top, Slot-Verteilung. Optional fresh=true erzwingt einen neuen Snapshot.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'board_id' => ['type' => 'integer', 'description' => 'Board-ID (ERFORDERLICH).'],
                'taken_on' => ['type' => 'string', 'description' => 'Optional: historischer Stichtag YYYY-MM-DD. Default: juengster Snapshot.'],
                'fresh' => ['type' => 'boolean', 'description' => 'Optional: wenn true wird ein neuer Snapshot erstellt (Trigger=manual).'],
            ],
            'required' => ['board_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
            }
            if (empty($arguments['board_id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'board_id ist erforderlich.');
            }

            $board = HelpdeskBoard::find($arguments['board_id']);
            if (!$board) {
                return ToolResult::error('BOARD_NOT_FOUND', 'Helpdesk-Board nicht gefunden.');
            }

            try {
                Gate::forUser($context->user)->authorize('view', $board);
            } catch (AuthorizationException $e) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Lesezugriff auf das Board.');
            }

            if (!empty($arguments['fresh'])) {
                $snapshot = app(HelpdeskBoardSnapshotService::class)->snapshot($board, 'manual');
            } else {
                $query = HelpdeskBoardSnapshot::where('helpdesk_board_id', $board->id);
                if (!empty($arguments['taken_on'])) {
                    $query->whereDate('taken_on', $arguments['taken_on']);
                }
                $snapshot = $query->orderByDesc('taken_on')->first();
            }

            if (!$snapshot) {
                return ToolResult::success([
                    'board_id' => $board->id,
                    'snapshot' => null,
                    'message' => 'Noch kein Snapshot. Setze fresh=true um den ersten zu erstellen.',
                ]);
            }

            $snapshot->load(['topTickets', 'people', 'slots']);

            return ToolResult::success([
                'board_id' => $board->id,
                'board_name' => $board->name,
                'snapshot' => self::serializeSnapshot($snapshot),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public static function serializeSnapshot(HelpdeskBoardSnapshot $s): array
    {
        return [
            'id' => $s->id,
            'uuid' => $s->uuid,
            'taken_at' => $s->taken_at?->toIso8601String(),
            'taken_on' => $s->taken_on?->toDateString(),
            'trigger' => $s->trigger,
            'frozen_context' => $s->frozen_context,
            'tickets' => [
                'total' => $s->tickets_total,
                'open' => $s->tickets_open,
                'done' => $s->tickets_done,
                'overdue' => $s->tickets_overdue,
                'with_due_date' => $s->tickets_with_due_date,
            ],
            'escalation' => [
                'escalated' => $s->tickets_escalated,
                'critical' => $s->tickets_critical,
                'lifetime_count' => $s->escalations_total_lifetime,
            ],
            'story_points' => [
                'total' => $s->story_points_total,
                'open' => $s->story_points_open,
                'done' => $s->story_points_done,
            ],
            'sla' => [
                'has_sla' => (bool) $s->has_sla,
                'response_hours' => $s->sla_response_hours,
                'resolution_hours' => $s->sla_resolution_hours,
                'tickets_breaching_resolution' => $s->tickets_breaching_resolution,
            ],
            'workload' => [
                'active_users' => $s->active_users_count,
                'unassigned' => $s->unassigned_tickets,
            ],
            'health' => [
                'score' => $s->health_score,
                'color' => $s->health_color,
                'worst_axis' => $s->worst_axis,
                'axis_scores' => $s->axis_scores,
            ],
            'confidence' => [
                'score' => $s->confidence_score,
                'reason' => $s->confidence_reason,
            ],
            'movement' => [
                'prev_snapshot_id' => $s->prev_snapshot_id,
                'delta_health_score' => $s->delta_health_score,
                'last_movement_at' => $s->last_movement_at?->toIso8601String(),
            ],
            'top_tickets' => $s->topTickets->map(fn ($x) => [
                'ticket_id' => $x->ticket_id,
                'ticket_uuid' => $x->ticket_uuid,
                'title' => $x->ticket_title,
                'due_date' => $x->due_date?->toIso8601String(),
                'is_overdue' => $x->is_overdue,
                'priority' => $x->priority,
                'escalation_level' => $x->escalation_level,
                'escalation_count' => $x->escalation_count,
                'story_points' => $x->story_points,
                'user_in_charge' => $x->user_in_charge_name,
                'created_at' => $x->ticket_created_at?->toIso8601String(),
                'rank' => $x->rank,
            ])->all(),
            'people' => $s->people->map(fn ($x) => [
                'user_id' => $x->user_id,
                'name' => $x->user_name,
                'open_tickets' => $x->open_tickets,
                'done_tickets' => $x->done_tickets,
                'overdue_tickets' => $x->overdue_tickets,
                'escalated_tickets' => $x->escalated_tickets,
                'sp_open' => $x->sp_open,
                'sp_done' => $x->sp_done,
            ])->all(),
            'slots' => $s->slots->map(fn ($x) => [
                'slot_id' => $x->slot_id,
                'name' => $x->slot_name,
                'order' => $x->slot_order,
                'open' => $x->open_tickets,
                'done' => $x->done_tickets,
                'total' => $x->total_tickets,
            ])->all(),
        ];
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['helpdesk', 'board', 'snapshot', 'health'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
