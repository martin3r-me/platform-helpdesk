<?php

namespace Platform\Helpdesk\Organization;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Organization\Contracts\EntityLinkProvider;
use Platform\Organization\Contracts\HasMetricDefinitions;
use Platform\Organization\Contracts\HasPersonMetrics;

class HelpdeskEntityLinkProvider implements EntityLinkProvider, HasMetricDefinitions, HasPersonMetrics
{
    public function morphAliases(): array
    {
        return ['helpdesk_ticket', 'helpdesk_board'];
    }

    public function linkTypeConfig(): array
    {
        return [
            'helpdesk_ticket' => ['label' => 'Tickets', 'singular' => 'Ticket', 'icon' => 'ticket', 'route' => null],
            'helpdesk_board' => ['label' => 'Helpdesk Boards', 'singular' => 'Board', 'icon' => 'view-columns', 'route' => null],
        ];
    }

    public function applyEagerLoading(Builder $query, string $morphAlias, string $fqcn): void
    {
        match ($morphAlias) {
            'helpdesk_ticket' => $query->withCount('escalations'),
            'helpdesk_board' => $query->withCount('tickets'),
            default => null,
        };
    }

    public function extractMetadata(string $morphAlias, mixed $model): array
    {
        return match ($morphAlias) {
            'helpdesk_ticket' => [
                'is_done' => $model->is_done ?? false,
                'priority' => $model->priority?->value ?? null,
                'escalation_level' => $model->escalation_level?->value ?? null,
                'story_points' => $model->story_points?->value ?? null,
                'due_date' => $model->due_date?->format('d.m.Y') ?? null,
                'escalation_count' => (int) ($model->escalations_count ?? 0),
            ],
            'helpdesk_board' => [
                'ticket_count' => (int) ($model->tickets_count ?? 0),
            ],
            default => [],
        };
    }

    public function metadataDisplayRules(): array
    {
        return [
            'helpdesk_ticket' => [
                ['field' => 'priority', 'format' => 'text'],
                ['field' => 'escalation_level', 'format' => 'text', 'css_class' => 'text-red-600'],
                ['field' => 'story_points', 'format' => 'text', 'suffix' => 'SP'],
                ['field' => 'due_date', 'format' => 'text'],
                ['field' => 'escalation_count', 'format' => 'count', 'suffix' => 'Eskalation', 'suffix_plural' => 'Eskalationen'],
                ['field' => 'is_done', 'format' => 'boolean_done'],
            ],
            'helpdesk_board' => [
                ['field' => 'ticket_count', 'format' => 'count', 'suffix' => 'Tickets'],
            ],
        ];
    }

    public function timeTrackableCascades(): array
    {
        return [
            'helpdesk_ticket' => [HelpdeskTicket::class, []],
        ];
    }

    public function activityChildren(string $morphAlias, array $linkableIds): array
    {
        return [];
    }

