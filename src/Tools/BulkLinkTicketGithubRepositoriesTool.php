<?php

namespace Platform\Helpdesk\Tools;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Helpdesk\Tools\Concerns\ResolvesHelpdeskTeam;
use Platform\Integrations\Models\IntegrationsGithubRepository;
use Platform\Integrations\Services\IntegrationAccountLinkService;

/**
 * Bulk-Tool zum Verknüpfen mehrerer GitHub Repositories mit einem Ticket in einem Call.
 */
class BulkLinkTicketGithubRepositoriesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHelpdeskTeam;

    public function getName(): string
    {
        return 'helpdesk.ticket_github_repositories.bulk.POST';
    }

    public function getDescription(): string
    {
        return 'POST /helpdesk/tickets/{ticket_id}/github_repositories/bulk - Verknüpft mehrere GitHub Repositories mit einem Ticket in einem Call. Reduziert Toolcalls bei vielen Repos.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'ticket_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Tickets (ERFORDERLICH).',
                ],
                'github_repository_ids' => [
                    'type' => 'array',
                    'description' => 'ERFORDERLICH: Array von GitHub Repository-IDs. Nutze "helpdesk.github_repositories.GET" um Repositories zu finden.',
                    'items' => [
                        'type' => 'integer',
                    ],
                ],
            ],
            'required' => ['ticket_id', 'github_repository_ids'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            // Ticket finden und validieren
            $validation = $this->validateAndFindModel(
                $arguments,
                $context,
                'ticket_id',
                HelpdeskTicket::class,
                'TICKET_NOT_FOUND',
                'Das angegebene Ticket wurde nicht gefunden.'
            );

            if ($validation['error']) {
                return $validation['error'];
            }

            /** @var HelpdeskTicket $ticket */
            $ticket = $validation['model'];

            // Policy prüfen
            try {
                Gate::forUser($context->user)->authorize('update', $ticket);
            } catch (AuthorizationException $e) {
                return ToolResult::error('ACCESS_DENIED', 'Du darfst dieses Ticket nicht bearbeiten.');
            }

            $repoIds = $arguments['github_repository_ids'] ?? null;
            if (!is_array($repoIds) || empty($repoIds)) {
                return ToolResult::error('VALIDATION_ERROR', 'github_repository_ids muss ein nicht-leeres Array sein.');
            }

            $linkService = app(IntegrationAccountLinkService::class);
            $results = [];
            $linkedCount = 0;
            $alreadyLinkedCount = 0;
            $failedCount = 0;

            foreach ($repoIds as $idx => $repoId) {
                $repoId = (int)$repoId;
                if ($repoId <= 0) {
                    $failedCount++;
                    $results[] = [
                        'index' => $idx,
                        'github_repository_id' => $repoId,
                        'ok' => false,
                        'error' => 'Ungültige Repository-ID.',
                    ];
                    continue;
                }

                $githubRepository = IntegrationsGithubRepository::where('id', $repoId)
                    ->where('user_id', $context->user->id)
                    ->first();

                if (!$githubRepository) {
                    $failedCount++;
                    $results[] = [
                        'index' => $idx,
                        'github_repository_id' => $repoId,
                        'ok' => false,
                        'error' => 'Repository nicht gefunden oder gehört dir nicht.',
                    ];
                    continue;
                }

                $created = $linkService->linkGithubRepository($githubRepository, $ticket);

                if ($created) {
                    $linkedCount++;
                } else {
                    $alreadyLinkedCount++;
                }

                $results[] = [
                    'index' => $idx,
                    'github_repository_id' => $githubRepository->id,
                    'github_repository_name' => $githubRepository->full_name,
                    'ok' => true,
                    'already_linked' => !$created,
                ];
            }

            return ToolResult::success([
                'ticket_id' => $ticket->id,
                'ticket_title' => $ticket->title,
                'results' => $results,
                'summary' => [
                    'requested' => count($repoIds),
                    'linked' => $linkedCount,
                    'already_linked' => $alreadyLinkedCount,
                    'failed' => $failedCount,
                ],
                'message' => sprintf(
                    '%d Repos verknüpft, %d bereits verknüpft, %d fehlgeschlagen.',
                    $linkedCount,
                    $alreadyLinkedCount,
                    $failedCount
                ),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Bulk-Verknüpfen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'bulk',
            'tags' => ['helpdesk', 'tickets', 'github', 'repository', 'link', 'bulk'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
