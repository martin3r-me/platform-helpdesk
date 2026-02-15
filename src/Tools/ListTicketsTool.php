<?php

namespace Platform\Helpdesk\Tools;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Helpdesk\Tools\Concerns\ResolvesHelpdeskTeam;

class ListTicketsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesHelpdeskTeam;

    public function getName(): string
    {
        return 'helpdesk.tickets.GET';
    }

    public function getDescription(): string
    {
        return 'GET /helpdesk/tickets - Listet Tickets. Parameter: team_id (optional), board_id (optional), slot_id (optional), group_id (optional), is_done (optional), priority (optional), user_in_charge_id (optional), filters/search/sort/limit/offset (optional).';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'team_id' => ['type' => 'integer'],
                    'board_id' => ['type' => 'integer'],
                    'slot_id' => ['type' => 'integer'],
                    'group_id' => ['type' => 'integer'],
                    'is_done' => ['type' => 'boolean'],
                    'priority' => ['type' => 'string'],
                    'user_in_charge_id' => ['type' => 'integer'],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int)$resolved['team_id'];

            Gate::forUser($context->user)->authorize('viewAny', HelpdeskTicket::class);

            $query = HelpdeskTicket::query()
                ->with(['helpdeskBoard', 'helpdeskBoardSlot', 'helpdeskTicketGroup', 'userInCharge'])
                ->where('team_id', $teamId);

            if (isset($arguments['board_id'])) {
                $query->where('helpdesk_board_id', (int)$arguments['board_id']);
            }
            if (isset($arguments['slot_id'])) {
                $query->where('helpdesk_board_slot_id', (int)$arguments['slot_id']);
            }
            if (isset($arguments['group_id'])) {
                $query->where('helpdesk_ticket_group_id', (int)$arguments['group_id']);
            }
            if (isset($arguments['is_done'])) {
                $query->where('is_done', (bool)$arguments['is_done']);
            }
            if (isset($arguments['priority'])) {
                $query->where('priority', (string)$arguments['priority']);
            }
            if (isset($arguments['user_in_charge_id'])) {
                $query->where('user_in_charge_id', (int)$arguments['user_in_charge_id']);
            }

            $this->applyStandardFilters($query, $arguments, [
                'title', 'priority', 'is_done', 'due_date', 'created_at',
            ]);
            $this->applyStandardSearch($query, $arguments, ['title', 'notes']);
            $this->applyStandardSort($query, $arguments, [
                'order', 'slot_order', 'due_date', 'created_at', 'updated_at',
            ], 'created_at', 'desc');

            $result = $this->applyStandardPaginationResult($query, $arguments);

            $tickets = collect($result['data'])
                ->filter(fn (HelpdeskTicket $t) => Gate::forUser($context->user)->allows('view', $t))
                ->values()
                ->map(fn (HelpdeskTicket $t) => [
                    'id' => $t->id,
                    'uuid' => $t->uuid,
                    'title' => $t->title,
                    'notes' => $t->notes,
                    'description' => $t->notes, // Abwärtskompatibilität
                    'dod_progress' => $t->dod_progress,
                    'team_id' => $t->team_id,
                    'board_id' => $t->helpdesk_board_id,
                    'board_name' => $t->helpdeskBoard?->name,
                    'slot_id' => $t->helpdesk_board_slot_id,
                    'slot_name' => $t->helpdeskBoardSlot?->name,
                    'group_id' => $t->helpdesk_ticket_group_id,
                    'group_name' => $t->helpdeskTicketGroup?->name,
                    'user_id' => $t->user_id,
                    'user_in_charge_id' => $t->user_in_charge_id,
                    'user_in_charge_name' => $t->userInCharge?->name,
                    'priority' => (string)($t->priority?->value ?? $t->priority),
                    'story_points' => (string)($t->story_points?->value ?? $t->story_points),
                    'is_done' => (bool)$t->is_done,
                    'due_date' => $t->due_date?->toDateString(),
                    'created_at' => $t->created_at?->toISOString(),
                    'updated_at' => $t->updated_at?->toISOString(),
                ])->toArray();

            return ToolResult::success([
                'data' => $tickets,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $teamId,
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ToolResult::error('ACCESS_DENIED', 'Du hast keine Berechtigung, Tickets zu sehen.');
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Tickets: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['helpdesk', 'tickets', 'list'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}


