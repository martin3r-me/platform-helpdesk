<?php

namespace Platform\Helpdesk\Organization;

use Platform\Organization\Contracts\PersonActivityProvider;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Helpdesk\Enums\TicketEscalationLevel;

class HelpdeskPersonActivityProvider implements PersonActivityProvider
{
    public function sectionKey(): string
    {
        return 'helpdesk';
    }

    public function sectionConfig(): array
    {
        return [
            'label' => 'Helpdesk',
            'icon' => 'ticket',
            'description' => 'Tickets und Support',
        ];
    }

    public function metricConfig(): array
    {
        return [
            'open_tickets' => ['label' => 'Offene Tickets', 'type' => 'warning', 'sort_weight' => 1],
            'overdue_tickets' => ['label' => 'Überfällig', 'type' => 'danger', 'sort_weight' => 3],
            'escalated_tickets' => ['label' => 'Eskaliert', 'type' => 'danger', 'sort_weight' => 2],
        ];
    }

    public function vitalSigns(int $userId, int $teamId): array
    {
        $openTickets = HelpdeskTicket::where('user_in_charge_id', $userId)
            ->where('team_id', $teamId)
            ->where('is_done', false)
            ->count();

        $overdueTickets = HelpdeskTicket::where('user_in_charge_id', $userId)
            ->where('team_id', $teamId)
            ->where('is_done', false)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->count();

        $escalatedTickets = HelpdeskTicket::where('user_in_charge_id', $userId)
            ->where('team_id', $teamId)
            ->where('is_done', false)
            ->where('escalation_level', '!=', TicketEscalationLevel::NONE)
            ->count();

        $signs = [
            [
                'key' => 'open_tickets',
                'label' => 'Offene Tickets',
                'value' => $openTickets,
                'variant' => $openTickets > 0 ? 'default' : 'success',
            ],
        ];

        if ($overdueTickets > 0) {
            $signs[] = [
                'key' => 'overdue_tickets',
                'label' => 'Überfällig',
                'value' => $overdueTickets,
                'variant' => 'danger',
            ];
        }

        if ($escalatedTickets > 0) {
            $signs[] = [
                'key' => 'escalated_tickets',
                'label' => 'Eskaliert',
                'value' => $escalatedTickets,
                'variant' => 'danger',
            ];
        }

        return $signs;
    }

    public function responsibilities(int $userId, int $teamId, int $limit = 5): array
    {
        $groups = [];

        $ticketQuery = HelpdeskTicket::where('user_in_charge_id', $userId)
            ->where('team_id', $teamId)
            ->where('is_done', false)
            ->orderByRaw('CASE WHEN due_date IS NOT NULL AND due_date < NOW() THEN 0 ELSE 1 END')
            ->orderBy('due_date');

        $totalTickets = $ticketQuery->count();
        $tickets = $ticketQuery->limit($limit)->get();

        if ($totalTickets > 0) {
            $groups[] = [
                'key' => 'assigned_tickets',
                'label' => 'Zugewiesene Tickets',
                'icon' => 'ticket',
                'total_count' => $totalTickets,
                'items' => $tickets->map(fn($t) => [
                    'id' => $t->id,
                    'name' => $t->title ?? '—',
                    'url' => null,
                    'meta' => $t->due_date
                        ? ($t->due_date->isPast() ? 'Überfällig: ' : 'Fällig: ') . $t->due_date->format('d.m.Y')
                        : null,
                ])->toArray(),
            ];
        }

        return $groups;
    }
}
