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
    #[On('updateSidebar')]
    public function updateSidebar()
    {
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
            ]);
        }

        // Alle Helpdesk Boards des Teams
        $allBoards = HelpdeskBoard::query()
            ->where('team_id', $teamId)
            ->orderBy('name')
            ->get();

        // Entity-Verknüpfungen laden aus beiden Quellen
        $boardIds = $allBoards->pluck('id')->toArray();
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

        // Gruppieren: EntityType → Entity → Boards
        $entityTypeGroups = collect();
        $entityIds = array_keys($entityBoardMap);

        if (!empty($entityIds)) {
            $entities = OrganizationEntity::with('type')
                ->whereIn('id', $entityIds)
                ->get()
                ->keyBy('id');

            $groupedByType = [];
            foreach ($entityBoardMap as $entityId => $boardIdsList) {
                $entity = $entities->get($entityId);
                if (!$entity || !$entity->type) {
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
                if (!isset($groupedByType[$typeId]['entities'][$entityId])) {
                    $groupedByType[$typeId]['entities'][$entityId] = [
                        'entity_id' => $entityId,
                        'entity_name' => $entity->name,
                        'boards' => collect(),
                    ];
                }
                foreach ($boardIdsList as $bid) {
                    $board = $allBoards->firstWhere('id', $bid);
                    if ($board) {
                        $groupedByType[$typeId]['entities'][$entityId]['boards']->push($board);
                    }
                }
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

        // Unverknüpfte Boards
        $unlinkedBoards = $allBoards->filter(function ($board) use ($linkedBoardIds) {
            return !in_array($board->id, $linkedBoardIds);
        })->values();

        return view('helpdesk::livewire.sidebar', [
            'entityTypeGroups' => $entityTypeGroups,
            'unlinkedBoards' => $unlinkedBoards,
        ]);
    }
}
