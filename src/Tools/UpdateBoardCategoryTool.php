<?php

namespace Platform\Helpdesk\Tools;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Helpdesk\Models\HelpdeskBoardCategory;
use Platform\Helpdesk\Tools\Concerns\ResolvesHelpdeskTeam;

class UpdateBoardCategoryTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesHelpdeskTeam;

    public function getName(): string
    {
        return 'helpdesk.board_categories.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /helpdesk/board_categories/{id} - Kuratiert eine Kategorie. Parameter: category_id (required), '
            .'name/description/examples/order/is_active (optional). examples ersetzt die Liste komplett.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team.'],
                'category_id' => ['type' => 'integer', 'description' => 'ID der Kategorie (ERFORDERLICH). Nutze "helpdesk.board_categories.GET".'],
                'name' => ['type' => 'string', 'description' => 'Optional: neuer Name.'],
                'description' => ['type' => 'string', 'description' => 'Optional: neue Grenze/Beschreibung.'],
                'examples' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Optional: ersetzt die Beispiel-Liste.'],
                'order' => ['type' => 'integer', 'description' => 'Optional: neue Sortierung.'],
                'is_active' => ['type' => 'boolean', 'description' => 'Optional: aktiv an/aus.'],
            ],
            'required' => ['category_id'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int) $resolved['team_id'];

            $cat = HelpdeskBoardCategory::with('helpdeskBoard')->find((int) ($arguments['category_id'] ?? 0));
            if (! $cat) {
                return ToolResult::error('NOT_FOUND', 'Kategorie nicht gefunden.');
            }
            $board = $cat->helpdeskBoard;
            if (! $board || (int) $board->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf diese Kategorie.');
            }
            Gate::forUser($context->user)->authorize('update', $board);

            $update = [];
            if (array_key_exists('name', $arguments)) {
                $name = trim((string) $arguments['name']);
                if ($name === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'name darf nicht leer sein.');
                }
                $update['name'] = $name;
            }
            if (array_key_exists('description', $arguments)) {
                $update['description'] = $arguments['description'] === '' ? null : $arguments['description'];
            }
            if (array_key_exists('examples', $arguments)) {
                $update['examples'] = is_array($arguments['examples'])
                    ? (array_values(array_filter(array_map(fn ($e) => trim((string) $e), $arguments['examples']))) ?: null)
                    : null;
            }
            if (array_key_exists('order', $arguments)) {
                $update['order'] = (int) $arguments['order'];
            }
            if (array_key_exists('is_active', $arguments)) {
                $update['is_active'] = (bool) $arguments['is_active'];
            }

            if (! empty($update)) {
                $cat->update($update);
            }

            return ToolResult::success([
                'id' => $cat->id,
                'name' => $cat->name,
                'description' => $cat->description,
                'examples' => $cat->examples,
                'order' => (int) $cat->order,
                'is_active' => $cat->is_active,
                'helpdesk_board_id' => $cat->helpdesk_board_id,
                'message' => 'Kategorie aktualisiert.',
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ToolResult::error('ACCESS_DENIED', 'Du darfst diese Kategorie nicht bearbeiten.');
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der Kategorie: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['helpdesk', 'board_categories', 'update'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
