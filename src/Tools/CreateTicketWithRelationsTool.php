<?php

namespace Platform\Helpdesk\Tools;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Helpdesk\Tools\Concerns\ResolvesHelpdeskTeam;
use Platform\Integrations\Models\IntegrationsGithubRepository;
use Platform\Integrations\Services\IntegrationAccountLinkService;

/**
 * Kombi-Endpoint: Ticket erstellen + DODs + GitHub Repos in einem einzigen Call.
 *
 * Reduziert bei komplexen Tickets 3+ sequentielle Calls auf einen einzigen.
 */
class CreateTicketWithRelationsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHelpdeskTeam;

    public function getName(): string
    {
        return 'helpdesk.tickets.compose.POST';
    }

    public function getDescription(): string
    {
        return 'POST /helpdesk/tickets/compose - Erstellt ein Ticket mit DODs und GitHub-Repos in einem einzigen Call. Kombiniert helpdesk.tickets.POST + helpdesk.ticket.dod(add_many) + helpdesk.ticket_github_repositories.bulk.POST. Ideal wenn alle Daten bereits bekannt sind.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.'],
                'title' => ['type' => 'string', 'description' => 'ERFORDERLICH: Titel des Tickets.'],
                'notes' => ['type' => 'string', 'description' => 'Anmerkung zum Ticket.'],
                'description' => ['type' => 'string', 'description' => 'Deprecated: Verwende notes stattdessen.'],
                'dod' => [
                    'type' => ['array', 'string'],
                    'description' => 'Definition of Done. Entweder als Array von {text, checked} Objekten oder als String (z.B. "[ ] Item1\n[ ] Item2").',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'text' => ['type' => 'string', 'description' => 'DoD-Eintrag Text (ERFORDERLICH).'],
                            'checked' => ['type' => 'boolean', 'description' => 'Initial abgehakt? (default: false).'],
                        ],
                        'required' => ['text'],
                    ],
                ],
                'github_repository_ids' => [
                    'type' => 'array',
                    'description' => 'Optional: Array von GitHub Repository-IDs zum Verknüpfen.',
                    'items' => ['type' => 'integer'],
                ],
                'board_id' => ['type' => 'integer'],
                'slot_id' => ['type' => 'integer'],
                'group_id' => ['type' => 'integer'],
                'due_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'priority' => [
                    'type' => 'string',
                    'description' => 'Optional: Priorität (low|normal|medium|high). "medium" ist ein Alias für "normal".',
                    'enum' => ['low', 'normal', 'medium', 'high'],
                ],
                'story_points' => [
                    'type' => 'string',
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
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            // Repo-IDs vor dem Ticket-Create rausnehmen (nicht an CreateTicketTool weiterreichen)
            $githubRepoIds = $arguments['github_repository_ids'] ?? [];
            unset($arguments['github_repository_ids']);

            if (!is_array($githubRepoIds)) {
                $githubRepoIds = [];
            }

            // Alles in einer Transaktion
            $result = DB::transaction(function () use ($arguments, $context, $githubRepoIds) {
                // 1. Ticket erstellen (inkl. DOD – CreateTicketTool verarbeitet dod bereits)
                $createTool = new CreateTicketTool();
                $ticketResult = $createTool->execute($arguments, $context);

                if (!$ticketResult->success) {
                    throw new \RuntimeException(json_encode([
                        'code' => $ticketResult->errorCode,
                        'message' => $ticketResult->error,
                        'step' => 'ticket_create',
                    ], JSON_UNESCAPED_UNICODE));
                }

                $ticketId = $ticketResult->data['id'];
                $response = [
                    'ticket' => $ticketResult->data,
                    'github_repositories' => null,
                ];

                // 2. GitHub Repos verknüpfen (falls angegeben)
                if (!empty($githubRepoIds)) {
                    $bulkLinkTool = new BulkLinkTicketGithubRepositoriesTool();
                    $linkResult = $bulkLinkTool->execute([
                        'ticket_id' => $ticketId,
                        'github_repository_ids' => $githubRepoIds,
                    ], $context);

                    if (!$linkResult->success) {
                        throw new \RuntimeException(json_encode([
                            'code' => $linkResult->errorCode,
                            'message' => $linkResult->error,
                            'step' => 'github_link',
                        ], JSON_UNESCAPED_UNICODE));
                    }

                    $response['github_repositories'] = $linkResult->data;
                }

                return $response;
            });

            // Zusammenfassung bauen
            $parts = ['Ticket erstellt'];
            if (isset($arguments['dod'])) {
                $parsedDod = CreateTicketTool::parseDod($arguments['dod']);
                if ($parsedDod && count($parsedDod) > 0) {
                    $parts[] = sprintf('%d DoD-Einträge gesetzt', count($parsedDod));
                }
            }
            if (!empty($githubRepoIds) && $result['github_repositories']) {
                $linked = $result['github_repositories']['summary']['linked'] ?? 0;
                if ($linked > 0) {
                    $parts[] = sprintf('%d Repos verknüpft', $linked);
                }
            }

            return ToolResult::success([
                'ticket' => $result['ticket'],
                'github_repositories' => $result['github_repositories'],
                'message' => implode(', ', $parts) . '.',
            ]);
        } catch (\RuntimeException $e) {
            $errorData = json_decode($e->getMessage(), true);
            if (is_array($errorData) && isset($errorData['code'])) {
                $step = $errorData['step'] ?? 'unknown';
                return ToolResult::error($errorData['code'], sprintf('[%s] %s', $step, $errorData['message']));
            }
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Tickets mit Relationen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['helpdesk', 'tickets', 'create', 'compose', 'dod', 'github', 'bulk'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