    public function metrics(string $morphAlias, array $linksByEntity): array
    {
        if ($morphAlias !== 'helpdesk_ticket') {
            return [];
        }

        $allIds = [];
        foreach ($linksByEntity as $ids) {
            $allIds = array_merge($allIds, $ids);
        }
        $allIds = array_values(array_unique($allIds));

        if (empty($allIds)) {
            return [];
        }

        // Wide select: alle Felder, die eine der KPIs auswertet. Ein Query fuer alle.
        $tickets = HelpdeskTicket::whereIn('id', $allIds)
            ->select('id', 'is_done', 'story_points', 'created_at', 'done_at', 'due_date',
                'escalation_level', 'helpdesk_board_id')
            ->get()
            ->keyBy('id');

        // SLA-Frist pro Board vorladen — vermeidet N+1 im Ticket-Loop.
        $boardIds = $tickets->pluck('helpdesk_board_id')->filter()->unique()->all();
        $slaByBoard = [];
        if (! empty($boardIds)) {
            $slaByBoard = DB::table('helpdesk_boards as b')
                ->leftJoin('helpdesk_board_slas as s', 's.id', '=', 'b.helpdesk_board_sla_id')
                ->whereIn('b.id', $boardIds)
                ->select('b.id as board_id', 's.resolution_time_hours', 's.response_time_hours')
                ->get()
                ->keyBy('board_id')
                ->all();
        }

        $now = now();
        $window7d = $now->copy()->subDays(7);

        $result = [];
        foreach ($linksByEntity as $entityId => $ids) {
            $total = 0;
            $done = 0;
            $spTotal = 0;
            $spDone = 0;
            $created7d = 0;
            $resolved7d = 0;
            $slaBreach = 0;
            $escalationsActive = 0;
            $overdueDueDate = 0;
            $ageSumDays = 0.0;
            $openCount = 0;

            foreach ($ids as $id) {
                $ticket = $tickets[$id] ?? null;
                if (! $ticket) {
                    continue;
                }
                $total++;
                $sp = $ticket->story_points?->points() ?? 0;
                $spTotal += $sp;

                if ($ticket->is_done) {
                    $done++;
                    $spDone += $sp;
                    if ($ticket->done_at && $ticket->done_at->greaterThanOrEqualTo($window7d)) {
                        $resolved7d++;
                    }
                } else {
                    $openCount++;
                    $ageSumDays += (float) $ticket->created_at?->diffInDays($now, false) ?? 0.0;

                    // SLA-Bruch: offen + Alter ueber Board-SLA-Frist
                    $sla = $slaByBoard[$ticket->helpdesk_board_id] ?? null;
                    if ($sla && $sla->resolution_time_hours && $ticket->created_at) {
                        $ageHours = $ticket->created_at->diffInHours($now, false);
                        if ($ageHours > (int) $sla->resolution_time_hours) {
                            $slaBreach++;
                        }
                    }

                    // Aktive Eskalationen: escalation_level gesetzt und != NONE
                    $lvl = $ticket->escalation_level?->value;
                    if ($lvl && $lvl !== 'none') {
                        $escalationsActive++;
                    }

                    // Ueberfaellige Faelligkeit: due_date in der Vergangenheit
                    if ($ticket->due_date && $ticket->due_date->lt($now->copy()->startOfDay())) {
                        $overdueDueDate++;
                    }
                }

                if ($ticket->created_at && $ticket->created_at->greaterThanOrEqualTo($window7d)) {
                    $created7d++;
                }
            }

            $avgAgeOpen = $openCount > 0 ? round($ageSumDays / $openCount, 1) : 0.0;

            $result[$entityId] = [
                'items_total' => $total,
                'items_done' => $done,
                'story_points_total' => $spTotal,
                'story_points_done' => $spDone,
                'helpdesk_tickets_created_7d' => $created7d,
                'helpdesk_tickets_resolved_7d' => $resolved7d,
                'helpdesk_sla_breach_open' => $slaBreach,
                'helpdesk_escalations_active' => $escalationsActive,
                'helpdesk_avg_age_open_days' => $avgAgeOpen,
                'helpdesk_overdue_due_date' => $overdueDueDate,
            ];
        }

        return $result;
    }

