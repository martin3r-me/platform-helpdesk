<?php

namespace Platform\Helpdesk\Tools;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Tools\Concerns\ResolvesHelpdeskTeam;

class UpdateBoardTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHelpdeskTeam;

    public function getName(): string
    {
        return 'helpdesk.boards.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /helpdesk/boards/{id} - Aktualisiert ein Board. Parameter: board_id (required), name/description/order (optional).';
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
                ],
                'description' => [
                    'type' => 'string',
                ],
                'order' => [
                    'type' => 'integer',
                ],
            ],
            'required' => ['board_id'],
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

            $found = $this->validateAndFindModel($arguments, $context, 'board_id', HelpdeskBoard::class, 'NOT_FOUND', 'Board nicht gefunden.');
            if ($found['error']) {
                return $found['error'];
            }
            /** @var HelpdeskBoard $board */
            $board = $found['model'];

            if ((int)$board->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf dieses Board.');
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
                $board->update($update);
            }

            return ToolResult::success([
                'id' => $board->id,
                'uuid' => $board->uuid,
                'name' => $board->name,
                'description' => $board->description,
                'order' => (int)$board->order,
                'team_id' => $board->team_id,
                'message' => 'Board aktualisiert.',
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ToolResult::error('ACCESS_DENIED', 'Du darfst dieses Board nicht bearbeiten.');
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Boards: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['helpdesk', 'boards', 'update'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}


