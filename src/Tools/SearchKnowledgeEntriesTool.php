<?php

namespace Platform\Helpdesk\Tools;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Helpdesk\Models\HelpdeskKnowledgeEntry;
use Platform\Helpdesk\Tools\Concerns\ResolvesHelpdeskTeam;

class SearchKnowledgeEntriesTool implements ToolContract, ToolMetadataContract
{
    use ResolvesHelpdeskTeam;

    public function getName(): string
    {
        return 'helpdesk.knowledge.search';
    }

    public function getDescription(): string
    {
        return 'Durchsucht die Knowledge Base eines Boards nach Stichwörtern. Sucht in title, problem, solution und tags. Parameter: board_id (required), query (required), limit (optional).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
                'board_id' => [
                    'type' => 'integer',
                    'description' => 'Board-ID (ERFORDERLICH). Suche ist Board-scoped.',
                ],
                'query' => [
                    'type' => 'string',
                    'description' => 'Suchbegriff (ERFORDERLICH). Durchsucht title, problem, solution und tags.',
                ],
                'tags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Optional: Nur Entries mit diesen Tags (AND-Verknüpfung).',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Optional: Maximale Anzahl Ergebnisse. Default: 20, Max: 100.',
                ],
            ],
            'required' => ['board_id', 'query'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int)$resolved['team_id'];

            Gate::forUser($context->user)->authorize('viewAny', HelpdeskKnowledgeEntry::class);

            $boardId = (int)($arguments['board_id'] ?? 0);
            if ($boardId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'board_id ist erforderlich.');
            }

            $queryString = trim((string)($arguments['query'] ?? ''));
            if ($queryString === '') {
                return ToolResult::error('VALIDATION_ERROR', 'query ist erforderlich.');
            }

            $limit = min(max((int)($arguments['limit'] ?? 20), 1), 100);

            $query = HelpdeskKnowledgeEntry::query()
                ->where('team_id', $teamId)
                ->where('helpdesk_board_id', $boardId);

            // Fulltext-Suche über title, problem, solution
            $query->where(function ($q) use ($queryString) {
                $search = '%' . $queryString . '%';
                $q->where('title', 'LIKE', $search)
                    ->orWhere('problem', 'LIKE', $search)
                    ->orWhere('solution', 'LIKE', $search)
                    ->orWhereJsonContains('tags', $queryString);
            });

            // Optional: Tag-Filter (AND-Verknüpfung)
            if (!empty($arguments['tags']) && is_array($arguments['tags'])) {
                foreach ($arguments['tags'] as $tag) {
                    $tag = trim((string)$tag);
                    if ($tag !== '') {
                        $query->whereJsonContains('tags', $tag);
                    }
                }
            }

            $entries = $query->orderBy('updated_at', 'desc')
                ->limit($limit)
                ->get();

            $results = $entries->map(fn (HelpdeskKnowledgeEntry $e) => [
                'id' => $e->id,
                'uuid' => $e->uuid,
                'title' => $e->title,
                'problem' => $e->problem,
                'solution' => $e->solution,
                'tags' => $e->tags,
                'board_id' => $e->helpdesk_board_id,
                'source_ticket_id' => $e->source_ticket_id,
                'created_at' => $e->created_at?->toISOString(),
                'updated_at' => $e->updated_at?->toISOString(),
            ])->toArray();

            return ToolResult::success([
                'data' => $results,
                'count' => count($results),
                'query' => $queryString,
                'board_id' => $boardId,
                'team_id' => $teamId,
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ToolResult::error('ACCESS_DENIED', 'Du hast keine Berechtigung, Knowledge Entries zu durchsuchen.');
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler bei der Knowledge-Suche: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['helpdesk', 'knowledge', 'search'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
