<?php

namespace Platform\Helpdesk\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

/**
 * Bulk Delete: mehrere Tickets in einem Call löschen.
 *
 * Sinn: reduziert Toolcalls/Iterationen (LLM kann mehrere Tickets in einem Schritt löschen).
 * REST-Idee: DELETE /helpdesk/tickets/bulk
 */
class BulkDeleteTicketsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'helpdesk.tickets.bulk.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /helpdesk/tickets/bulk - Löscht mehrere Tickets in einem Request. Parameter: ticket_ids (required), confirm=true (required).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'atomic' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Wenn true, werden alle Deletes in einer DB-Transaktion ausgeführt (bei einem Fehler wird alles zurückgerollt). Standard: true.',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'ERFORDERLICH: Setze confirm=true um wirklich zu löschen.',
                ],
                'ticket_ids' => [
                    'type' => 'array',
                    'description' => 'Liste von Ticket-IDs die gelöscht werden sollen.',
                    'items' => ['type' => 'integer'],
                ],
                'team_id' => ['type' => 'integer'],
            ],
            'required' => ['ticket_ids', 'confirm'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            if (!($arguments['confirm'] ?? false)) {
                return ToolResult::error('CONFIRMATION_REQUIRED', 'Bitte bestätige mit confirm: true.');
            }

            $ticketIds = $arguments['ticket_ids'] ?? null;
            if (!is_array($ticketIds) || empty($ticketIds)) {
                return ToolResult::error('INVALID_ARGUMENT', 'ticket_ids muss ein nicht-leeres Array sein.');
            }

            $atomic = (bool)($arguments['atomic'] ?? true);
            $singleTool = new DeleteTicketTool();

            $run = function () use ($ticketIds, $singleTool, $context, $arguments, $atomic) {
                $results = [];
                $okCount = 0;
                $failCount = 0;

                foreach ($ticketIds as $idx => $ticketId) {
                    $payload = [
                        'ticket_id' => $ticketId,
                        'confirm' => true,
                    ];

                    // team_id durchreichen falls angegeben
                    if (isset($arguments['team_id'])) {
                        $payload['team_id'] = $arguments['team_id'];
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
                            throw new \RuntimeException(json_encode([
                                'code' => 'BULK_VALIDATION_ERROR',
                                'message' => "Delete an Index {$idx} (Ticket-ID {$ticketId}): {$res->error}",
                            ], JSON_UNESCAPED_UNICODE));
                        }
                    }
                }

                return [
                    'results' => $results,
                    'summary' => [
                        'requested' => count($ticketIds),
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
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Bulk-Delete der Tickets: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'bulk',
            'tags' => ['helpdesk', 'tickets', 'bulk', 'batch', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'high',
            'idempotent' => false,
        ];
    }
}
