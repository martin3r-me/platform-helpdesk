<?php

namespace Platform\Helpdesk\Tools;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Tools\Concerns\ResolvesHelpdeskTeam;

class ListBoardsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesHelpdeskTeam;

    public function getName(): string
    {
        return 'helpdesk.boards.GET';
    }

    public function getDescription(): string
    {
        return 'GET /helpdesk/boards - Listet Helpdesk Boards. Parameter: team_id (optional), filters/search/sort/limit/offset (optional).';
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

            Gate::forUser($context->user)->authorize('viewAny', HelpdeskBoard::class);

            $query = HelpdeskBoard::query()->where('team_id', $teamId);

            $this->applyStandardFilters($query, $arguments, ['name', 'order', 'created_at']);
            $this->applyStandardSearch($query, $arguments, ['name', 'description']);
            $this->applyStandardSort($query, $arguments, ['order', 'name', 'created_at'], 'order', 'asc');

            $result = $this->applyStandardPaginationResult($query, $arguments);

            $boards = collect($result['data'])
                ->filter(fn (HelpdeskBoard $b) => Gate::forUser($context->user)->allows('view', $b))
                ->values()
                ->map(fn (HelpdeskBoard $b) => [
                    'id' => $b->id,
                    'uuid' => $b->uuid,
                    'name' => $b->name,
                    'description' => $b->description,
                    'order' => (int)$b->order,
                    'team_id' => $b->team_id,
                    'user_id' => $b->user_id,
                    'created_at' => $b->created_at?->toISOString(),
                    'updated_at' => $b->updated_at?->toISOString(),
                ])->toArray();

            return ToolResult::success([
                'data' => $boards,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $teamId,
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ToolResult::error('ACCESS_DENIED', 'Du hast keine Berechtigung, Boards zu sehen.');
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Boards: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['helpdesk', 'boards', 'list'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}


