<?php

namespace Platform\Helpdesk\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

/**
 * Bulk Create: mehrere Tickets in einem Call anlegen.
 *
 * Sinn: reduziert Toolcalls/Iterationen (LLM kann 10-50 Tickets in einem Schritt erstellen).
 * REST-Idee: POST /helpdesk/tickets/bulk
 */
class BulkCreateTicketsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'helpdesk.tickets.bulk.POST';
    }

    public function getDescription(): string
    {
        return 'POST /helpdesk/tickets/bulk - Body MUSS {tickets:[{title,...}], defaults?} enthalten. Erstellt viele Tickets in einem Call.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'atomic' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Wenn true, werden alle Creates in einer DB-Transaktion ausgeführt (bei einem Fehler wird alles zurückgerollt). Standard: true.',
                ],
                'defaults' => [
                    'type' => 'object',
                    'description' => 'Optional: Default-Werte, die auf jedes Ticket angewendet werden (können pro Ticket überschrieben werden).',
                    'properties' => [
                        'team_id' => ['type' => 'integer'],
                        'board_id' => ['type' => 'integer'],
                        'slot_id' => ['type' => 'integer'],
                        'group_id' => ['type' => 'integer'],
                        'user_in_charge_id' => ['type' => 'integer'],
                        'due_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        'priority' => [
                            'type' => 'string',
                            'enum' => ['low', 'normal', 'medium', 'high'],
                        ],
                        'story_points' => [
                            'type' => 'string',
                            'enum' => ['xs', 's', 'm', 'l', 'xl', 'xxl'],
                        ],
                    ],
                    'required' => [],
                ],
                'tickets' => [
                    'type' => 'array',
                    'description' => 'Liste von Tickets. Jedes Element entspricht den Parametern von helpdesk.tickets.POST (mindestens title).',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'notes' => ['type' => 'string', 'description' => 'Anmerkung zum Ticket.'],
                            'description' => ['type' => 'string', 'description' => 'Deprecated: Verwende notes stattdessen.'],
                            'dod' => [
                                'type' => ['array', 'string'],
                                'description' => 'Definition of Done. Array von {text, checked} oder String (z.B. "[ ] Item1\n[ ] Item2").',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'text' => ['type' => 'string'],
                                        'checked' => ['type' => 'boolean'],
                                    ],
                                    'required' => ['text'],
                                ],
                            ],
                            'team_id' => ['type' => 'integer'],
                            'board_id' => ['type' => 'integer'],
                            'slot_id' => ['type' => 'integer'],
                            'group_id' => ['type' => 'integer'],
                            'due_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                            'priority' => [
                                'type' => 'string',
                                'enum' => ['low', 'normal', 'medium', 'high'],
                            ],
                            'story_points' => [
                                'type' => 'string',
                                'enum' => ['xs', 's', 'm', 'l', 'xl', 'xxl'],
                            ],
                            'storyPoints' => [
                                'type' => 'string',
                                'enum' => ['xs', 's', 'm', 'l', 'xl', 'xxl'],
                            ],
                            'user_in_charge_id' => ['type' => 'integer'],
                        ],
                        'required' => ['title'],
                    ],
                ],
            ],
            'required' => ['tickets'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $tickets = $arguments['tickets'] ?? null;
            if (!is_array($tickets) || empty($tickets)) {
                return ToolResult::error('INVALID_ARGUMENT', 'tickets muss ein nicht-leeres Array sein.');
            }

            $defaults = $arguments['defaults'] ?? [];
            if (!is_array($defaults)) {
                $defaults = [];
            }

            $atomic = (bool)($arguments['atomic'] ?? true);
            $singleTool = new CreateTicketTool();

            $run = function () use ($tickets, $defaults, $singleTool, $context, $atomic) {
                $results = [];
                $okCount = 0;
                $failCount = 0;

                foreach ($tickets as $idx => $t) {
                    if (!is_array($t)) {
                        $failCount++;
                        $results[] = [
                            'index' => $idx,
                            'ok' => false,
                            'error' => ['code' => 'INVALID_ITEM', 'message' => 'Ticket-Item muss ein Objekt sein.'],
                        ];

                        if ($atomic) {
                            throw new \RuntimeException(json_encode([
                                'code' => 'BULK_VALIDATION_ERROR',
                                'message' => "Ticket an Index {$idx}: Ticket-Item muss ein Objekt sein.",
                                'failed_index' => $idx,
                                'results' => $results,
                            ], JSON_UNESCAPED_UNICODE));
                        }
                        continue;
                    }

                    // Defaults anwenden, ohne explizite Werte zu überschreiben
                    $payload = $defaults;
                    foreach ($t as $k => $v) {
                        $payload[$k] = $v;
                    }

                    $res = $singleTool->execute($payload, $context);
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
                            $ticketTitle = $t['title'] ?? '(kein Titel)';
                            throw new \RuntimeException(json_encode([
                                'code' => 'BULK_VALIDATION_ERROR',
                                'message' => "Ticket an Index {$idx} ('{$ticketTitle}'): {$res->error}",
                                'failed_index' => $idx,
                                'error_code' => $res->errorCode,
                                'error_message' => $res->error,
                                'results' => $results,
                            ], JSON_UNESCAPED_UNICODE));
                        }
                    }
                }

                return [
                    'results' => $results,
                    'summary' => [
                        'requested' => count($tickets),
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
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Bulk-Create der Tickets: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'bulk',
            'tags' => ['helpdesk', 'tickets', 'bulk', 'batch', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'medium',
            'idempotent' => false,
        ];
    }
}
