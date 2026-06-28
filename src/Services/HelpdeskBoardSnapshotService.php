<?php

namespace Platform\Helpdesk\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Platform\Core\Health\Services\ConfidenceCalculator;
use Platform\Core\Health\Services\HealthCompositor;
use Platform\Core\Models\User;
use Platform\Helpdesk\Enums\TicketEscalationLevel;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Models\HelpdeskBoardSnapshot;
use Platform\Helpdesk\Models\HelpdeskTicket;

/**
 * Erstellt einen Tages-Snapshot fuer ein HelpdeskBoard.
 *
 * Achsen (Gewichte 30/30/25/15):
 *   - backlog    : Druck durch offene Tickets, Alter, unzugewiesene
 *   - sla        : SLA-Einhaltung (nur wenn Board eine SLA hat)
 *   - escalation : Anzahl eskalierter Tickets, kritische, lifetime-Sum
 *   - workload   : Verteilung offener Tickets pro Agent
 *
 * Confidence-Datenebenen:
 *   - has_sla, has_tickets, has_assignees, has_service_hours
 *
 * Idempotent: max 1 Snapshot pro Board pro Tag — existiert er, wird er ueberschrieben.
 */
class HelpdeskBoardSnapshotService
{
    public function __construct(
        protected HealthCompositor $compositor,
        protected ConfidenceCalculator $confidence,
    ) {}

    public function snapshot(HelpdeskBoard $board, string $trigger = 'cron'): HelpdeskBoardSnapshot
    {
        return DB::transaction(function () use ($board, $trigger) {
            $now = now();
            $today = $now->toDateString();

            $board->loadMissing([
                'tickets',
                'slots',
                'sla',
                'serviceHours',
            ]);

            $existing = HelpdeskBoardSnapshot::where('helpdesk_board_id', $board->id)
                ->whereDate('taken_on', $today)
                ->first();

            if ($existing) {
                $existing->topTickets()->delete();
                $existing->people()->delete();
                $existing->slots()->delete();
            }

            $payload = $this->computeScalars($board, $now);

            $prev = HelpdeskBoardSnapshot::where('helpdesk_board_id', $board->id)
                ->whereDate('taken_on', '<', $today)
                ->orderByDesc('taken_on')
                ->first();

            if ($prev) {
                $payload['prev_snapshot_id'] = $prev->id;
                $payload['delta_health_score'] = $this->safeDelta($payload['health_score'], $prev->health_score);
            }

            $payload['trigger'] = $trigger;
            $payload['taken_at'] = $now;
            $payload['taken_on'] = $today;
            $payload['helpdesk_board_id'] = $board->id;
            $payload['team_id'] = $board->team_id;

            if ($existing) {
                $existing->update($payload);
                $snapshot = $existing->fresh();
            } else {
                $snapshot = HelpdeskBoardSnapshot::create($payload);
            }

            $this->writeTopTickets($snapshot, $board, $now);
            $this->writePeople($snapshot, $board);
            $this->writeSlots($snapshot, $board);

            return $snapshot;
        });
    }

    private function safeDelta(?int $current, ?int $prev): ?int
    {
        if ($current === null || $prev === null) {
            return null;
        }
        return $current - $prev;
    }