    public function metricDefinitions(): array
    {
        return [
            // Bestehende Basis-KPIs — basis explizit gesetzt (fehlte vorher; Delta-Rechner
            // interpretierte flow-Metriken sonst als cumulative_since_start).
            'items_total'        => ['label' => 'Items (gesamt)', 'group' => 'work', 'direction' => 'neutral', 'unit' => 'count', 'dimension' => 'complexity', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
            'items_done'         => ['label' => 'Items (erledigt)', 'group' => 'work', 'direction' => 'up', 'unit' => 'count', 'pair' => 'items_total', 'dimension' => 'throughput', 'type' => 'flow', 'aggregation_mode' => 'rolled_up', 'basis' => 'cumulative_since_start'],
            'story_points_total' => ['label' => 'Story Points (gesamt)', 'group' => 'work', 'direction' => 'neutral', 'unit' => 'points', 'dimension' => 'complexity', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
            'story_points_done'  => ['label' => 'Story Points (erledigt)', 'group' => 'work', 'direction' => 'up', 'unit' => 'points', 'pair' => 'story_points_total', 'dimension' => 'throughput', 'type' => 'flow', 'aggregation_mode' => 'rolled_up', 'basis' => 'cumulative_since_start'],

            // Kunden-Puls (neu): Ticket-Fluss und Backlog-Qualitaet.
            'helpdesk_tickets_created_7d'  => ['label' => 'Neue Tickets (7 Tage)', 'group' => 'work', 'direction' => 'neutral', 'unit' => 'count', 'dimension' => 'throughput', 'type' => 'flow', 'aggregation_mode' => 'rolled_up', 'basis' => 'window_7d'],
            'helpdesk_tickets_resolved_7d' => ['label' => 'Erledigte Tickets (7 Tage)', 'group' => 'work', 'direction' => 'up', 'unit' => 'count', 'dimension' => 'throughput', 'type' => 'flow', 'aggregation_mode' => 'rolled_up', 'basis' => 'window_7d'],
            'helpdesk_sla_breach_open'     => ['label' => 'Offene Tickets ueber SLA-Frist', 'group' => 'work', 'direction' => 'down', 'unit' => 'count', 'dimension' => 'quality', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
            'helpdesk_escalations_active'  => ['label' => 'Aktive Eskalationen', 'group' => 'work', 'direction' => 'down', 'unit' => 'count', 'dimension' => 'quality', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
            'helpdesk_avg_age_open_days'   => ['label' => 'Ø-Alter offener Tickets (Tage)', 'group' => 'work', 'direction' => 'down', 'unit' => 'days', 'dimension' => 'energy', 'type' => 'modulator', 'aggregation_mode' => 'rolled_up', 'basis' => 'modulator_factor'],
            'helpdesk_overdue_due_date'    => ['label' => 'Ueberfaellige Faelligkeit', 'group' => 'work', 'direction' => 'down', 'unit' => 'count', 'dimension' => 'quality', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
        ];
    }

    public function personMetrics(array $userIds, int $teamId): array
    {
        if (empty($userIds)) {
            return [];
        }

        $rows = HelpdeskTicket::whereIn('user_in_charge_id', $userIds)
            ->where('team_id', $teamId)
            ->select(
                'user_in_charge_id',
                DB::raw('SUM(CASE WHEN is_done = 0 THEN 1 ELSE 0 END) as active_items'),
                DB::raw('SUM(CASE WHEN is_done = 1 THEN 1 ELSE 0 END) as completed_items'),
                DB::raw('SUM(CASE WHEN is_done = 0 THEN COALESCE(story_points, 0) ELSE 0 END) as story_points_total'),
                DB::raw('SUM(CASE WHEN is_done = 1 THEN COALESCE(story_points, 0) ELSE 0 END) as story_points_done'),
            )
            ->groupBy('user_in_charge_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->user_in_charge_id] = [
                'active_items' => (int) $row->active_items,
                'completed_items' => (int) $row->completed_items,
                'story_points_total' => (int) $row->story_points_total,
                'story_points_done' => (int) $row->story_points_done,
            ];
        }

        return $result;
    }

    public function personMetricDefinitions(): array
    {
        return [
            'active_items'       => ['label' => 'Aktive Items', 'group' => 'persons', 'direction' => 'neutral', 'unit' => 'count'],
            'completed_items'    => ['label' => 'Erledigte Items', 'group' => 'persons', 'direction' => 'up', 'unit' => 'count'],
            'story_points_total' => ['label' => 'Story Points gesamt', 'group' => 'persons', 'direction' => 'neutral', 'unit' => 'points'],
            'story_points_done'  => ['label' => 'Story Points erledigt', 'group' => 'persons', 'direction' => 'up', 'unit' => 'points'],
        ];
    }
}
