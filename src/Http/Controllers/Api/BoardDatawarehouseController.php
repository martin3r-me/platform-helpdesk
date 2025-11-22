<?php

namespace Platform\Helpdesk\Http\Controllers\Api;

use Platform\Core\Http\Controllers\ApiController;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Platform\Core\Models\Team;
use Illuminate\Http\Request;
use Carbon\Carbon;

/**
 * Datawarehouse API Controller für Helpdesk Boards
 * 
 * Stellt flexible Filter und Aggregationen für das Datawarehouse bereit.
 * Unterstützt Team-Hierarchien (inkl. Kind-Teams).
 */
class BoardDatawarehouseController extends ApiController
{
    /**
     * Flexibler Datawarehouse-Endpunkt für Boards
     * 
     * Unterstützt komplexe Filter und Aggregationen
     */
    public function index(Request $request)
    {
        $query = HelpdeskBoard::query();

        // ===== FILTER =====
        $this->applyFilters($query, $request);

        // ===== SORTING =====
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        
        // Validierung der Sort-Spalte (Security)
        $allowedSortColumns = ['id', 'created_at', 'updated_at', 'name', 'order'];
        if (in_array($sortBy, $allowedSortColumns)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // ===== PAGINATION =====
        $perPage = min($request->get('per_page', 100), 1000); // Max 1000 pro Seite
        // Team-Relation laden für Team-Name
        $query->with('team:id,name', 'user:id,name,email');
        $boards = $query->paginate($perPage);

        // ===== FORMATTING =====
        // Datawarehouse-freundliches Format
        $formatted = $boards->map(function ($board) {
            return [
                'id' => $board->id,
                'uuid' => $board->uuid,
                'name' => $board->name,
                'description' => $board->description,
                'team_id' => $board->team_id,
                'team_name' => $board->team?->name, // Team-Name mitliefern (denormalisiert)
                'user_id' => $board->user_id,
                'user_name' => $board->user?->name, // User-Name mitliefern (denormalisiert)
                'user_email' => $board->user?->email, // User-Email mitliefern
                'order' => $board->order,
                'created_at' => $board->created_at->toIso8601String(),
                'updated_at' => $board->updated_at->toIso8601String(),
            ];
        });

        return $this->paginated(
            $boards->setCollection($formatted),
            'Boards erfolgreich geladen'
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

        // User-Filter
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
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

        // Name-Suche
        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
    }
}

