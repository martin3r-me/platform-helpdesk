<?php

namespace Platform\Helpdesk\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

class HelpdeskOverviewTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'helpdesk.overview.GET';
    }

    public function getDescription(): string
    {
        return 'GET /helpdesk/overview - Zeigt Übersicht über Helpdesk-Konzepte (Boards/Slots/Groups/Tickets) und die wichtigsten Beziehungen. REST-Parameter: keine.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            return ToolResult::success([
                'module' => 'helpdesk',
                'scope' => [
                    'team_scoped' => true,
                    'access_rule' => 'Wer Team-Mitglied ist, darf im Helpdesk arbeiten. Zusätzlich greifen Policies (Board/Ticket).',
                ],
                'concepts' => [
                    'boards' => [
                        'model' => 'Platform\\Helpdesk\\Models\\HelpdeskBoard',
                        'table' => 'helpdesk_boards',
                        'keys' => ['id', 'uuid', 'name', 'team_id', 'user_id', 'order'],
                    ],
                    'board_slots' => [
                        'model' => 'Platform\\Helpdesk\\Models\\HelpdeskBoardSlot',
                        'table' => 'helpdesk_board_slots',
                        'keys' => ['id', 'uuid', 'helpdesk_board_id', 'name', 'order'],
                    ],
                    'ticket_groups' => [
                        'model' => 'Platform\\Helpdesk\\Models\\HelpdeskTicketGroup',
                        'table' => 'helpdesk_ticket_groups',
                        'keys' => ['id', 'uuid', 'team_id', 'user_id', 'name', 'order'],
                    ],
                    'tickets' => [
                        'model' => 'Platform\\Helpdesk\\Models\\HelpdeskTicket',
                        'table' => 'helpdesk_tickets',
                        'keys' => ['id', 'uuid', 'team_id', 'user_id', 'user_in_charge_id', 'title', 'status', 'priority', 'is_done'],
                    ],
                ],
                'relationships' => [
                    'board -> slots -> tickets',
                    'ticket_group -> tickets',
                    'ticket belongsTo team + optional owner/assignee',
                ],
                'related_tools' => [
                    'boards' => [
                        'list' => 'helpdesk.boards.GET',
                        'get' => 'helpdesk.board.GET',
                        'create' => 'helpdesk.boards.POST',
                        'update' => 'helpdesk.boards.PUT',
                        'delete' => 'helpdesk.boards.DELETE',
                    ],
                    'board_slots' => [
                        'list' => 'helpdesk.board_slots.GET',
                        'get' => 'helpdesk.board_slot.GET',
                        'create' => 'helpdesk.board_slots.POST',
                        'update' => 'helpdesk.board_slots.PUT',
                        'delete' => 'helpdesk.board_slots.DELETE',
                    ],
                    'ticket_groups' => [
                        'list' => 'helpdesk.ticket_groups.GET',
                        'get' => 'helpdesk.ticket_group.GET',
                        'create' => 'helpdesk.ticket_groups.POST',
                        'update' => 'helpdesk.ticket_groups.PUT',
                        'delete' => 'helpdesk.ticket_groups.DELETE',
                    ],
                    'tickets' => [
                        'list' => 'helpdesk.tickets.GET',
                        'get' => 'helpdesk.ticket.GET',
                        'create' => 'helpdesk.tickets.POST',
                        'update' => 'helpdesk.tickets.PUT',
                        'delete' => 'helpdesk.tickets.DELETE',
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Helpdesk-Übersicht: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'overview',
            'tags' => ['overview', 'help', 'helpdesk', 'boards', 'tickets'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}


