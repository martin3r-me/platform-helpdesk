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
        return 'POST /helpdesk/tickets - Erstellt ein Ticket. Parameter: title (required), description (optional), board_id/slot_id/group_id (optional), due_date (optional), priority/story_points (optional), user_in_charge_id (optional).';
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
                    'type' => ['array', 'string'],
                    'description' => 'Definition of Done. Entweder als Array von {text, checked} Objekten oder als String (z.B. "[ ] Item1\n[ ] Item2").',
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

            $boardId = !empty($arguments['board_id']) ? (int)$arguments['board_id'] : null;
            $slotId = !empty($arguments['slot_id']) ? (int)$arguments['slot_id'] : null;
            $groupId = !empty($arguments['group_id']) ? (int)$arguments['group_id'] : null;

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

            // DoD validieren falls vorhanden (Array oder String)
            $dod = null;
            if (isset($arguments['dod'])) {
                $dod = self::parseDod($arguments['dod']);
            }

            $ticket = HelpdeskTicket::create([
                'team_id' => $teamId,
                'user_id' => $context->user?->id,
                'user_in_charge_id' => !empty($arguments['user_in_charge_id']) ? (int)$arguments['user_in_charge_id'] : null,
                'title' => $title,
                'notes' => $notes,
                'dod' => $dod,
                'due_date' => $arguments['due_date'] ?? null,
                'priority' => $priorityValue,
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

    /**
     * Parst einen DoD-Wert (Array oder String) in ein normalisiertes Array von {text, checked} Items.
     * String-Format: "[ ] Item1\n[ ] Item2" oder "- Item1\n- Item2" oder einfacher Text pro Zeile.
     *
     * @param mixed $dod
     * @return array|null
     */
    public static function parseDod(mixed $dod): ?array
    {
        if ($dod === null || $dod === '' || $dod === []) {
            return null;
        }

        // Wenn bereits ein Array von Items vorliegt
        if (is_array($dod)) {
            $items = array_map(function ($item) {
                return [
                    'text' => trim((string)($item['text'] ?? '')),
                    'checked' => (bool)($item['checked'] ?? false),
                ];
            }, $dod);
            $items = array_values(array_filter($items, fn($item) => $item['text'] !== ''));
            return !empty($items) ? $items : null;
        }

        // String-Format parsen
        if (is_string($dod)) {
            $dod = trim($dod);
            if ($dod === '') {
                return null;
            }

            // Versuche zuerst als JSON zu parsen
            $decoded = json_decode($dod, true);
            if (is_array($decoded) && !empty($decoded)) {
                $firstItem = reset($decoded);
                if (is_array($firstItem) && array_key_exists('text', $firstItem)) {
                    return self::parseDod($decoded);
                }
            }

            // Plaintext: Zeilen aufteilen und parsen
            $lines = preg_split('/\r\n|\r|\n/', $dod);
            $items = [];

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                // Markdown-Checkbox-Format: "- [ ] Text" oder "- [x] Text" oder "* [x] Text"
                if (preg_match('/^[-*]\s*\[([ xX])\]\s*(.+)$/', $line, $matches)) {
                    $items[] = [
                        'text' => trim($matches[2]),
                        'checked' => strtolower($matches[1]) === 'x',
                    ];
                }
                // Nur Checkbox ohne Listenpräfix: "[ ] Text" oder "[x] Text"
                elseif (preg_match('/^\[([ xX])\]\s*(.+)$/', $line, $matches)) {
                    $items[] = [
                        'text' => trim($matches[2]),
                        'checked' => strtolower($matches[1]) === 'x',
                    ];
                }
                // Einfaches Listenformat: "- Text" oder "* Text"
                elseif (preg_match('/^[-*]\s+(.+)$/', $line, $matches)) {
                    $items[] = [
                        'text' => trim($matches[1]),
                        'checked' => false,
                    ];
                }
                // Einfacher Text ohne Format
                else {
                    $items[] = [
                        'text' => $line,
                        'checked' => false,
                    ];
                }
            }

            return !empty($items) ? $items : null;
        }

        return null;
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