    private function computeScalars(HelpdeskBoard $board, Carbon $now): array
    {
        $tickets = $board->tickets;
        $openTickets = $tickets->where('is_done', false);
        $doneTickets = $tickets->where('is_done', true);
        $overdueTickets = $openTickets->filter(fn ($t) => $t->due_date && $t->due_date->isPast());
        $withDueDate = $tickets->filter(fn ($t) => $t->due_date !== null);

        // Escalation
        $escalatedTickets = $openTickets->filter(
            fn ($t) => $t->escalation_level && $t->escalation_level !== TicketEscalationLevel::NONE
        );
        $criticalTickets = $openTickets->filter(
            fn ($t) => $t->escalation_level && in_array($t->escalation_level, [TicketEscalationLevel::CRITICAL, TicketEscalationLevel::URGENT], true)
        );
        $escalationsLifetime = (int) $tickets->sum(fn ($t) => $t->escalation_count ?? 0);

        // Story Points
        $spTotal = $tickets->sum(fn ($t) => $t->story_points?->points() ?? 0);
        $spOpen = $openTickets->sum(fn ($t) => $t->story_points?->points() ?? 0);
        $spDone = $doneTickets->sum(fn ($t) => $t->story_points?->points() ?? 0);

        // SLA
        $sla = $board->sla;
        $hasSla = $sla !== null;
        $slaResolution = $hasSla ? (int) $sla->resolution_time_hours : null;
        $slaResponse = $hasSla ? (int) $sla->response_time_hours : null;
        $breaching = 0;
        if ($hasSla && $slaResolution) {
            $breaching = $openTickets->filter(function ($t) use ($slaResolution, $now) {
                if (! $t->created_at) return false;
                $age = $t->created_at->diffInHours($now);
                return $age > $slaResolution;
            })->count();
        }

        // Workload
        $byUser = $openTickets
            ->whereNotNull('user_in_charge_id')
            ->groupBy('user_in_charge_id');
        $activeUsers = $byUser->count();
        $unassigned = $openTickets->whereNull('user_in_charge_id')->count();

        // Oldest open ticket age (in Tagen)
        $oldestOpenDays = 0;
        if ($openTickets->isNotEmpty()) {
            $oldestCreated = $openTickets->min('created_at');
            if ($oldestCreated) {
                $oldestOpenDays = (int) Carbon::parse($oldestCreated)->startOfDay()->diffInDays($now->copy()->startOfDay());
            }
        }

        // ── Achsen ──
        $axes = [];

        // Backlog (immer berechnet sobald Tickets da)
        if ($tickets->isNotEmpty()) {
            $axes['backlog'] = $this->backlogScore($openTickets->count(), $oldestOpenDays, $unassigned);
        }

        // SLA — nur wenn Board eine SLA hat
        if ($hasSla) {
            $axes['sla'] = $this->slaScore($breaching, $openTickets->count());
        }

        // Escalation — immer wenn Tickets da sind
        if ($tickets->isNotEmpty()) {
            $axes['escalation'] = $this->escalationScore(
                $escalatedTickets->count(),
                $criticalTickets->count(),
                $escalationsLifetime,
            );
        }

        // Workload — nur wenn aktive Agents da
        if ($activeUsers > 0) {
            $axes['workload'] = $this->workloadScore($byUser, $openTickets->count(), $activeUsers);
        }

        // ── Confidence ──
        [$confScore, $confReason] = array_values($this->confidence->compute([
            'sla' => $hasSla,
            'tickets' => $tickets->isNotEmpty(),
            'assignees' => $activeUsers > 0,
            'service_hours' => $board->serviceHours->isNotEmpty(),
        ]));

        // ── Composite ──
        $weights = ['backlog' => 30, 'sla' => 30, 'escalation' => 25, 'workload' => 15];
        $composed = $this->compositor->compose($axes, $weights, $confScore);

        return [
            // Composite results
            'health_score' => $composed['score'],
            'health_color' => $composed['color'],
            'worst_axis' => $composed['worst_axis'],
            'axis_scores' => empty($composed['axis_scores']) ? null : $composed['axis_scores'],
            // Confidence
            'confidence_score' => $confScore,
            'confidence_reason' => $confReason,
            // Frozen Context
            'frozen_context' => [
                'name' => $board->name,
                'description' => $board->description,
                'sla_id' => $board->helpdesk_board_sla_id,
            ],
            // Ticket counts
            'tickets_total' => $tickets->count(),
            'tickets_open' => $openTickets->count(),
            'tickets_done' => $doneTickets->count(),
            'tickets_overdue' => $overdueTickets->count(),
            'tickets_with_due_date' => $withDueDate->count(),
            // Escalation
            'tickets_escalated' => $escalatedTickets->count(),
            'tickets_critical' => $criticalTickets->count(),
            'escalations_total_lifetime' => $escalationsLifetime,
            // SP
            'story_points_total' => $spTotal,
            'story_points_open' => $spOpen,
            'story_points_done' => $spDone,
            // SLA
            'has_sla' => $hasSla,
            'sla_response_hours' => $slaResponse,
            'sla_resolution_hours' => $slaResolution,
            'tickets_breaching_resolution' => $breaching,
            // Workload
            'active_users_count' => $activeUsers,
            'unassigned_tickets' => $unassigned,
            // Movement
            'last_movement_at' => $this->computeLastMovement($board),
        ];
    }

