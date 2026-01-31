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
 * Tool zum Entfernen der Verknüpfung eines GitHub Repositories mit einem Ticket
 */
class UnlinkTicketGithubRepositoryTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHelpdeskTeam;

    public function getName(): string
    {
        return 'helpdesk.ticket_github_repositories.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /helpdesk/tickets/{ticket_id}/github_repositories/{github_repository_id} - Entfernt die Verknüpfung eines GitHub Repositories mit einem Ticket. Parameter: ticket_id (required, integer) - Ticket-ID. github_repository_id (required, integer) - GitHub Repository-ID.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'ticket_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Tickets (ERFORDERLICH).'
                ],
                'github_repository_id' => [
                    'type' => 'integer',
                    'description' => 'ID des GitHub Repositories (ERFORDERLICH).'
                ],
            ],
            'required' => ['ticket_id', 'github_repository_id'],
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

            // Policy prüfen - User muss Ticket bearbeiten dürfen
            try {
                Gate::forUser($context->user)->authorize('update', $ticket);
            } catch (AuthorizationException $e) {
                return ToolResult::error('ACCESS_DENIED', 'Du darfst dieses Ticket nicht bearbeiten.');
            }

            // GitHub Repository ID validieren
            $githubRepositoryId = (int)($arguments['github_repository_id'] ?? 0);
            if ($githubRepositoryId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'github_repository_id ist erforderlich.');
            }

            // GitHub Repository finden (keine User-Prüfung beim Unlink - Ticket-Owner darf entfernen)
            $githubRepository = IntegrationsGithubRepository::find($githubRepositoryId);

            if (!$githubRepository) {
                return ToolResult::error('REPOSITORY_NOT_FOUND', 'Das GitHub Repository wurde nicht gefunden.');
            }

            // Verknüpfung entfernen
            $linkService = app(IntegrationAccountLinkService::class);
            $deleted = $linkService->unlinkGithubRepository($githubRepository, $ticket);

            if (!$deleted) {
                return ToolResult::error('NOT_LINKED', 'Das GitHub Repository war nicht mit diesem Ticket verknüpft.');
            }

            return ToolResult::success([
                'ticket_id' => $ticket->id,
                'ticket_title' => $ticket->title,
                'github_repository_id' => $githubRepository->id,
                'github_repository_name' => $githubRepository->full_name,
                'message' => 'Verknüpfung von GitHub Repository "' . $githubRepository->full_name . '" mit Ticket entfernt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Entfernen der Verknüpfung: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['helpdesk', 'tickets', 'github', 'repository', 'unlink', 'delete'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
