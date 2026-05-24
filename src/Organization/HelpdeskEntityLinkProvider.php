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

        $tickets = HelpdeskTicket::whereIn('id', $allIds)
            ->select('id', 'is_done', 'story_points')
            ->get()
            ->keyBy('id');

        $result = [];
        foreach ($linksByEntity as $entityId => $ids) {
            $total = 0;
            $done = 0;
            $spTotal = 0;
            $spDone = 0;
            foreach ($ids as $id) {
                $ticket = $tickets[$id] ?? null;
                if (!$ticket) {
                    continue;
                }
                $total++;
                $sp = $ticket->story_points?->points() ?? 0;
                $spTotal += $sp;
                if ($ticket->is_done) {
                    $done++;
                    $spDone += $sp;
                }
            }
            $result[$entityId] = [
                'items_total' => $total,
                'items_done' => $done,
                'story_points_total' => $spTotal,
                'story_points_done' => $spDone,
            ];
        }

        return $result;
    }

    public function metricDefinitions(): array
    {
        return [
            'items_total'        => ['label' => 'Items (gesamt)', 'group' => 'work', 'direction' => 'neutral', 'unit' => 'count', 'dimension' => 'complexity', 'type' => 'stock', 'aggregation_mode' => 'rolled_up'],
            'items_done'         => ['label' => 'Items (erledigt)', 'group' => 'work', 'direction' => 'up', 'unit' => 'count', 'pair' => 'items_total', 'dimension' => 'throughput', 'type' => 'flow', 'aggregation_mode' => 'rolled_up'],
            'story_points_total' => ['label' => 'Story Points (gesamt)', 'group' => 'work', 'direction' => 'neutral', 'unit' => 'points', 'dimension' => 'complexity', 'type' => 'stock', 'aggregation_mode' => 'rolled_up'],
            'story_points_done'  => ['label' => 'Story Points (erledigt)', 'group' => 'work', 'direction' => 'up', 'unit' => 'points', 'pair' => 'story_points_total', 'dimension' => 'throughput', 'type' => 'flow', 'aggregation_mode' => 'rolled_up'],
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
