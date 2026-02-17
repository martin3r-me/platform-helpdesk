<?php

namespace Platform\Helpdesk\Tools;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Helpdesk\Models\HelpdeskKnowledgeEntry;
use Platform\Helpdesk\Tools\Concerns\ResolvesHelpdeskTeam;

class ListKnowledgeEntriesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesHelpdeskTeam;

    public function getName(): string
    {
        return 'helpdesk.knowledge.GET';
    }

    public function getDescription(): string
    {
        return 'GET /helpdesk/knowledge - Listet Knowledge Entries (Wissensdatenbank). Parameter: board_id (empfohlen), team_id (optional), filters/search/sort/limit/offset (optional).';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                    ],
                    'board_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Nur Entries dieses Boards anzeigen (empfohlen).',
                    ],
                    'source_ticket_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Nur Entries mit dieser Quell-Ticket-ID.',
                    ],
                ],
            ]
        );
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

            $query = HelpdeskKnowledgeEntry::query()->where('team_id', $teamId);

            if (!empty($arguments['board_id'])) {
                $query->where('helpdesk_board_id', (int)$arguments['board_id']);
            }

            if (!empty($arguments['source_ticket_id'])) {
                $query->where('source_ticket_id', (int)$arguments['source_ticket_id']);
            }

            $this->applyStandardFilters($query, $arguments, ['title', 'helpdesk_board_id', 'source_ticket_id', 'created_at']);
            $this->applyStandardSearch($query, $arguments, ['title', 'problem', 'solution']);
            $this->applyStandardSort($query, $arguments, ['title', 'created_at', 'updated_at'], 'created_at', 'desc');

            $result = $this->applyStandardPaginationResult($query, $arguments);

            $entries = collect($result['data'])
                ->map(fn (HelpdeskKnowledgeEntry $e) => [
                    'id' => $e->id,
                    'uuid' => $e->uuid,
                    'title' => $e->title,
                    'problem' => $e->problem,
                    'solution' => $e->solution,
                    'tags' => $e->tags,
                    'board_id' => $e->helpdesk_board_id,
                    'source_ticket_id' => $e->source_ticket_id,
                    'team_id' => $e->team_id,
                    'created_at' => $e->created_at?->toISOString(),
                    'updated_at' => $e->updated_at?->toISOString(),
                ])->toArray();

            return ToolResult::success([
                'data' => $entries,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $teamId,
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ToolResult::error('ACCESS_DENIED', 'Du hast keine Berechtigung, Knowledge Entries zu sehen.');
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Knowledge Entries: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['helpdesk', 'knowledge', 'list'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
