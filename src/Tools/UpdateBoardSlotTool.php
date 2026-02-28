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

class UpdateBoardSlotTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHelpdeskTeam;

    public function getName(): string
    {
        return 'helpdesk.board_slots.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /helpdesk/board_slots/{id} - Aktualisiert einen Slot. Parameter: slot_id (required), name/description/order (optional).';
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
                    'description' => 'ID des Slots (ERFORDERLICH). Nutze "helpdesk.board_slots.GET".',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Name des Slots.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Neue Beschreibung.',
                ],
                'order' => [
                    'type' => 'integer',
                    'description' => 'Optional: Neue Sortierung.',
                ],
            ],
            'required' => ['slot_id'],
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

            $update = [];
            foreach (['name', 'description', 'order'] as $f) {
                if (array_key_exists($f, $arguments)) {
                    $update[$f] = $arguments[$f] === '' ? null : $arguments[$f];
                }
            }

            if (isset($update['name'])) {
                $update['name'] = trim((string)$update['name']);
                if ($update['name'] === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'name darf nicht leer sein.');
                }
            }

            if (!empty($update)) {
                $slot->update($update);
            }

            return ToolResult::success([
                'id' => $slot->id,
                'uuid' => $slot->uuid,
                'name' => $slot->name,
                'description' => $slot->description,
                'order' => (int)$slot->order,
                'helpdesk_board_id' => $slot->helpdesk_board_id,
                'board_name' => $board->name,
                'message' => 'Slot aktualisiert.',
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ToolResult::error('ACCESS_DENIED', 'Du darfst diesen Slot nicht bearbeiten.');
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Slots: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['helpdesk', 'board_slots', 'update'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
