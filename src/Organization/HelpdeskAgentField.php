<?php

namespace Platform\Helpdesk\Organization;

use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Organization\Contracts\AgentFieldSource;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Services\EntityDimensionBridge;
use Platform\Organization\Support\AgentDomain;

/**
 * Organization-Bridge: Boards und Tickets im Arbeitsfeld eines Agenten (GET /api/org/agent/field).
 *
 * Im Feld: meine offenen Tickets; die Boards, in denen sie liegen; die Boards an den Einheiten, die ich
 * verantworte — je Board seine offenen Tickets, auch die anderer. Angelegt wird hier nichts: Tickets
 * kommen von Kunden und Kanälen, nicht aus dem Takt eines Agenten.
 */
class HelpdeskAgentField implements AgentFieldSource
{
    private const MAX_BOARDS = 8;

    private const TICKETS_PER_BOARD = 8;

    public function key(): string
    {
        return 'helpdesk';
    }

    public function creatable(): array
    {
        return [];
    }

    public function create(OrganizationEntity $agent, string $type, array $input): array
    {
        throw new \InvalidArgumentException('Tickets entstehen im Helpdesk, nicht über das Arbeitsfeld.');
    }

    public function fieldFor(OrganizationEntity $agent, AgentDomain $domain): array
    {
        $userId = (int) $agent->linked_user_id;
        if ($userId < 1) {
            return [];
        }
        $today = now()->toDateString();

        $mine = HelpdeskTicket::query()->with('userInCharge:id,name')
            ->where('user_in_charge_id', $userId)->where('is_done', false)
            ->latest('updated_at')->limit(30)->get();

        $ids = array_values(array_unique(array_merge(
            $mine->pluck('helpdesk_board_id')->filter()->map(fn ($id) => (int) $id)->all(),
            EntityDimensionBridge::linksForEntities($domain->roots)->where('linkable_type', 'helpdesk_board')
                ->pluck('linkable_id')->map(fn ($id) => (int) $id)->all(),
        )));
        $boards = HelpdeskBoard::query()->whereIn('id', $ids)->latest('updated_at')->limit(self::MAX_BOARDS)->get();

        $units = [];
        foreach (EntityDimensionBridge::linksForLinkables(['helpdesk_board'], $boards->pluck('id')->all()) as $l) {
            $units[(int) $l->linkable_id] ??= $l->entity ? ['id' => (int) $l->entity_id, 'name' => (string) $l->entity->name] : null;
        }

        $objects = [];
        $seen = [];
        foreach ($boards as $b) {
            $open = HelpdeskTicket::query()->with('userInCharge:id,name')->where('helpdesk_board_id', $b->id)->where('is_done', false);
            $objects[] = [
                'type' => 'helpdesk_board', 'id' => (int) $b->id, 'title' => (string) $b->name, 'state' => 'aktiv', 'open' => true,
                'parent' => null, 'unit' => $units[(int) $b->id] ?? null, 'mine' => false,
                'open_tasks' => (clone $open)->count(),
            ];
            foreach ($open->orderByRaw('due_date is null')->orderBy('due_date')->latest('updated_at')->limit(self::TICKETS_PER_BOARD)->get() as $t) {
                $objects[] = $this->ticket($t, $userId, $today);
                $seen[(int) $t->id] = true;
            }
        }
        foreach ($mine as $t) {
            if (! isset($seen[(int) $t->id])) {
                $objects[] = $this->ticket($t, $userId, $today);
            }
        }

        return $objects;
    }

    /** @return array<string,mixed> */
    private function ticket(HelpdeskTicket $t, int $userId, string $today): array
    {
        $due = $t->due_date?->toDateString();

        return [
            'type' => 'helpdesk_ticket', 'id' => (int) $t->id, 'title' => (string) $t->title,
            'state' => (string) ($t->status instanceof \BackedEnum ? $t->status->value : ($t->status ?? 'offen')), 'open' => true,
            'parent' => $t->helpdesk_board_id ? ['type' => 'helpdesk_board', 'id' => (int) $t->helpdesk_board_id] : null,
            'mine' => (int) $t->user_in_charge_id === $userId,
            'assignee' => $t->userInCharge?->name,
            'due' => $due, 'overdue' => $due !== null && $due < $today,
            'points' => $t->story_points?->value,
            'updated_at' => $t->updated_at?->toIso8601String(),
        ];
    }
}
