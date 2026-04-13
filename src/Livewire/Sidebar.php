<?php

namespace Platform\Helpdesk\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Models\HelpdeskBoardSlot;
use Platform\Organization\Models\OrganizationContext;
use Platform\Organization\Models\OrganizationEntityLink;
use Platform\Organization\Models\OrganizationEntity;
use Livewire\Attributes\On;

class Sidebar extends Component
{
    public bool $showAllBoards = false;

    public function mount()
    {
        $this->showAllBoards = false;
    }

    #[On('updateSidebar')]
    public function updateSidebar()
    {
    }

    public function toggleShowAllBoards()
    {
        $this->showAllBoards = !$this->showAllBoards;
    }

    #[On('create-helpdesk-board')]
    public function createHelpdeskBoard()
    {
        $user = Auth::user();
        $teamId = $user->currentTeam->id;

        // 1. Neues Helpdesk Board anlegen
        $board = new HelpdeskBoard();
        $board->name = 'Neues Helpdesk Board';
        $board->user_id = $user->id;
        $board->team_id = $teamId;
        $board->order = HelpdeskBoard::where('team_id', $teamId)->max('order') + 1;
        $board->save();

        // 2. Standard-Slots anlegen: Offen, In Bearbeitung, Wartend, Gelöst
        $defaultSlots = ['Offen', 'In Bearbeitung', 'Wartend', 'Gelöst'];
        foreach ($defaultSlots as $index => $name) {
            HelpdeskBoardSlot::create([
                'helpdesk_board_id' => $board->id,
                'name' => $name,
                'order' => $index + 1,
            ]);
        }

        return redirect()->route('helpdesk.boards.show', ['helpdeskBoard' => $board->id]);
    }

