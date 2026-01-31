<?php

namespace Platform\Helpdesk\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Integrations\Models\IntegrationsGithubRepository;

/**
 * Tool zum Auflisten von GitHub Repositories des aktuellen Users
 */
class ListGithubRepositoriesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;

    public function getName(): string
    {
        return 'helpdesk.github_repositories.GET';
    }

    public function getDescription(): string
    {
        return 'GET /helpdesk/github_repositories - Listet alle GitHub Repositories des aktuellen Users auf. REST-Parameter: search (optional, string) - Suchbegriff für full_name, name, owner. limit/offset (optional) - Pagination.';
    }

    public function getSchema(): array
    {
        return $this->getStandardGetSchema();
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            // GitHub Repositories des aktuellen Users laden (keine Team-Prüfung nötig, da User-owned)
            $query = IntegrationsGithubRepository::where('user_id', $context->user->id);

            // Standard-Suche anwenden
            $this->applyStandardSearch($query, $arguments, ['full_name', 'name', 'owner', 'description']);

            // Standard-Sortierung anwenden
            $this->applyStandardSort($query, $arguments, [
                'full_name', 'name', 'owner', 'language', 'created_at', 'updated_at'
            ], 'full_name', 'asc');

            // Standard-Pagination anwenden
            $this->applyStandardPagination($query, $arguments);

            $repositories = $query->get();

            // Repositories formatieren
            $reposList = $repositories->map(function ($repo) {
                return [
                    'id' => $repo->id,
                    'uuid' => $repo->uuid,
                    'full_name' => $repo->full_name,
                    'name' => $repo->name,
                    'owner' => $repo->owner,
                    'description' => $repo->description,
                    'url' => $repo->url,
                    'language' => $repo->language,
                    'is_private' => $repo->is_private,
                    'default_branch' => $repo->default_branch,
                    'stars_count' => $repo->stars_count,
                    'forks_count' => $repo->forks_count,
                ];
            })->values()->toArray();

            return ToolResult::success([
                'github_repositories' => $reposList,
                'count' => count($reposList),
                'message' => count($reposList) > 0
                    ? count($reposList) . ' GitHub Repository/Repositories gefunden.'
                    : 'Keine GitHub Repositories gefunden. Verbinde dein GitHub-Konto in den Einstellungen.'
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der GitHub Repositories: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['helpdesk', 'github', 'repositories', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
