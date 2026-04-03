<?php

namespace Platform\Helpdesk\Organization;

use Illuminate\Database\Eloquent\Builder;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Organization\Contracts\EntityLinkProvider;

class HelpdeskEntityLinkProvider implements EntityLinkProvider
{
    public function morphAliases(): array
    {
        return ['helpdesk_ticket', 'helpdesk_board'];
    }

    public function linkTypeConfig(): array
    {
        return [
            'helpdesk_ticket' => ['label' => 'Tickets', 'icon' => 'ticket', 'route' => null],
            'helpdesk_board' => ['label' => 'Helpdesk Boards', 'icon' => 'view-columns', 'route' => null],
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
}
