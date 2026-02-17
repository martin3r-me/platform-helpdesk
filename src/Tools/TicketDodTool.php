<?php

namespace Platform\Helpdesk\Tools;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Helpdesk\Tools\Concerns\ResolvesHelpdeskTeam;

class TicketDodTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHelpdeskTeam;

    public function getName(): string
    {
        return 'helpdesk.ticket.dod';
    }

    public function getDescription(): string
    {
        return 'Verwaltet die Definition of Done (DoD) eines Tickets. Operationen: list (anzeigen), add (hinzufügen), add_many (mehrere auf einmal hinzufügen), remove (entfernen), toggle (abhaken/abwählen), update (Text ändern), update_many (mehrere auf einmal updaten), move (verschieben).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.'],
                'ticket_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: ID des Tickets.'],
                'operation' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Die auszuführende Operation.',
                    'enum' => ['list', 'add', 'add_many', 'remove', 'toggle', 'update', 'update_many', 'move'],
                ],
                'index' => [
                    'type' => 'integer',
                    'description' => 'Index des DoD-Eintrags (0-basiert). Erforderlich für: remove, toggle, update, move.',
                ],
                'text' => [
                    'type' => 'string',
                    'description' => 'Text des DoD-Eintrags. Erforderlich für: add. Optional für: update (neuer Text).',
                ],
                'checked' => [
                    'type' => 'boolean',
                    'description' => 'Optional für add: Initialer Status (default: false). Optional für update: Neuer Status.',
                ],
                'direction' => [
                    'type' => 'string',
                    'description' => 'Richtung für move-Operation.',
                    'enum' => ['up', 'down'],
                ],
                'items' => [
                    'type' => 'array',
                    'description' => 'Array von DoD-Einträgen. Erforderlich für: add_many. Jedes Item: {text, checked?}.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'text' => ['type' => 'string', 'description' => 'DoD-Eintrag Text (ERFORDERLICH).'],
                            'checked' => ['type' => 'boolean', 'description' => 'Initialer Status (default: false).'],
                        ],
                        'required' => ['text'],
                    ],
                ],
                'updates' => [
                    'type' => 'array',
                    'description' => 'Array von Updates. Erforderlich für: update_many. Jedes Item: {index, text?, checked?}.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'index' => ['type' => 'integer', 'description' => 'Index des DoD-Eintrags (0-basiert, ERFORDERLICH).'],
                            'text' => ['type' => 'string', 'description' => 'Neuer Text (optional).'],
                            'checked' => ['type' => 'boolean', 'description' => 'Neuer Status (optional).'],
                        ],
                        'required' => ['index'],
                    ],
                ],
            ],
            'required' => ['ticket_id', 'operation'],
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

            $ticketId = (int)($arguments['ticket_id'] ?? 0);
            if ($ticketId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'ticket_id ist erforderlich.');
            }

            $operation = trim((string)($arguments['operation'] ?? ''));
            if (!in_array($operation, ['list', 'add', 'add_many', 'remove', 'toggle', 'update', 'update_many', 'move'])) {
                return ToolResult::error('VALIDATION_ERROR', 'Ungültige operation. Erlaubt: list|add|add_many|remove|toggle|update|update_many|move.');
            }

            $ticket = HelpdeskTicket::query()
                ->where('team_id', $teamId)
                ->find($ticketId);

            if (!$ticket) {
                return ToolResult::error('NOT_FOUND', 'Ticket nicht gefunden (oder kein Zugriff).');
            }

            // Für list reicht view, für alles andere brauchen wir update
            if ($operation === 'list') {
                Gate::forUser($context->user)->authorize('view', $ticket);
            } else {
                Gate::forUser($context->user)->authorize('update', $ticket);
            }

            return match ($operation) {
                'list' => $this->listDod($ticket),
                'add' => $this->addDodItem($ticket, $arguments),
                'add_many' => $this->addManyDodItems($ticket, $arguments),
                'remove' => $this->removeDodItem($ticket, $arguments),
                'toggle' => $this->toggleDodItem($ticket, $arguments),
                'update' => $this->updateDodItem($ticket, $arguments),
                'update_many' => $this->updateManyDodItems($ticket, $arguments),
                'move' => $this->moveDodItem($ticket, $arguments),
                default => ToolResult::error('VALIDATION_ERROR', 'Unbekannte Operation.'),
            };
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ToolResult::error('ACCESS_DENIED', 'Keine Berechtigung für diese Aktion.');
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    protected function listDod(HelpdeskTicket $ticket): ToolResult
    {
        $dod = $ticket->dod ?? [];

        return ToolResult::success([
            'ticket_id' => $ticket->id,
            'title' => $ticket->title,
            'dod' => array_map(function ($item, $index) {
                return [
                    'index' => $index,
                    'text' => $item['text'] ?? '',
                    'checked' => (bool)($item['checked'] ?? false),
                ];
            }, $dod, array_keys($dod)),
            'dod_progress' => $ticket->dod_progress,
            'message' => count($dod) > 0
                ? sprintf('DoD enthält %d Eintrag/Einträge.', count($dod))
                : 'Keine DoD-Einträge vorhanden.',
        ]);
    }

    protected function addDodItem(HelpdeskTicket $ticket, array $arguments): ToolResult
    {
        $text = trim((string)($arguments['text'] ?? ''));
        if ($text === '') {
            return ToolResult::error('VALIDATION_ERROR', 'text ist erforderlich für add-Operation.');
        }

        $checked = (bool)($arguments['checked'] ?? false);

        $dod = $ticket->dod ?? [];
        $dod[] = ['text' => $text, 'checked' => $checked];
        $ticket->dod = $dod;
        $ticket->save();

        return ToolResult::success([
            'ticket_id' => $ticket->id,
            'title' => $ticket->title,
            'added_item' => [
                'index' => count($dod) - 1,
                'text' => $text,
                'checked' => $checked,
            ],
            'dod_progress' => $ticket->dod_progress,
            'message' => 'DoD-Eintrag hinzugefügt.',
        ]);
    }

    protected function addManyDodItems(HelpdeskTicket $ticket, array $arguments): ToolResult
    {
        $items = $arguments['items'] ?? null;
        if (!is_array($items) || empty($items)) {
            return ToolResult::error('VALIDATION_ERROR', 'items ist erforderlich für add_many-Operation (Array von {text, checked?}).');
        }

        $dod = $ticket->dod ?? [];
        $addedItems = [];
        $startIndex = count($dod);

        foreach ($items as $idx => $item) {
            if (!is_array($item)) {
                return ToolResult::error('VALIDATION_ERROR', sprintf('items[%d] muss ein Objekt sein.', $idx));
            }

            $text = trim((string)($item['text'] ?? ''));
            if ($text === '') {
                return ToolResult::error('VALIDATION_ERROR', sprintf('items[%d].text darf nicht leer sein.', $idx));
            }

            $checked = (bool)($item['checked'] ?? false);
            $dod[] = ['text' => $text, 'checked' => $checked];
            $addedItems[] = [
                'index' => $startIndex + count($addedItems),
                'text' => $text,
                'checked' => $checked,
            ];
        }

        $ticket->dod = $dod;
        $ticket->save();

        return ToolResult::success([
            'ticket_id' => $ticket->id,
            'title' => $ticket->title,
            'added_items' => $addedItems,
            'added_count' => count($addedItems),
            'dod_progress' => $ticket->dod_progress,
            'message' => sprintf('%d DoD-Einträge hinzugefügt.', count($addedItems)),
        ]);
    }

    protected function removeDodItem(HelpdeskTicket $ticket, array $arguments): ToolResult
    {
        if (!array_key_exists('index', $arguments)) {
            return ToolResult::error('VALIDATION_ERROR', 'index ist erforderlich für remove-Operation.');
        }

        $index = (int)$arguments['index'];
        $dod = $ticket->dod ?? [];

        if ($index < 0 || $index >= count($dod)) {
            return ToolResult::error('VALIDATION_ERROR', sprintf('Ungültiger index. Erlaubt: 0-%d.', max(0, count($dod) - 1)));
        }

        $removedItem = $dod[$index];
        array_splice($dod, $index, 1);
        $ticket->dod = array_values($dod);
        $ticket->save();

        return ToolResult::success([
            'ticket_id' => $ticket->id,
            'title' => $ticket->title,
            'removed_item' => [
                'index' => $index,
                'text' => $removedItem['text'] ?? '',
                'checked' => (bool)($removedItem['checked'] ?? false),
            ],
            'dod_progress' => $ticket->dod_progress,
            'message' => 'DoD-Eintrag entfernt.',
        ]);
    }

    protected function toggleDodItem(HelpdeskTicket $ticket, array $arguments): ToolResult
    {
        if (!array_key_exists('index', $arguments)) {
            return ToolResult::error('VALIDATION_ERROR', 'index ist erforderlich für toggle-Operation.');
        }

        $index = (int)$arguments['index'];
        $dod = $ticket->dod ?? [];

        if ($index < 0 || $index >= count($dod)) {
            return ToolResult::error('VALIDATION_ERROR', sprintf('Ungültiger index. Erlaubt: 0-%d.', max(0, count($dod) - 1)));
        }

        $dod[$index]['checked'] = !($dod[$index]['checked'] ?? false);
        $ticket->dod = $dod;
        $ticket->save();

        return ToolResult::success([
            'ticket_id' => $ticket->id,
            'title' => $ticket->title,
            'toggled_item' => [
                'index' => $index,
                'text' => $dod[$index]['text'] ?? '',
                'checked' => (bool)$dod[$index]['checked'],
            ],
            'dod_progress' => $ticket->dod_progress,
            'message' => $dod[$index]['checked'] ? 'DoD-Eintrag als erledigt markiert.' : 'DoD-Eintrag als nicht erledigt markiert.',
        ]);
    }

    protected function updateDodItem(HelpdeskTicket $ticket, array $arguments): ToolResult
    {
        if (!array_key_exists('index', $arguments)) {
            return ToolResult::error('VALIDATION_ERROR', 'index ist erforderlich für update-Operation.');
        }

        $index = (int)$arguments['index'];
        $dod = $ticket->dod ?? [];

        if ($index < 0 || $index >= count($dod)) {
            return ToolResult::error('VALIDATION_ERROR', sprintf('Ungültiger index. Erlaubt: 0-%d.', max(0, count($dod) - 1)));
        }

        $changed = false;

        if (array_key_exists('text', $arguments)) {
            $text = trim((string)$arguments['text']);
            if ($text === '') {
                return ToolResult::error('VALIDATION_ERROR', 'text darf nicht leer sein.');
            }
            $dod[$index]['text'] = $text;
            $changed = true;
        }

        if (array_key_exists('checked', $arguments)) {
            $dod[$index]['checked'] = (bool)$arguments['checked'];
            $changed = true;
        }

        if (!$changed) {
            return ToolResult::error('VALIDATION_ERROR', 'Mindestens text oder checked muss angegeben werden.');
        }

        $ticket->dod = $dod;
        $ticket->save();

        return ToolResult::success([
            'ticket_id' => $ticket->id,
            'title' => $ticket->title,
            'updated_item' => [
                'index' => $index,
                'text' => $dod[$index]['text'] ?? '',
                'checked' => (bool)($dod[$index]['checked'] ?? false),
            ],
            'dod_progress' => $ticket->dod_progress,
            'message' => 'DoD-Eintrag aktualisiert.',
        ]);
    }

    protected function updateManyDodItems(HelpdeskTicket $ticket, array $arguments): ToolResult
    {
        $updates = $arguments['updates'] ?? null;
        if (!is_array($updates) || empty($updates)) {
            return ToolResult::error('VALIDATION_ERROR', 'updates ist erforderlich für update_many-Operation (Array von {index, text?, checked?}).');
        }

        $dod = $ticket->dod ?? [];
        $dodCount = count($dod);
        $updatedItems = [];

        // Erst alle validieren, dann alle anwenden (atomar)
        foreach ($updates as $idx => $update) {
            if (!is_array($update)) {
                return ToolResult::error('VALIDATION_ERROR', sprintf('updates[%d] muss ein Objekt sein.', $idx));
            }

            if (!array_key_exists('index', $update)) {
                return ToolResult::error('VALIDATION_ERROR', sprintf('updates[%d].index ist erforderlich.', $idx));
            }

            $itemIndex = (int)$update['index'];
            if ($itemIndex < 0 || $itemIndex >= $dodCount) {
                return ToolResult::error('VALIDATION_ERROR', sprintf('updates[%d].index=%d ist ungültig. Erlaubt: 0-%d.', $idx, $itemIndex, max(0, $dodCount - 1)));
            }

            $hasChange = array_key_exists('text', $update) || array_key_exists('checked', $update);
            if (!$hasChange) {
                return ToolResult::error('VALIDATION_ERROR', sprintf('updates[%d]: Mindestens text oder checked muss angegeben werden.', $idx));
            }

            if (array_key_exists('text', $update)) {
                $text = trim((string)$update['text']);
                if ($text === '') {
                    return ToolResult::error('VALIDATION_ERROR', sprintf('updates[%d].text darf nicht leer sein.', $idx));
                }
            }
        }

        // Alle Updates anwenden
        foreach ($updates as $idx => $update) {
            $itemIndex = (int)$update['index'];

            if (array_key_exists('text', $update)) {
                $dod[$itemIndex]['text'] = trim((string)$update['text']);
            }
            if (array_key_exists('checked', $update)) {
                $dod[$itemIndex]['checked'] = (bool)$update['checked'];
            }

            $updatedItems[] = [
                'index' => $itemIndex,
                'text' => $dod[$itemIndex]['text'] ?? '',
                'checked' => (bool)($dod[$itemIndex]['checked'] ?? false),
            ];
        }

        $ticket->dod = $dod;
        $ticket->save();

        return ToolResult::success([
            'ticket_id' => $ticket->id,
            'title' => $ticket->title,
            'updated_items' => $updatedItems,
            'updated_count' => count($updatedItems),
            'dod_progress' => $ticket->dod_progress,
            'message' => sprintf('%d DoD-Einträge aktualisiert.', count($updatedItems)),
        ]);
    }

    protected function moveDodItem(HelpdeskTicket $ticket, array $arguments): ToolResult
    {
        if (!array_key_exists('index', $arguments)) {
            return ToolResult::error('VALIDATION_ERROR', 'index ist erforderlich für move-Operation.');
        }

        $direction = trim((string)($arguments['direction'] ?? ''));
        if (!in_array($direction, ['up', 'down'])) {
            return ToolResult::error('VALIDATION_ERROR', 'direction ist erforderlich für move-Operation. Erlaubt: up|down.');
        }

        $index = (int)$arguments['index'];
        $dod = $ticket->dod ?? [];

        if ($index < 0 || $index >= count($dod)) {
            return ToolResult::error('VALIDATION_ERROR', sprintf('Ungültiger index. Erlaubt: 0-%d.', max(0, count($dod) - 1)));
        }

        $newIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if ($newIndex < 0 || $newIndex >= count($dod)) {
            return ToolResult::error('VALIDATION_ERROR', sprintf('Kann nicht nach %s verschieben (bereits am %s).', $direction, $direction === 'up' ? 'Anfang' : 'Ende'));
        }

        // Tausche die Elemente
        $temp = $dod[$index];
        $dod[$index] = $dod[$newIndex];
        $dod[$newIndex] = $temp;

        $ticket->dod = $dod;
        $ticket->save();

        return ToolResult::success([
            'ticket_id' => $ticket->id,
            'title' => $ticket->title,
            'moved_item' => [
                'old_index' => $index,
                'new_index' => $newIndex,
                'text' => $temp['text'] ?? '',
                'checked' => (bool)($temp['checked'] ?? false),
            ],
            'dod_progress' => $ticket->dod_progress,
            'message' => sprintf('DoD-Eintrag von Position %d nach Position %d verschoben.', $index, $newIndex),
        ]);
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['helpdesk', 'ticket', 'dod', 'definition-of-done'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
