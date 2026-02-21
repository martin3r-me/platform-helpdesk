<?php

namespace Platform\Helpdesk\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

/**
 * Bulk Update: mehrere Tickets in einem Call aktualisieren.
 *
 * Sinn: reduziert Toolcalls/Iterationen (LLM kann 10+ Updates in einem Schritt erledigen).
 * REST-Idee: PUT /helpdesk/tickets/bulk
 */
class BulkUpdateTicketsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'helpdesk.tickets.bulk.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /helpdesk/tickets/bulk - Aktualisiert mehrere Tickets in einem Request. Nützlich für Batch-Operationen (z.B. mehrere Tickets abschließen/verschieben/Priorität setzen) ohne viele Toolcalls.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'atomic' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Wenn true, werden alle Updates in einer DB-Transaktion ausgeführt (bei einem Fehler wird alles zurückgerollt). Standard: true.',
                ],
                'updates' => [
                    'type' => 'array',
                    'description' => 'Liste von Updates. Jedes Element entspricht den Parametern von helpdesk.tickets.PUT.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'ticket_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: ID des Tickets.'],
                            'id' => ['type' => 'integer', 'description' => 'Alias für ticket_id (Deprecated).'],
                            'team_id' => ['type' => 'integer'],
                            'title' => ['type' => 'string'],
                            'notes' => ['type' => 'string'],
                            'description' => ['type' => 'string', 'description' => 'Deprecated: Verwende notes stattdessen.'],
                            'dod' => [
                                'type' => ['array', 'string'],
                                'description' => 'Definition of Done. Array von {text, checked} oder String (z.B. "[ ] Item1\n[ ] Item2"). Ersetzt bestehende DoD.',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'text' => ['type' => 'string'],
                                        'checked' => ['type' => 'boolean'],
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
                                'description' => 'Priorität (low|normal|medium|high). "medium" ist ein Alias für "normal". Setze auf null/"" um zu entfernen.',
                                'enum' => ['low', 'normal', 'medium', 'high'],
                            ],
                            'story_points' => [
                                'type' => 'string',
                                'description' => 'Story Points (xs|s|m|l|xl|xxl). Setze auf null/""/0 um zu entfernen.',
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
                    ],
                ],
            ],
            'required' => ['updates'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $updates = $arguments['updates'] ?? null;
            if (!is_array($updates) || empty($updates)) {
                return ToolResult::error('INVALID_ARGUMENT', 'updates muss ein nicht-leeres Array sein.');
            }

            $atomic = (bool)($arguments['atomic'] ?? true);
            $singleTool = new UpdateTicketTool();

            $run = function () use ($updates, $singleTool, $context, $atomic) {
                $results = [];
                $okCount = 0;
                $failCount = 0;

                foreach ($updates as $idx => $u) {
                    if (!is_array($u)) {
                        $failCount++;
                        $results[] = [
                            'index' => $idx,
                            'ok' => false,
                            'error' => ['code' => 'INVALID_ITEM', 'message' => 'Update-Item muss ein Objekt sein.'],
                        ];

                        if ($atomic) {
                            throw new \RuntimeException(json_encode([
                                'code' => 'BULK_VALIDATION_ERROR',
                                'message' => "Update an Index {$idx}: Update-Item muss ein Objekt sein.",
                            ], JSON_UNESCAPED_UNICODE));
                        }
                        continue;
                    }

                    $res = $singleTool->execute($u, $context);
                    if ($res->success) {
                        $okCount++;
                        $results[] = [
                            'index' => $idx,
                            'ok' => true,
                            'data' => $res->data,
                        ];
                    } else {
                        $failCount++;
                        $results[] = [
                            'index' => $idx,
                            'ok' => false,
                            'error' => [
                                'code' => $res->errorCode,
                                'message' => $res->error,
                            ],
                        ];

                        if ($atomic) {
                            $ticketId = $u['ticket_id'] ?? $u['id'] ?? '(keine ID)';
                            throw new \RuntimeException(json_encode([
                                'code' => 'BULK_VALIDATION_ERROR',
                                'message' => "Update an Index {$idx} (Ticket-ID {$ticketId}): {$res->error}",
                            ], JSON_UNESCAPED_UNICODE));
                        }
                    }
                }

                return [
                    'results' => $results,
                    'summary' => [
                        'requested' => count($updates),
                        'ok' => $okCount,
                        'failed' => $failCount,
                    ],
                ];
            };

            if ($atomic) {
                try {
                    $payload = DB::transaction(fn () => $run());
                } catch (\RuntimeException $e) {
                    $errorData = json_decode($e->getMessage(), true);
                    if (is_array($errorData) && isset($errorData['code'])) {
                        return ToolResult::error($errorData['code'], $errorData['message']);
                    }
                    throw $e;
                }
            } else {
                $payload = $run();
            }

            return ToolResult::success($payload);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Bulk-Update der Tickets: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'bulk',
            'tags' => ['helpdesk', 'tickets', 'bulk', 'batch', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'medium',
            'idempotent' => false,
        ];
    }
}
