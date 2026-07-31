<?php

namespace Platform\Helpdesk\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Helpdesk\Models\HelpdeskBoard;

/**
 * Frischer Agent-Flow fürs Helpdesk (Support-Worker) — bewusst NEU, unabhängig vom
 * alten GithubRepository-Ticket-Flow. Gleicher Aufbau wie dev/planner: token-
 * authentifizierte Endpunkte, vom Worker-Cockpit konsumiert.
 */
class AgentController extends Controller
{
    /**
     * GET /api/helpdesk/agent/boards
     * Alle Helpdesk-Boards des Worker-Teams — für die bewusste Board-Auswahl in den
     * Support-Settings (default nichts angehakt).
     */
    public function boards(Request $request): JsonResponse
    {
        $teamId = (int) ($request->user()?->current_team_id ?? 0);

        $boards = HelpdeskBoard::query()
            ->when($teamId > 0, fn ($q) => $q->where('team_id', $teamId))
            ->orderBy('name')
            ->get(['id', 'name', 'team_id']);

        return response()->json([
            'data' => $boards->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'team_id' => $b->team_id,
            ])->all(),
        ]);
    }
}
