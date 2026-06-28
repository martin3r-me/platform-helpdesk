<?php

namespace Platform\Helpdesk\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Helpdesk\Models\HelpdeskBoardSnapshot;

/**
 * Aggregat-Sicht: juengste Snapshots aller Helpdesk-Boards im Team.
 * Liefert Verteilungen (Ampel/Achsen/Confidence/SLA-Coverage) plus
 * Top-N worst-health und Top-N low-confidence.
 */
class ListBoardSnapshotsSummaryTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'helpdesk.board_snapshots.summary';
    }

    public function getDescription(): string
    {
        return 'GET /board-snapshots/summary - Aggregat-Sicht ueber die juengsten Board-Snapshots eines Teams. Health-Verteilung, Worst-Axis-Verteilung, SLA-Coverage, Top-N rote Boards, Top-N daten-arme Boards.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default aktuelles Team.'],
                'top_n' => ['type' => 'integer', 'description' => 'Optional: Top-N Eintraege. Default 5, max 20.'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User.');
            }

            $teamId = (int) ($arguments['team_id'] ?? ($context->team?->id ?? 0));
            if ($teamId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'Kein Team im Kontext.');
            }

            $topN = max(1, min(20, (int) ($arguments['top_n'] ?? 5)));

            $latestIds = DB::table('helpdesk_board_snapshots as a')
                ->where('a.team_id', $teamId)
                ->whereRaw('a.taken_on = (
                    SELECT MAX(b.taken_on) FROM helpdesk_board_snapshots b
                    WHERE b.helpdesk_board_id = a.helpdesk_board_id
                )')
                ->pluck('a.id');

            $latest = HelpdeskBoardSnapshot::with('board:id,name')
                ->whereIn('id', $latestIds)
                ->get();

            $total = $latest->count();
            if ($total === 0) {
                return ToolResult::success([
                    'team_id' => $teamId,
                    'total_boards' => 0,
                    'message' => 'Noch keine Board-Snapshots fuer dieses Team vorhanden.',
                ]);
            }

            // Health-Distribution
            $byColor = ['green' => 0, 'yellow' => 0, 'red' => 0, 'gray' => 0];
            foreach ($latest as $s) {
                $key = $s->health_color ?: 'gray';
                $byColor[$key] = ($byColor[$key] ?? 0) + 1;
            }

            // Worst-Axis-Distribution
            $byAxis = ['backlog' => 0, 'sla' => 0, 'escalation' => 0, 'workload' => 0];
            foreach ($latest as $s) {
                if ($s->worst_axis && isset($byAxis[$s->worst_axis])) {
                    $byAxis[$s->worst_axis]++;
                }
            }

            // Confidence-Distribution
            $byConfidence = ['high_75_100' => 0, 'medium_50_74' => 0, 'low_25_49' => 0, 'none_0_24' => 0];
            foreach ($latest as $s) {
                $c = (int) $s->confidence_score;
                if ($c >= 75) $byConfidence['high_75_100']++;
                elseif ($c >= 50) $byConfidence['medium_50_74']++;
                elseif ($c >= 25) $byConfidence['low_25_49']++;
                else $byConfidence['none_0_24']++;
            }

            // SLA Coverage
            $slaBoards = $latest->where('has_sla', true)->count();

            // Worst-Health Top-N
            $colorRank = ['red' => 0, 'yellow' => 1, 'green' => 2, 'gray' => 3];
            $worstHealth = $latest
                ->sort(function ($a, $b) use ($colorRank) {
                    $ra = $colorRank[$a->health_color ?? 'gray'] ?? 9;
                    $rb = $colorRank[$b->health_color ?? 'gray'] ?? 9;
                    if ($ra !== $rb) return $ra <=> $rb;
                    return (int) ($a->health_score ?? 999) <=> (int) ($b->health_score ?? 999);
                })
                ->take($topN)
                ->map(fn ($s) => $this->compact($s))
                ->values()->all();

            // Low-Confidence Top-N
            $lowConfidence = $latest
                ->sortBy('confidence_score')
                ->take($topN)
                ->map(fn ($s) => $this->compact($s))
                ->values()->all();

            return ToolResult::success([
                'team_id' => $teamId,
                'taken_on_range' => [
                    'from' => $latest->min('taken_on')?->toDateString(),
                    'to' => $latest->max('taken_on')?->toDateString(),
                ],
                'total_boards' => $total,
                'health_distribution' => $byColor,
                'worst_axis_distribution' => $byAxis,
                'confidence_distribution' => $byConfidence,
                'sla_coverage' => [
                    'boards_with_sla' => $slaBoards,
                    'boards_without_sla' => $total - $slaBoards,
                ],
                'worst_health' => $worstHealth,
                'low_confidence' => $lowConfidence,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    private function compact(HelpdeskBoardSnapshot $s): array
    {
        return [
            'board_id' => $s->helpdesk_board_id,
            'board_name' => $s->board?->name,
            'health_score' => $s->health_score,
            'health_color' => $s->health_color,
            'worst_axis' => $s->worst_axis,
            'confidence_score' => $s->confidence_score,
            'confidence_reason' => $s->confidence_reason,
            'tickets_open' => $s->tickets_open,
            'tickets_overdue' => $s->tickets_overdue,
            'tickets_escalated' => $s->tickets_escalated,
            'tickets_critical' => $s->tickets_critical,
            'has_sla' => (bool) $s->has_sla,
            'tickets_breaching_resolution' => $s->tickets_breaching_resolution,
            'taken_on' => $s->taken_on?->toDateString(),
        ];
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['helpdesk', 'board', 'snapshot', 'summary', 'aggregate'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
