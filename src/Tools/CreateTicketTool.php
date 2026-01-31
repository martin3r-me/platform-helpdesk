<?php

namespace Platform\Helpdesk\Tools;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Helpdesk\Enums\TicketPriority;
use Platform\Helpdesk\Enums\TicketStatus;
use Platform\Helpdesk\Enums\TicketStoryPoints;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Models\HelpdeskBoardSlot;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Helpdesk\Models\HelpdeskTicketGroup;
use Platform\Helpdesk\Tools\Concerns\ResolvesHelpdeskTeam;

class CreateTicketTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHelpdeskTeam;

    public function getName(): string
    {
        return 'helpdesk.tickets.POST';
    }

    public function getDescription(): string
    {
        return 'POST /helpdesk/tickets - Erstellt ein Ticket. Parameter: title (required), description (optional), board_id/slot_id/group_id (optional), due_date (optional), priority/status/story_points (optional), user_in_charge_id (optional).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer'],
                'title' => ['type' => 'string', 'description' => 'ERFORDERLICH'],
                'description' => ['type' => 'string', 'description' => 'Deprecated: Verwende notes stattdessen.'],
                'notes' => ['type' => 'string', 'description' => 'Anmerkung zum Ticket.'],
                'dod' => [
                    'type' => 'array',
                    'description' => 'Definition of Done.',
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
                'slot_id' => ['type' => 'integer'],
                'group_id' => ['type' => 'integer'],
                'due_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'priority' => [
                    'type' => 'string',
                    'description' => 'Optional: Priorität (low|normal|high). Setze auf null/"" um zu entfernen.',
                    'enum' => ['low', 'normal', 'high'],
                ],
                'status' => [
                    'type' => 'string',
                    'description' => 'Optional: Status (open|in_progress|waiting|resolved|closed).',
                    'enum' => ['open', 'in_progress', 'waiting', 'resolved', 'closed'],
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
            ],
            'required' => ['title'],
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

            Gate::forUser($context->user)->authorize('create', HelpdeskTicket::class);

            $title = trim((string)($arguments['title'] ?? ''));
            if ($title === '') {
                return ToolResult::error('VALIDATION_ERROR', 'title ist erforderlich.');
            }

            // Backward compatible: allow "storyPoints" as alias for "story_points"
            if (!array_key_exists('story_points', $arguments) && array_key_exists('storyPoints', $arguments)) {
                $arguments['story_points'] = $arguments['storyPoints'];
            }

            // Priority normalisieren/validieren (damit Enum-Cast nie knallt)
            $priorityValue = null;
            if (array_key_exists('priority', $arguments)) {
                $prio = $arguments['priority'];
                if (is_string($prio)) {
                    $prio = trim($prio);
                }
                if ($prio === null || $prio === '' || $prio === 'null') {
                    $priorityValue = null;
                } else {
                    $normalized = strtolower((string)$prio);
                    $enum = TicketPriority::tryFrom($normalized);
                    if (!$enum) {
                        return ToolResult::error(
                            'VALIDATION_ERROR',
                            'Ungültige priority. Erlaubt: low|normal|high (oder null/"" zum Entfernen).'
                        );
                    }
                    $priorityValue = $enum->value;
                }
            }

            // Status normalisieren/validieren (damit Enum-Cast nie knallt)
            $statusValue = null;
            if (array_key_exists('status', $arguments)) {
                $st = $arguments['status'];
                if (is_string($st)) {
                    $st = trim($st);
                }
                if ($st === null || $st === '' || $st === 'null') {
                    $statusValue = null;
                } else {
                    $normalized = strtolower((string)$st);
                    $enum = TicketStatus::tryFrom($normalized);
                    if (!$enum) {
                        return ToolResult::error(
                            'VALIDATION_ERROR',
                            'Ungültiger status. Erlaubt: open|in_progress|waiting|resolved|closed (oder null/"" zum Entfernen).'
                        );
                    }
                    $statusValue = $enum->value;
                }
            }

            // Story points normalisieren/validieren (damit Enum-Cast nie knallt)
            $storyPointsValue = null;
            if (array_key_exists('story_points', $arguments)) {
                $sp = $arguments['story_points'];
                if (is_string($sp)) {
                    $sp = trim($sp);
                }
                if ($sp === null || $sp === '' || $sp === 'null' || $sp === 0 || $sp === '0') {
                    $storyPointsValue = null;
                } else {
                    $normalized = strtolower((string)$sp);
                    $enum = TicketStoryPoints::tryFrom($normalized);
                    if (!$enum) {
                        return ToolResult::error(
                            'VALIDATION_ERROR',
                            'Ungültige story_points. Erlaubt: xs|s|m|l|xl|xxl (oder null/""/0 zum Entfernen).'
                        );
                    }
                    $storyPointsValue = $enum->value;
                }
            }

            $boardId = isset($arguments['board_id']) ? (int)$arguments['board_id'] : null;
            $slotId = isset($arguments['slot_id']) ? (int)$arguments['slot_id'] : null;
            $groupId = isset($arguments['group_id']) ? (int)$arguments['group_id'] : null;

            $board = null;
            if ($boardId) {
                $board = HelpdeskBoard::query()->where('team_id', $teamId)->find($boardId);
                if (!$board) {
                    return ToolResult::error('VALIDATION_ERROR', 'Ungültiger board_id (nicht gefunden oder kein Zugriff).');
                }
                Gate::forUser($context->user)->authorize('view', $board);
            }

            if ($slotId) {
                $slot = HelpdeskBoardSlot::query()->find($slotId);
                if (!$slot || ($board && (int)$slot->helpdesk_board_id !== (int)$board->id)) {
                    return ToolResult::error('VALIDATION_ERROR', 'Ungültiger slot_id (nicht gefunden oder nicht im Board).');
                }
            }

            if ($groupId) {
                $group = HelpdeskTicketGroup::query()->where('team_id', $teamId)->find($groupId);
                if (!$group) {
                    return ToolResult::error('VALIDATION_ERROR', 'Ungültiger group_id (nicht gefunden oder kein Zugriff).');
                }
            }

            // notes/description: notes hat Priorität, description für Abwärtskompatibilität
            $notes = $arguments['notes'] ?? $arguments['description'] ?? null;

            // DoD validieren falls vorhanden
            $dod = null;
            if (isset($arguments['dod']) && is_array($arguments['dod'])) {
                $dod = array_map(function ($item) {
                    return [
                        'text' => trim((string)($item['text'] ?? '')),
                        'checked' => (bool)($item['checked'] ?? false),
                    ];
                }, $arguments['dod']);
                // Leere Einträge entfernen
                $dod = array_values(array_filter($dod, fn($item) => $item['text'] !== ''));
            }

            $ticket = HelpdeskTicket::create([
                'team_id' => $teamId,
                'user_id' => $context->user?->id,
                'user_in_charge_id' => $arguments['user_in_charge_id'] ?? null,
                'title' => $title,
                'notes' => $notes,
                'dod' => $dod,
                'due_date' => $arguments['due_date'] ?? null,
                'priority' => $priorityValue,
                'status' => $statusValue,
                'story_points' => $storyPointsValue,
                'helpdesk_board_id' => $boardId,
                'helpdesk_board_slot_id' => $slotId,
                'helpdesk_ticket_group_id' => $groupId,
            ]);

            return ToolResult::success([
                'id' => $ticket->id,
                'uuid' => $ticket->uuid,
                'title' => $ticket->title,
                'team_id' => $ticket->team_id,
                'message' => 'Ticket erfolgreich erstellt.',
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ToolResult::error('ACCESS_DENIED', 'Du darfst kein Ticket erstellen.');
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Tickets: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['helpdesk', 'tickets', 'create'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}


