<?php

namespace Platform\Helpdesk\Http\Controllers\Api;

use Platform\Core\Http\Controllers\ApiController;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Core\Models\Team;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Datawarehouse API Controller für Tickets
 * 
 * Stellt flexible Filter und Aggregationen für das Datawarehouse bereit.
 * Unterstützt Team-Hierarchien (inkl. Kind-Teams).
 */
class TicketDatawarehouseController extends ApiController
{
    /**
     * Flexibler Datawarehouse-Endpunkt für Tickets
     * 
     * Unterstützt komplexe Filter und Aggregationen
     */
    public function index(Request $request)
    {
        $query = HelpdeskTicket::query();

        // ===== FILTER =====
        $this->applyFilters($query, $request);

        // ===== AGGREGATION =====
        if ($request->has('aggregate')) {
            return $this->handleAggregation($query, $request);
        }

        // ===== SORTING =====
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        
        // Validierung der Sort-Spalte (Security)
        $allowedSortColumns = ['id', 'created_at', 'updated_at', 'done_at', 'due_date', 'title', 'escalated_at'];
        if (in_array($sortBy, $allowedSortColumns)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // ===== PAGINATION =====
        $perPage = min($request->get('per_page', 100), 1000); // Max 1000 pro Seite
        // Board- und Team-Relationen laden für Namen
        $query->with('helpdeskBoard:id,name', 'team:id,name', 'userInCharge:id,name,email');
        $tickets = $query->paginate($perPage);

        // ===== FORMATTING =====
        // Datawarehouse-freundliches Format
        $formatted = $tickets->map(function ($ticket) {
            return [
                'id' => $ticket->id,
                'uuid' => $ticket->uuid,
                'title' => $ticket->title,
                'notes' => $ticket->notes,
                'description' => $ticket->notes, // Abwärtskompatibilität
                'dod' => $ticket->dod,
                'dod_progress' => $ticket->dod_progress,
                'team_id' => $ticket->team_id,
                'team_name' => $ticket->team?->name, // Team-Name mitliefern (denormalisiert)
                'user_id' => $ticket->user_id,
                'user_in_charge_id' => $ticket->user_in_charge_id,
                'user_in_charge_name' => $ticket->userInCharge?->name, // User in Charge Name
                'helpdesk_board_id' => $ticket->helpdesk_board_id,
                'helpdesk_board_name' => $ticket->helpdeskBoard?->name, // Board-Name mitliefern
                'helpdesk_board_slot_id' => $ticket->helpdesk_board_slot_id,
                'helpdesk_ticket_group_id' => $ticket->helpdesk_ticket_group_id,
                'is_done' => $ticket->is_done,
                'done_at' => $ticket->done_at?->toIso8601String(),
                'created_at' => $ticket->created_at->toIso8601String(),
                'updated_at' => $ticket->updated_at->toIso8601String(),
                'due_date' => $ticket->due_date?->format('Y-m-d'),
                'story_points' => $ticket->story_points?->value,
                'story_points_numeric' => $ticket->story_points?->points(),
                'priority' => $ticket->priority?->value,
                'status' => $ticket->status?->value,
                'escalation_level' => $ticket->escalation_level?->value,
                'escalated_at' => $ticket->escalated_at?->toIso8601String(),
                'escalation_count' => $ticket->escalation_count,
                'is_escalated' => $ticket->isEscalated(),
                'is_critical' => $ticket->isCritical(),
                'order' => $ticket->order,
                'slot_order' => $ticket->slot_order,
            ];
        });

        return $this->paginated(
            $tickets->setCollection($formatted),
            'Tickets erfolgreich geladen'
        );
    }

    /**
     * Wendet alle Filter auf die Query an
     */
    protected function applyFilters($query, Request $request): void
    {
        // Team-Filter mit Kind-Teams Option (standardmäßig aktiviert)
        if ($request->has('team_id')) {
            $teamId = $request->team_id;
            // Standardmäßig Kind-Teams inkludieren (wenn nicht explizit false)
            $includeChildrenValue = $request->input('include_child_teams');
            $includeChildren = $request->has('include_child_teams') 
                ? ($includeChildrenValue === '1' || $includeChildrenValue === 'true' || $includeChildrenValue === true || $includeChildrenValue === 1)
                : true; // Default: true (wenn nicht gesetzt)
            
            if ($includeChildren) {
                // Team mit Kind-Teams laden
                $team = Team::find($teamId);
                
                if ($team) {
                    // Alle Team-IDs inkl. Kind-Teams sammeln
                    $teamIds = $team->getAllTeamIdsIncludingChildren();
                    $query->whereIn('team_id', $teamIds);
                } else {
                    // Team nicht gefunden - leeres Ergebnis
                    $query->whereRaw('1 = 0');
                }
            } else {
                // Nur das genannte Team (wenn explizit deaktiviert)
                $query->where('team_id', $teamId);
            }
        }

        // WICHTIG: Kein Standard-Filter für is_done - alle Tickets werden zurückgegeben
        // Nur wenn explizit gefiltert wird

        // Erledigte Tickets (done_at)
        if ($request->has('is_done')) {
            if ($request->is_done === 'true' || $request->is_done === '1') {
                $query->whereNotNull('done_at');
            } elseif ($request->is_done === 'false' || $request->is_done === '0') {
                $query->whereNull('done_at');
            }
        }

        // Datums-Filter für done_at (heute erledigt)
        if ($request->boolean('done_today')) {
            $query->whereDate('done_at', Carbon::today());
        }

        // Datums-Range für done_at
        if ($request->has('done_from')) {
            $query->whereDate('done_at', '>=', $request->done_from);
        }
        if ($request->has('done_to')) {
            $query->whereDate('done_at', '<=', $request->done_to);
        }

        // Erstellt heute
        if ($request->boolean('created_today')) {
            $query->whereDate('created_at', Carbon::today());
        }

        // Erstellt in Range
        if ($request->has('created_from')) {
            $query->whereDate('created_at', '>=', $request->created_from);
        }
        if ($request->has('created_to')) {
            $query->whereDate('created_at', '<=', $request->created_to);
        }

        // User-Filter
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('user_in_charge_id')) {
            $query->where('user_in_charge_id', $request->user_in_charge_id);
        }

        // Board-Filter
        if ($request->has('helpdesk_board_id')) {
            $query->where('helpdesk_board_id', $request->helpdesk_board_id);
        }

        // Board Slot Filter
        if ($request->has('helpdesk_board_slot_id')) {
            $query->where('helpdesk_board_slot_id', $request->helpdesk_board_slot_id);
        }

        // Ticket Group Filter
        if ($request->has('helpdesk_ticket_group_id')) {
            $query->where('helpdesk_ticket_group_id', $request->helpdesk_ticket_group_id);
        }

        // Story Points Filter
        if ($request->has('has_story_points')) {
            if ($request->has_story_points === 'true' || $request->has_story_points === '1') {
                $query->whereNotNull('story_points');
            } else {
                $query->whereNull('story_points');
            }
        }

        // Priority Filter
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        // Status Filter
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Escalation Filter
        if ($request->has('escalation_level')) {
            $query->where('escalation_level', $request->escalation_level);
        }

        if ($request->boolean('is_escalated')) {
            $query->whereNotNull('escalated_at');
        }

        if ($request->boolean('is_critical')) {
            $query->whereIn('escalation_level', ['critical', 'urgent']);
        }

        // Due Date Filter
        if ($request->has('due_date')) {
            $query->whereDate('due_date', $request->due_date);
        }

        if ($request->has('due_date_from')) {
            $query->whereDate('due_date', '>=', $request->due_date_from);
        }

        if ($request->has('due_date_to')) {
            $query->whereDate('due_date', '<=', $request->due_date_to);
        }

        if ($request->boolean('overdue')) {
            $query->whereNotNull('due_date')
                  ->whereDate('due_date', '<', Carbon::today())
                  ->whereNull('done_at');
        }
    }

