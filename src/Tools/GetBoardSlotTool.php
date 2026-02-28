<?php

namespace Platform\Helpdesk\Tools;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Models\HelpdeskBoardSlot;
use Platform\Helpdesk\Tools\Concerns\ResolvesHelpdeskTeam;

class GetBoardSlotTool implements ToolContract, ToolMetadataContract
{
    use ResolvesHelpdeskTeam;

    public function getName(): string
    {
        return 'helpdesk.board_slot.GET';
    }

    public function getDescription(): string
    {
        return 'GET /helpdesk/board_slots/{id} - Ruft einen Slot ab. Parameter: slot_id (required), team_id (optional), include_tickets_count (optional, default true).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'slot_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Slots (ERFORDERLICH). Nutze "helpdesk.board_slots.GET".',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'include_tickets_count' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
            ],
            'required' => ['slot_id'],
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

            $slotId = (int)($arguments['slot_id'] ?? 0);
            if ($slotId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'slot_id ist erforderlich.');
            }

            $slot = HelpdeskBoardSlot::with('helpdeskBoard')->find($slotId);
            if (!$slot) {
                return ToolResult::error('NOT_FOUND', 'Slot nicht gefunden.');
            }

            $board = $slot->helpdeskBoard;
            if (!$board || (int)$board->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf diesen Slot.');
            }

            Gate::forUser($context->user)->authorize('view', $board);

            $includeTicketsCount = (bool)($arguments['include_tickets_count'] ?? true);

            $result = [
                'id' => $slot->id,
                'uuid' => $slot->uuid,
                'name' => $slot->name,
                'description' => $slot->description,
                'order' => (int)$slot->order,
                'helpdesk_board_id' => $slot->helpdesk_board_id,
                'board_name' => $board->name,
                'created_at' => $slot->created_at?->toISOString(),
                'updated_at' => $slot->updated_at?->toISOString(),
            ];

            if ($includeTicketsCount) {
                $result['tickets_count'] = $slot->tickets()->count();
                $result['tickets_open'] = $slot->tickets()->where('is_done', false)->count();
                $result['tickets_done'] = $slot->tickets()->where('is_done', true)->count();
            }

            return ToolResult::success($result);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf diesen Slot.');
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des Slots: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['helpdesk', 'board_slot', 'get'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
