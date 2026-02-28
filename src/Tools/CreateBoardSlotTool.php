<?php

namespace Platform\Helpdesk\Tools;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Models\HelpdeskBoardSlot;
use Platform\Helpdesk\Tools\Concerns\ResolvesHelpdeskTeam;

class CreateBoardSlotTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHelpdeskTeam;

    public function getName(): string
    {
        return 'helpdesk.board_slots.POST';
    }

    public function getDescription(): string
    {
        return 'POST /helpdesk/board_slots - Erstellt einen Slot in einem Board. Parameter: board_id (required), name (required), description (optional), order (optional).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'board_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Boards (ERFORDERLICH). Nutze "helpdesk.boards.GET".',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Name des Slots (ERFORDERLICH).',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung des Slots.',
                ],
                'order' => [
                    'type' => 'integer',
                    'description' => 'Optional: Sortierung. Default: wird ans Ende gesetzt.',
                ],
            ],
            'required' => ['board_id', 'name'],
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

            $boardId = (int)($arguments['board_id'] ?? 0);
            if ($boardId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'board_id ist erforderlich.');
            }

            $board = HelpdeskBoard::query()
                ->where('team_id', $teamId)
                ->find($boardId);

            if (!$board) {
                return ToolResult::error('NOT_FOUND', 'Board nicht gefunden (oder kein Zugriff).');
            }

            Gate::forUser($context->user)->authorize('update', $board);

            $name = trim((string)($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }

            $order = $arguments['order'] ?? null;
            if ($order === null) {
                $order = ($board->slots()->max('order') ?? 0) + 1;
            }

            $slot = HelpdeskBoardSlot::create([
                'helpdesk_board_id' => $board->id,
                'name' => $name,
                'description' => $arguments['description'] ?? null,
                'order' => (int)$order,
            ]);

            return ToolResult::success([
                'id' => $slot->id,
                'uuid' => $slot->uuid,
                'name' => $slot->name,
                'description' => $slot->description,
                'order' => (int)$slot->order,
                'helpdesk_board_id' => $slot->helpdesk_board_id,
                'board_name' => $board->name,
                'message' => 'Slot erfolgreich erstellt.',
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ToolResult::error('ACCESS_DENIED', 'Du darfst keine Slots in diesem Board erstellen.');
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Slots: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['helpdesk', 'board_slots', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