    public function render()
    {
        $user = auth()->user();
        $teamId = $user?->currentTeam->id ?? null;

        if (!$user || !$teamId) {
            return view('helpdesk::livewire.sidebar', [
                'entityTypeGroups' => collect(),
                'unlinkedBoards' => collect(),
                'hasMoreBoards' => false,
            ]);
        }

        // 1. Boards laden (mit User-Filter wie Planner)
        $boardsWithUserTickets = HelpdeskBoard::query()
            ->where('team_id', $teamId)
            ->where(function ($query) use ($user) {
                $query->whereHas('tickets', function ($q) use ($user) {
                    $q->where('user_in_charge_id', $user->id)
                      ->where('is_done', false);
                })
                ->orWhere('user_id', $user->id);
            })
            ->orderBy('name')
            ->get();

        $allBoards = HelpdeskBoard::query()
            ->where('team_id', $teamId)
            ->orderBy('name')
            ->get();

        $boardsToShow = $this->showAllBoards
            ? $allBoards
            : $boardsWithUserTickets;

        $hasMoreBoards = $allBoards->count() > $boardsWithUserTickets->count();

        // 2. Entity-Verknüpfungen laden aus beiden Quellen
        $boardIds = $boardsToShow->pluck('id')->toArray();
        $entityBoardMap = [];
        $linkedBoardIds = [];

        // a) OrganizationContext (primäre Quelle – UI)
        $contextMorphTypes = ['helpdesk_board', HelpdeskBoard::class];
        $contexts = OrganizationContext::query()
            ->whereIn('contextable_type', $contextMorphTypes)
            ->whereIn('contextable_id', $boardIds)
            ->where('is_active', true)
            ->with(['organizationEntity.type'])
            ->get();

        foreach ($contexts as $ctx) {
            $entityId = $ctx->organization_entity_id;
            $boardId = $ctx->contextable_id;
            if ($entityId) {
                $entityBoardMap[$entityId][] = $boardId;
                $linkedBoardIds[] = $boardId;
            }
        }

        // b) OrganizationEntityLink (sekundäre Quelle – DimensionLinker / LLM Tools)
        $entityLinks = OrganizationEntityLink::query()
            ->whereIn('linkable_type', $contextMorphTypes)
            ->whereIn('linkable_id', $boardIds)
            ->with(['entity.type'])
            ->get();

        foreach ($entityLinks as $link) {
            $entityId = $link->entity_id;
            $boardId = $link->linkable_id;
            $entityBoardMap[$entityId][] = $boardId;
            $linkedBoardIds[] = $boardId;
        }

        // Deduplizieren
        foreach ($entityBoardMap as $entityId => $bids) {
            $entityBoardMap[$entityId] = array_unique($bids);
        }
        $linkedBoardIds = array_unique($linkedBoardIds);

        // 2c. Aufwärts-Traversierung: Ancestors ins Entity-Set aufnehmen (für Baum-Darstellung)
        $directEntityIds = array_keys($entityBoardMap);
        if (!empty($directEntityIds)) {
            $directEntities = OrganizationEntity::with(['allParents.type'])
                ->whereIn('id', $directEntityIds)
                ->get()
                ->keyBy('id');

            foreach ($directEntities as $entityId => $entity) {
                $ancestor = $entity->allParents;
                while ($ancestor) {
                    if (!isset($entityBoardMap[$ancestor->id])) {
                        $entityBoardMap[$ancestor->id] = [];
                    }
                    $ancestor = $ancestor->allParents;
                }
            }
        }

        // 3. Gruppieren: EntityType → Entity-Baum → Boards
        $entityTypeGroups = collect();
        $entityIds = array_keys($entityBoardMap);

        if (!empty($entityIds)) {
            $entities = OrganizationEntity::with('type')
                ->whereIn('id', $entityIds)
                ->get()
                ->keyBy('id');

            // Eltern-Kind-Beziehungen innerhalb unseres Entity-Sets aufbauen
            $entityChildrenMap = [];
            $rootEntityIds = [];

            foreach ($entities as $entity) {
                $parentId = $entity->parent_entity_id;
                if ($parentId && $entities->has($parentId)) {
                    $entityChildrenMap[$parentId][] = $entity->id;
                } else {
                    $rootEntityIds[] = $entity->id;
                }
            }

            // Rekursiver Baum-Builder
            $buildTree = function (int $entityId) use (&$buildTree, $entities, $entityChildrenMap, $entityBoardMap, $boardsToShow): ?array {
                $entity = $entities->get($entityId);
                if (!$entity) {
                    return null;
                }

                $childIds = $entityChildrenMap[$entityId] ?? [];
                $childNodes = collect($childIds)
                    ->map(fn ($childId) => $buildTree($childId))
                    ->filter();

                // Kinder nach EntityType gruppieren
                $childrenByType = $childNodes
                    ->groupBy(fn ($child) => $child['type_id'])
                    ->map(function ($group) use ($entities) {
                        $firstChild = $group->first();
                        $typeEntity = $entities->get($firstChild['entity_id']);
                        $type = $typeEntity?->type;

                        return [
                            'type_id' => $firstChild['type_id'],
                            'type_name' => $type?->name ?? 'Sonstige',
                            'type_icon' => $type?->icon ?? null,
                            'sort_order' => $type?->sort_order ?? 999,
                            'children' => $group->sortBy('entity_name')->values(),
                        ];
                    })
                    ->sortBy('sort_order')
                    ->values();

                $boards = collect($entityBoardMap[$entityId] ?? [])
                    ->map(fn ($bid) => $boardsToShow->firstWhere('id', $bid))
                    ->filter()
                    ->values();

                // Gesamtzahl Boards (eigene + aller Kinder)
                $totalBoards = $boards->count();
                foreach ($childNodes as $child) {
                    $totalBoards += $child['total_boards'];
                }

                // Entity nur anzeigen wenn sie Boards hat oder Kinder mit Boards
                if ($totalBoards === 0) {
                    return null;
                }

                return [
                    'entity_id' => $entityId,
                    'entity_name' => $entity->name,
                    'type_id' => $entity->type?->id,
                    'boards' => $boards,
                    'children_by_type' => $childrenByType,
                    'total_boards' => $totalBoards,
                ];
            };

            // Root-Entities nach Typ gruppieren
            $groupedByType = [];
            foreach ($rootEntityIds as $entityId) {
                $entity = $entities->get($entityId);
                if (!$entity || !$entity->type) {
                    continue;
                }

                $tree = $buildTree($entityId);
                if (!$tree) {
                    continue;
                }

                $typeId = $entity->type->id;
                if (!isset($groupedByType[$typeId])) {
                    $groupedByType[$typeId] = [
                        'type_id' => $typeId,
                        'type_name' => $entity->type->name,
                        'type_icon' => $entity->type->icon,
                        'sort_order' => $entity->type->sort_order ?? 999,
                        'entities' => [],
                    ];
                }
                $groupedByType[$typeId]['entities'][] = $tree;
            }

            $entityTypeGroups = collect($groupedByType)
                ->sortBy('sort_order')
                ->map(function ($group) {
                    $group['entities'] = collect($group['entities'])
                        ->sortBy('entity_name')
                        ->values();
                    return $group;
                })
                ->values();
        }

        // 4. Unverknüpfte Boards
        $unlinkedBoards = $boardsToShow->filter(function ($board) use ($linkedBoardIds) {
            return !in_array($board->id, $linkedBoardIds);
        })->values();

        return view('helpdesk::livewire.sidebar', [
            'entityTypeGroups' => $entityTypeGroups,
            'unlinkedBoards' => $unlinkedBoards,
            'hasMoreBoards' => $hasMoreBoards,
        ]);
    }
}