    private function backlogScore(int $open, int $oldestOpenDays, int $unassigned): int
    {
        $score = 100;

        // Druck durch Anzahl offener Tickets
        if ($open > 20)       $score -= 30;
        elseif ($open > 10)   $score -= 20;
        elseif ($open > 5)    $score -= 10;

        // Alter des aeltesten offenen Tickets
        if ($oldestOpenDays > 60)     $score -= 30;
        elseif ($oldestOpenDays > 30) $score -= 20;
        elseif ($oldestOpenDays > 14) $score -= 10;

        // Unzugewiesene Tickets (Anteil)
        if ($open > 0 && $unassigned / $open > 0.3) {
            $score -= 15;
        }

        return max(0, $score);
    }

    private function slaScore(int $breaching, int $open): int
    {
        $score = 100;
        $score -= min(60, $breaching * 15);
        return max(0, $score);
    }

    private function escalationScore(int $escalated, int $critical, int $lifetime): int
    {
        $score = 100;
        $score -= min(40, $escalated * 5);
        $score -= min(30, $critical * 10);
        if ($lifetime > 20) $score -= 10;
        return max(0, $score);
    }

    private function workloadScore(Collection $byUser, int $totalOpen, int $activeUsers): int
    {
        if ($activeUsers <= 1) return 100; // einzelner Agent: keine Verteilung relevant
        $counts = $byUser->map(fn ($tickets) => $tickets->count());
        $max = $counts->max();
        $avg = $totalOpen / $activeUsers;
        if ($avg == 0) return 100;
        $ratio = $max / $avg;

        if ($ratio <= 1.5) return 100;
        if ($ratio <= 2.5) return 70;
        return 40;
    }

    private function computeLastMovement(HelpdeskBoard $board): ?Carbon
    {
        $candidates = [];

        $lastTicketUpdate = HelpdeskTicket::where('helpdesk_board_id', $board->id)->max('updated_at');
        if ($lastTicketUpdate) {
            $candidates[] = Carbon::parse($lastTicketUpdate);
        }

        $lastEscalation = DB::table('helpdesk_ticket_escalations as e')
            ->join('helpdesk_tickets as t', 't.id', '=', 'e.helpdesk_ticket_id')
            ->where('t.helpdesk_board_id', $board->id)
            ->max('e.escalated_at');
        if ($lastEscalation) {
            $candidates[] = Carbon::parse($lastEscalation);
        }

        $ticketIds = HelpdeskTicket::where('helpdesk_board_id', $board->id)->pluck('id');
        if ($ticketIds->isNotEmpty()) {
            $lastTimeEntry = DB::table('organization_time_entries')
                ->where('context_type', HelpdeskTicket::class)
                ->whereIn('context_id', $ticketIds)
                ->whereNull('deleted_at')
                ->max('created_at');
            if ($lastTimeEntry) {
                $candidates[] = Carbon::parse($lastTimeEntry);
            }
        }

        if (empty($candidates)) return null;
        return collect($candidates)->max();
    }

