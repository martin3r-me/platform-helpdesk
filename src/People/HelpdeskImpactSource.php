<?php

namespace Platform\Helpdesk\People;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\Relation;
use Platform\Helpdesk\Enums\TicketStoryPoints;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\People\Support\ImpactSourceBase;

/**
 * Wirkungs-Quelle des Helpdesk-Moduls für people (Registry-Muster, lose gekoppelt).
 *
 * Liefert nur die WORK-Facette: im Zeitraum gelöste Tickets je Mitarbeiter, zugeordnet über
 * user_in_charge_id (Mensch wie Agent). „Gelöst" = is_done mit done_at im Zeitraum; wo ein Abschluss
 * done_at nicht gesetzt hat, gilt updated_at als Abschluss-Zeitpunkt.
 *
 * Wieder geöffnet hält das Ticket nicht fest (is_done=false, keine Spur) — reopened ist immer false.
 *
 * Registrierung in HelpdeskServiceProvider::boot() nur, wenn people geladen ist (diese Klasse erbt
 * von einer people-Klasse und darf ohne people nie instanziiert werden).
 */
class HelpdeskImpactSource extends ImpactSourceBase
{
    public function key(): string
    {
        return 'helpdesk';
    }

    public function work(int $teamId, array $subjects, CarbonInterface $from, CarbonInterface $to): array
    {
        $employeeByUser = [];
        foreach ($subjects as $subject) {
            if (! empty($subject['user_id'])) {
                $employeeByUser[(int) $subject['user_id']] = (int) $subject['employee_id'];
            }
        }
        if (empty($employeeByUser)) {
            return [];
        }

        $tickets = HelpdeskTicket::query()
            ->where('team_id', $teamId)
            ->whereIn('user_in_charge_id', array_keys($employeeByUser))
            ->where('is_done', true)
            ->where(fn ($q) => $q->whereBetween('done_at', [$from, $to])
                ->orWhere(fn ($q) => $q->whereNull('done_at')->whereBetween('updated_at', [$from, $to])))
            ->get();
        if ($tickets->isEmpty()) {
            return [];
        }

        $minutes = $this->bookedMinutes($tickets->pluck('id')->all());
        $ticketAlias = $this->morphAlias(HelpdeskTicket::class, 'helpdesk_ticket');
        $boardAlias = $this->morphAlias(HelpdeskBoard::class, 'helpdesk_board');

        $rows = [];
        foreach ($tickets as $ticket) {
            $userId = (int) $ticket->user_in_charge_id;
            $employeeId = $employeeByUser[$userId] ?? null;
            if ($employeeId === null) {
                continue;
            }
            $contextRefs = [$ticketAlias.':'.$ticket->id];
            if ($ticket->helpdesk_board_id) {
                $contextRefs[] = $boardAlias.':'.$ticket->helpdesk_board_id;
            }

            $rows[] = [
                'employee_id' => $employeeId,
                'ref' => $ticketAlias.':'.$ticket->id,
                'title' => $ticket->title,
                'points' => $ticket->story_points instanceof TicketStoryPoints ? $ticket->story_points->points() : null,
                'accepted_at' => $ticket->done_at ?? $ticket->updated_at,
                'documented' => $this->isDocumented($ticket),
                'reopened' => false,
                'minutes' => $minutes[$ticket->id][$userId] ?? null,
                'context_refs' => $contextRefs,
                'self_initiated' => (int) $ticket->user_id === $userId,
            ];
        }

        return $rows;
    }

    /**
     * „Dokumentiert" = nachvollziehbar, wie das Anliegen gelöst wurde: ein Lösungsweg (resolution),
     * ODER eine nicht leere, vollständig abgehakte DoD ({text, checked}).
     */
    protected function isDocumented(HelpdeskTicket $ticket): bool
    {
        if (trim((string) $ticket->resolution) !== '') {
            return true;
        }
        $dod = is_array($ticket->dod) ? $ticket->dod : [];
        if (empty($dod)) {
            return false;
        }
        foreach ($dod as $item) {
            if (! is_array($item) || empty($item['checked'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Gebuchte Minuten je Ticket und User (Organization-Zeiteinträge). context_type kann FQCN oder
     * Morph-Alias sein — beides wird gezählt. Ohne organization leer (→ minutes null).
     *
     * @return array<int,array<int,int>> ticket_id → user_id → minutes
     */
    protected function bookedMinutes(array $ticketIds): array
    {
        $entryClass = \Platform\Organization\Models\OrganizationTimeEntry::class;
        if (empty($ticketIds) || ! class_exists($entryClass)) {
            return [];
        }
        try {
            $rows = $entryClass::query()
                ->whereIn('context_type', array_values(array_unique([
                    HelpdeskTicket::class,
                    $this->morphAlias(HelpdeskTicket::class, 'helpdesk_ticket'),
                ])))
                ->whereIn('context_id', $ticketIds)
                ->selectRaw('context_id, user_id, sum(minutes) as total')
                ->groupBy('context_id', 'user_id')
                ->get();
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->context_id][(int) $row->user_id] = (int) $row->total;
        }

        return $out;
    }

    protected function morphAlias(string $class, string $fallback): string
    {
        $alias = array_search($class, Relation::morphMap(), true);

        return is_string($alias) && $alias !== '' ? $alias : $fallback;
    }
}
