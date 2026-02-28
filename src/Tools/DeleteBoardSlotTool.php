<?php

namespace Platform\Helpdesk\Tools;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Helpdesk\Models\HelpdeskBoardSlot;
use Platform\Helpdesk\Tools\Concerns\ResolvesHelpdeskTeam;

class DeleteBoardSlotTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHelpdeskTeam;

    public function getName(): string
    {
        return 'helpdesk.board_slots.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /helpdesk/board_slots/{id} - Löscht einen Slot. Tickets im Slot werden in den Backlog verschoben (slot_id=null). Parameter: slot_id (required), confirm=true (required).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'slot_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Slots (ERFORDERLICH).',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'ERFORDERLICH: Setze confirm=true um wirklich zu löschen.',
                ],
            ],
            'required' => ['slot_id', 'confirm'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int)$resolved['team_id'];

            if (!($arguments['confirm'] ?? false)) {
                return ToolResult::error('CONFIRMATION_REQUIRED', 'Bitte bestätige mit confirm: true.');
            }

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

            Gate::forUser($context->user)->authorize('update', $board);

            $id = (int)$slot->id;
            $name = (string)$slot->name;

            // Tickets in den Backlog verschieben
            $movedTickets = $slot->tickets()->update(['helpdesk_board_slot_id' => null]);

            $slot->delete();

            return ToolResult::success([
                'slot_id' => $id,
                'name' => $name,
                'tickets_moved_to_backlog' => $movedTickets,
                'message' => "Slot gelöscht. {$movedTickets} Ticket(s) in den Backlog verschoben.",
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ToolResult::error('ACCESS_DENIED', 'Du darfst diesen Slot nicht löschen.');
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Slots: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['helpdesk', 'board_slots', 'delete'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