    private function writeTopTickets(HelpdeskBoardSnapshot $snapshot, HelpdeskBoard $board, Carbon $now): void
    {
        // Top-5 aelteste OFFENE Tickets — Eskalation > overdue > Alter
        $sortedOpen = $board->tickets
            ->where('is_done', false)
            ->sort(function ($a, $b) use ($now) {
                // Eskalierte zuerst
                $aEsc = $a->escalation_level && $a->escalation_level !== TicketEscalationLevel::NONE ? 0 : 1;
                $bEsc = $b->escalation_level && $b->escalation_level !== TicketEscalationLevel::NONE ? 0 : 1;
                if ($aEsc !== $bEsc) return $aEsc <=> $bEsc;
                // Dann ueberfaellige
                $aOver = ($a->due_date && $a->due_date->isPast()) ? 0 : 1;
                $bOver = ($b->due_date && $b->due_date->isPast()) ? 0 : 1;
                if ($aOver !== $bOver) return $aOver <=> $bOver;
                // Dann aelteste
                return $a->created_at <=> $b->created_at;
            })
            ->take(5)
            ->values();

        $userNames = $sortedOpen->pluck('user_in_charge_id')->filter()->unique()->values();
        $userMap = $userNames->isNotEmpty()
            ? User::whereIn('id', $userNames)->pluck('name', 'id')
            : collect();

        foreach ($sortedOpen as $idx => $t) {
            $snapshot->topTickets()->create([
                'ticket_id' => $t->id,
                'ticket_uuid' => $t->uuid,
                'ticket_title' => mb_substr((string) ($t->title ?? '—'), 0, 500),
                'due_date' => $t->due_date,
                'ticket_created_at' => $t->created_at,
                'is_overdue' => $t->due_date && $t->due_date->isPast(),
                'postpone_count' => 0, // helpdesk hat keine postpone_count auf tickets
                'priority' => $t->priority?->value,
                'escalation_level' => $t->escalation_level?->value,
                'escalation_count' => (int) ($t->escalation_count ?? 0),
                'story_points' => $t->story_points?->value,
                'user_in_charge_id' => $t->user_in_charge_id,
                'user_in_charge_name' => $userMap[$t->user_in_charge_id] ?? null,
                'rank' => $idx + 1,
            ]);
        }
    }

    private function writePeople(HelpdeskBoardSnapshot $snapshot, HelpdeskBoard $board): void
    {
        $byUser = $board->tickets
            ->whereNotNull('user_in_charge_id')
            ->groupBy('user_in_charge_id');

        if ($byUser->isEmpty()) return;

        $userIds = $byUser->keys()->all();
        $userNames = User::whereIn('id', $userIds)->pluck('name', 'id');

        foreach ($byUser as $userId => $userTickets) {
            $openT = $userTickets->where('is_done', false);
            if ($openT->isEmpty()) continue; // nur User mit offenen Tickets

            $doneT = $userTickets->where('is_done', true);
            $overdue = $openT->filter(fn ($t) => $t->due_date && $t->due_date->isPast())->count();
            $escalated = $openT->filter(
                fn ($t) => $t->escalation_level && $t->escalation_level !== TicketEscalationLevel::NONE
            )->count();

            $snapshot->people()->create([
                'user_id' => $userId,
                'user_name' => mb_substr((string) ($userNames[$userId] ?? ('User #' . $userId)), 0, 255),
                'open_tickets' => $openT->count(),
                'done_tickets' => $doneT->count(),
                'overdue_tickets' => $overdue,
                'escalated_tickets' => $escalated,
                'sp_open' => $openT->sum(fn ($t) => $t->story_points?->points() ?? 0),
                'sp_done' => $doneT->sum(fn ($t) => $t->story_points?->points() ?? 0),
            ]);
        }
    }

    private function writeSlots(HelpdeskBoardSnapshot $snapshot, HelpdeskBoard $board): void
    {
        foreach ($board->slots as $slot) {
            $slotTickets = $board->tickets->where('helpdesk_board_slot_id', $slot->id);
            $snapshot->slots()->create([
                'slot_id' => $slot->id,
                'slot_name' => mb_substr((string) ($slot->name ?? '—'), 0, 255),
                'slot_order' => (int) ($slot->order ?? 0),
                'open_tickets' => $slotTickets->where('is_done', false)->count(),
                'done_tickets' => $slotTickets->where('is_done', true)->count(),
                'total_tickets' => $slotTickets->count(),
            ]);
        }
    }
}