    /**
     * Aggregationen (z.B. Story Points Summe)
     */
    protected function handleAggregation($query, Request $request)
    {
        $aggregateType = $request->get('aggregate');
        $groupBy = $request->get('group_by');

        switch ($aggregateType) {
            case 'story_points_sum':
                return $this->aggregateStoryPoints($query, $groupBy);
            
            case 'count':
                return $this->aggregateCount($query, $groupBy);
            
            case 'story_points_avg':
                return $this->aggregateStoryPointsAvg($query, $groupBy);
            
            default:
                return $this->error('Unbekannte Aggregation: ' . $aggregateType);
        }
    }

    /**
     * Story Points Summe aggregieren
     */
    protected function aggregateStoryPoints($query, $groupBy = null)
    {
        // Lade alle Tickets (ohne Pagination für Aggregation)
        $tickets = $query->get();

        if ($groupBy === 'team_id') {
            // Gruppiert nach Team
            $result = $tickets->groupBy('team_id')->map(function ($teamTickets, $teamId) {
                return [
                    'team_id' => $teamId,
                    'total_story_points' => $teamTickets->sum(function ($ticket) {
                        return $ticket->story_points?->points() ?? 0;
                    }),
                    'ticket_count' => $teamTickets->count(),
                ];
            })->values();

            return $this->success($result, 'Story Points nach Team aggregiert');
        }

        if ($groupBy === 'date') {
            // Gruppiert nach Datum (done_at)
            $result = $tickets->groupBy(function ($ticket) {
                return $ticket->done_at?->format('Y-m-d') ?? 'no_date';
            })->map(function ($dateTickets, $date) {
                return [
                    'date' => $date === 'no_date' ? null : $date,
                    'total_story_points' => $dateTickets->sum(function ($ticket) {
                        return $ticket->story_points?->points() ?? 0;
                    }),
                    'ticket_count' => $dateTickets->count(),
                ];
            })->values();

            return $this->success($result, 'Story Points nach Datum aggregiert');
        }

        if ($groupBy === 'user_id') {
            // Gruppiert nach User
            $result = $tickets->groupBy('user_id')->map(function ($userTickets, $userId) {
                return [
                    'user_id' => $userId,
                    'total_story_points' => $userTickets->sum(function ($ticket) {
                        return $ticket->story_points?->points() ?? 0;
                    }),
                    'ticket_count' => $userTickets->count(),
                ];
            })->values();

            return $this->success($result, 'Story Points nach User aggregiert');
        }

        // Gesamt-Summe
        $total = $tickets->sum(function ($ticket) {
            return $ticket->story_points?->points() ?? 0;
        });

        return $this->success([
            'total_story_points' => $total,
            'ticket_count' => $tickets->count(),
        ], 'Story Points Summe');
    }

