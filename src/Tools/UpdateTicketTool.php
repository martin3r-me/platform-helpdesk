<?php

namespace Platform\Helpdesk\Tools;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Helpdesk\Enums\TicketPriority;
use Platform\Helpdesk\Enums\TicketStoryPoints;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Models\HelpdeskBoardSlot;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Helpdesk\Models\HelpdeskTicketGroup;
use Platform\Helpdesk\Tools\CreateTicketTool;
use Platform\Helpdesk\Tools\Concerns\ResolvesHelpdeskTeam;

class UpdateTicketTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHelpdeskTeam;

    public function getName(): string
    {
        return 'helpdesk.tickets.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /helpdesk/tickets/{id} - Aktualisiert ein Ticket. Parameter: ticket_id (required). BACKLOG: slot_id auf null setzen = Ticket zurück in den Backlog verschieben (keinem Slot zugeordnet).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer'],
                'ticket_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH'],
                'id' => ['type' => 'integer', 'description' => 'Alias für ticket_id (Deprecated). Verwende bevorzugt ticket_id.'],
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string', 'description' => 'Deprecated: Verwende notes stattdessen.'],
                'notes' => ['type' => 'string', 'description' => 'Anmerkung zum Ticket.'],
                'dod' => [
                    'type' => ['array', 'string'],
                    'description' => 'Definition of Done. Entweder als Array von {text, checked} Objekten oder als String (z.B. "[ ] Item1\n[ ] Item2"). Setzt bestehende DoD komplett neu.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'text' => ['type' => 'string', 'description' => 'DoD-Eintrag Text'],
                            'checked' => ['type' => 'boolean', 'description' => 'Abgehakt?'],
                        ],
                        'required' => ['text'],
                    ],
                ],
                'board_id' => ['type' => 'integer'],
                'slot_id' => ['type' => 'integer', 'description' => 'Slot-ID. Auf null setzen = Ticket in den Backlog verschieben (keinem Slot zugeordnet).'],
                'group_id' => ['type' => 'integer'],
                'due_date' => ['type' => 'string'],
                'priority' => [
                    'type' => 'string',
                    'description' => 'Optional: Priorität (low|normal|medium|high). "medium" ist ein Alias für "normal". Setze auf null/"" um zu entfernen.',
                    'enum' => ['low', 'normal', 'medium', 'high'],
                ],
                'story_points' => [
                    'type' => 'string',
                    'description' => 'Optional: Story Points (xs|s|m|l|xl|xxl). Setze auf null/""/0 um zu entfernen.',
                    'enum' => ['xs', 's', 'm', 'l', 'xl', 'xxl'],
                ],
                'storyPoints' => [
                    'type' => 'string',
                    'description' => 'Alias für story_points.',
                    'enum' => ['xs', 's', 'm', 'l', 'xl', 'xxl'],
                ],
                'user_in_charge_id' => ['type' => 'integer'],
                'is_done' => ['type' => 'boolean'],
                'is_locked' => ['type' => 'boolean', 'description' => 'Ticket sperren (true) oder entsperren (false).'],
            ],
            'required' => ['ticket_id'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            // Backward compatible: allow "id" as alias for "ticket_id"
            if (!array_key_exists('ticket_id', $arguments) && array_key_exists('id', $arguments)) {
                $arguments['ticket_id'] = $arguments['id'];
            }

            // Backward compatible: allow "storyPoints" as alias for "story_points"
            if (!array_key_exists('story_points', $arguments) && array_key_exists('storyPoints', $arguments)) {
                $arguments['story_points'] = $arguments['storyPoints'];
            }

            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int)$resolved['team_id'];

            $found = $this->validateAndFindModel($arguments, $context, 'ticket_id', HelpdeskTicket::class, 'NOT_FOUND', 'Ticket nicht gefunden.');
            if ($found['error']) {
                return $found['error'];
            }
            /** @var HelpdeskTicket $ticket */
            $ticket = $found['model'];

            if ((int)$ticket->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf dieses Ticket.');
            }

            // Lock/Unlock separat behandeln (eigene Policy-Gates + Model-Methoden)
            if (array_key_exists('is_locked', $arguments)) {
                $wantLock = (bool)$arguments['is_locked'];
                if ($wantLock && !$ticket->isLocked()) {
                    Gate::forUser($context->user)->authorize('lock', $ticket);
                    $ticket->lock();
                } elseif (!$wantLock && $ticket->isLocked()) {
                    Gate::forUser($context->user)->authorize('unlock', $ticket);
                    $ticket->unlock();
                }
                $ticket->refresh();

                // Wenn nur is_locked geändert wird, direkt zurückgeben
                $otherArgs = collect($arguments)->except(['ticket_id', 'id', 'team_id', 'is_locked'])->filter(fn($v) => $v !== null);
                if ($otherArgs->isEmpty()) {
                    return ToolResult::success([
                        'id' => $ticket->id,
                        'uuid' => $ticket->uuid,
                        'title' => $ticket->title,
                        'team_id' => $ticket->team_id,
                        'is_done' => (bool)$ticket->is_done,
                        'is_locked' => (bool)$ticket->is_locked,
                        'message' => $wantLock ? 'Ticket gesperrt.' : 'Ticket entsperrt.',
                    ]);
                }
            }

            Gate::forUser($context->user)->authorize('update', $ticket);

            $update = [];
            foreach ([
                'title',
                'notes',
                'due_date',
                'user_in_charge_id',
                'is_done',
            ] as $f) {
                if (array_key_exists($f, $arguments)) {
                    $update[$f] = $arguments[$f] === '' ? null : $arguments[$f];
                }
            }

            // Abwärtskompatibilität: description -> notes
            if (array_key_exists('description', $arguments) && !array_key_exists('notes', $arguments)) {
                $update['notes'] = $arguments['description'] === '' ? null : $arguments['description'];
            }

            // DoD aktualisieren (Array oder String)
            if (array_key_exists('dod', $arguments)) {
                $update['dod'] = CreateTicketTool::parseDod($arguments['dod']);
            }

            // Priority normalisieren/validieren (damit Enum-Cast nie knallt)
            if (array_key_exists('priority', $arguments)) {
                $prio = $arguments['priority'];
                if (is_string($prio)) {
                    $prio = trim($prio);
                }

                if ($prio === null || $prio === '' || $prio === 'null') {
                    $update['priority'] = null;
                } else {
                    $normalized = strtolower((string)$prio);
                    $enum = TicketPriority::tryFromWithAlias($normalized);
                    if (!$enum) {
                        return ToolResult::error(
                            'VALIDATION_ERROR',
                            'Ungültige priority. Erlaubt: low|normal|medium|high (oder null/"" zum Entfernen).'
                        );
                    }
                    $update['priority'] = $enum->value;
                }
            }

            // Story points normalisieren/validieren (damit Enum-Cast nie knallt)
            if (array_key_exists('story_points', $arguments)) {
                $sp = $arguments['story_points'];
                if (is_string($sp)) {
                    $sp = trim($sp);
                }

                if ($sp === null || $sp === '' || $sp === 'null' || $sp === 0 || $sp === '0') {
                    $update['story_points'] = null;
                } else {
                    $normalized = strtolower((string)$sp);
                    $enum = TicketStoryPoints::tryFrom($normalized);
                    if (!$enum) {
                        return ToolResult::error(
                            'VALIDATION_ERROR',
                            'Ungültige story_points. Erlaubt: xs|s|m|l|xl|xxl (oder null/""/0 zum Entfernen).'
                        );
                    }
                    $update['story_points'] = $enum->value;
                }
            }

            if (isset($update['title'])) {
                $update['title'] = trim((string)$update['title']);
                if ($update['title'] === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'title darf nicht leer sein.');
                }
            }

            // Board/Slot/Group Änderungen validieren
            if (array_key_exists('board_id', $arguments)) {
                $boardId = $arguments['board_id'] ? (int)$arguments['board_id'] : null;
                if ($boardId) {
                    $board = HelpdeskBoard::query()->where('team_id', $teamId)->find($boardId);
                    if (!$board) {
                        return ToolResult::error('VALIDATION_ERROR', 'Ungültiger board_id (nicht gefunden oder kein Zugriff).');
                    }
                    Gate::forUser($context->user)->authorize('view', $board);
                }
                $update['helpdesk_board_id'] = $boardId;
            }

            if (array_key_exists('slot_id', $arguments)) {
                $slotId = $arguments['slot_id'] ? (int)$arguments['slot_id'] : null;
                if ($slotId) {
                    $slot = HelpdeskBoardSlot::query()->find($slotId);
                    if (!$slot) {
                        return ToolResult::error('VALIDATION_ERROR', 'Ungültiger slot_id (nicht gefunden).');
                    }
                    // Wenn board gesetzt ist (neu oder alt), muss Slot dazu passen
                    $finalBoardId = $update['helpdesk_board_id'] ?? $ticket->helpdesk_board_id;
                    if ($finalBoardId && (int)$slot->helpdesk_board_id !== (int)$finalBoardId) {
                        return ToolResult::error('VALIDATION_ERROR', 'slot_id gehört nicht zum Board.');
                    }
                }
                $update['helpdesk_board_slot_id'] = $slotId;
            }

            if (array_key_exists('group_id', $arguments)) {
                $groupId = $arguments['group_id'] ? (int)$arguments['group_id'] : null;
                if ($groupId) {
                    $group = HelpdeskTicketGroup::query()->where('team_id', $teamId)->find($groupId);
                    if (!$group) {
                        return ToolResult::error('VALIDATION_ERROR', 'Ungültiger group_id (nicht gefunden oder kein Zugriff).');
                    }
                }
                $update['helpdesk_ticket_group_id'] = $groupId;
            }

            if (array_key_exists('is_done', $update)) {
                $update['done_at'] = $update['is_done'] ? now() : null;
            }

            if (!empty($update)) {
                $ticket->update($update);
            }
            $ticket->refresh();

            return ToolResult::success([
                'id' => $ticket->id,
                'uuid' => $ticket->uuid,
                'title' => $ticket->title,
                'team_id' => $ticket->team_id,
                'is_done' => (bool)$ticket->is_done,
                'is_locked' => (bool)$ticket->is_locked,
                'message' => 'Ticket aktualisiert.',
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ToolResult::error('ACCESS_DENIED', 'Du darfst dieses Ticket nicht bearbeiten (ggf. gesperrt oder keine Berechtigung zum Sperren/Entsperren).');
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Tickets: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['helpdesk', 'tickets', 'update'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}