    /**
     * Story Points Durchschnitt aggregieren
     */
    protected function aggregateStoryPointsAvg($query, $groupBy = null)
    {
        $tickets = $query->get();

        if ($groupBy === 'team_id') {
            $result = $tickets->groupBy('team_id')->map(function ($teamTickets, $teamId) {
                $ticketsWithPoints = $teamTickets->filter(fn($ticket) => $ticket->story_points !== null);
                $avg = $ticketsWithPoints->count() > 0 
                    ? $ticketsWithPoints->avg(fn($ticket) => $ticket->story_points->points())
                    : 0;

                return [
                    'team_id' => $teamId,
                    'avg_story_points' => round($avg, 2),
                    'ticket_count' => $teamTickets->count(),
                    'tickets_with_points' => $ticketsWithPoints->count(),
                ];
            })->values();

            return $this->success($result, 'Durchschnittliche Story Points nach Team');
        }

        // Gesamt-Durchschnitt
        $ticketsWithPoints = $tickets->filter(fn($ticket) => $ticket->story_points !== null);
        $avg = $ticketsWithPoints->count() > 0 
            ? $ticketsWithPoints->avg(fn($ticket) => $ticket->story_points->points())
            : 0;

        return $this->success([
            'avg_story_points' => round($avg, 2),
            'ticket_count' => $tickets->count(),
            'tickets_with_points' => $ticketsWithPoints->count(),
        ], 'Durchschnittliche Story Points');
    }

    /**
     * Count-Aggregation
     */
    protected function aggregateCount($query, $groupBy = null)
    {
        if ($groupBy === 'team_id') {
            $result = $query->selectRaw('team_id, COUNT(*) as count')
                ->groupBy('team_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'team_id' => $item->team_id,
                        'count' => $item->count,
                    ];
                });

            return $this->success($result, 'Anzahl Tickets nach Team');
        }

        if ($groupBy === 'date') {
            $result = $query->selectRaw('DATE(done_at) as date, COUNT(*) as count')
                ->whereNotNull('done_at')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(function ($item) {
                    return [
                        'date' => $item->date,
                        'count' => $item->count,
                    ];
                });

            return $this->success($result, 'Anzahl Tickets nach Datum');
        }

        if ($groupBy === 'user_id') {
            $result = $query->selectRaw('user_id, COUNT(*) as count')
                ->groupBy('user_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'user_id' => $item->user_id,
                        'count' => $item->count,
                    ];
                });

            return $this->success($result, 'Anzahl Tickets nach User');
        }

        $count = $query->count();
        return $this->success(['count' => $count], 'Anzahl Tickets');
    }

    /**
     * Health Check Endpoint
     * Gibt einen Beispiel-Datensatz zurück für Tests
     */
    public function health(Request $request)
    {
        try {
            $example = HelpdeskTicket::with('helpdeskBoard:id,name', 'team:id,name', 'userInCharge:id,name,email')
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$example) {
                return $this->success([
                    'status' => 'ok',
                    'message' => 'API ist erreichbar, aber keine Tickets vorhanden',
                    'example' => null,
                    'timestamp' => now()->toIso8601String(),
                ], 'Health Check');
            }

            $exampleData = [
                'id' => $example->id,
                'uuid' => $example->uuid,
                'title' => $example->title,
                'description' => $example->description,
                'team_id' => $example->team_id,
                'team_name' => $example->team?->name,
                'user_id' => $example->user_id,
                'user_in_charge_id' => $example->user_in_charge_id,
                'user_in_charge_name' => $example->userInCharge?->name,
                'helpdesk_board_id' => $example->helpdesk_board_id,
                'helpdesk_board_name' => $example->helpdeskBoard?->name,
                'is_done' => $example->is_done,
                'done_at' => $example->done_at?->toIso8601String(),
                'due_date' => $example->due_date?->format('Y-m-d'),
                'story_points' => $example->story_points?->value,
                'story_points_numeric' => $example->story_points?->points(),
                'priority' => $example->priority?->value,
                'status' => $example->status?->value,
                'escalation_level' => $example->escalation_level?->value,
                'created_at' => $example->created_at->toIso8601String(),
                'updated_at' => $example->updated_at->toIso8601String(),
            ];

            return $this->success([
                'status' => 'ok',
                'message' => 'API ist erreichbar',
                'example' => $exampleData,
                'timestamp' => now()->toIso8601String(),
            ], 'Health Check');

        } catch (\Exception $e) {
            return $this->error('Health Check fehlgeschlagen: ' . $e->getMessage(), 500);
        }
    }
}

