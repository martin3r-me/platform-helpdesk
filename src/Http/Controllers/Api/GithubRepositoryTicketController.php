<?php

namespace Platform\Helpdesk\Http\Controllers\Api;

use Platform\Core\Http\Controllers\ApiController;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Helpdesk\Enums\TicketStatus;
use Platform\Integrations\Models\IntegrationsGithubRepository;
use Platform\Integrations\Models\IntegrationAccountLink;
use Platform\Core\Services\OpenAiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * API Controller für GitHub Repository-bezogene Tickets
 */
class GithubRepositoryTicketController extends ApiController
{
    /**
     * Gibt das nächste offene Ticket für ein GitHub Repository zurück
     * 
     * Query Parameter:
     * - repo: GitHub Repository full_name (z.B. "username/repo-name")
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNextOpenTicket(Request $request)
    {
        $repoFullName = $request->query('repo');

        if (!$repoFullName) {
            return $this->error('Parameter "repo" fehlt. Beispiel: ?repo=username/repo-name', 400);
        }

        // GitHub Repository finden
        $repository = IntegrationsGithubRepository::where('full_name', $repoFullName)->first();

        if (!$repository) {
            return $this->error("GitHub Repository '{$repoFullName}' nicht gefunden.", 404);
        }

        // Alle Tickets finden, die mit diesem Repository verknüpft sind
        $links = IntegrationAccountLink::where('account_type', 'github_repository')
            ->where('account_id', $repository->id)
            ->where('linkable_type', HelpdeskTicket::class)
            ->get();

        if ($links->isEmpty()) {
            return $this->success([
                'repository' => [
                    'id' => $repository->id,
                    'full_name' => $repository->full_name,
                    'name' => $repository->name,
                    'owner' => $repository->owner,
                    'url' => $repository->url,
                ],
                'ticket' => null,
                'message' => 'Keine Tickets für dieses Repository gefunden',
            ], 'Kein offenes Ticket gefunden');
        }

        $ticketIds = $links->pluck('linkable_id')->toArray();

        // Nächstes offenes Ticket finden
        // Offen = nicht erledigt (is_done = false), nicht gesperrt (is_locked = false) und Status nicht 'closed' oder 'resolved'
        // WICHTIG: Nur Tickets aus Slots holen, NICHT aus Backlog/Inbox (helpdesk_board_slot_id IS NOT NULL)
        $ticket = HelpdeskTicket::whereIn('id', $ticketIds)
            ->where('is_done', false)
            ->where('is_locked', false) // Nur nicht gesperrte Tickets
            ->whereNotNull('helpdesk_board_slot_id') // Nur Tickets aus Slots, nicht aus Backlog/Inbox
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhereNotIn('status', ['closed', 'resolved']);
            })
            ->orderBy('created_at', 'asc') // Ältestes zuerst
            ->with(['helpdeskBoard:id,name', 'helpdeskBoardSlot:id,name,order', 'team:id,name', 'user:id,name,email', 'userInCharge:id,name,email', 'resolution'])
            ->first();

        if (!$ticket) {
            return $this->success([
                'repository' => [
                    'id' => $repository->id,
                    'full_name' => $repository->full_name,
                    'name' => $repository->name,
                    'owner' => $repository->owner,
                    'url' => $repository->url,
                ],
                'ticket' => null,
                'message' => 'Kein offenes Ticket für dieses Repository gefunden',
            ], 'Kein offenes Ticket gefunden');
        }

        // Ticket sperren (wenn es zurückgegeben wird)
        $ticket->lock();

        // Verknüpfte GitHub Repositories laden
        $linkedRepositories = $ticket->githubRepositories();

        // Ticket-Daten formatieren
        $ticketData = [
            'id' => $ticket->id,
            'uuid' => $ticket->uuid,
            'title' => $ticket->title,
            'notes' => $ticket->notes,
            'description' => $ticket->notes, // Abwärtskompatibilität
            'dod' => $ticket->dod,
            'dod_progress' => $ticket->dod_progress,
            'team_id' => $ticket->team_id,
            'team_name' => $ticket->team?->name,
            'user_id' => $ticket->user_id,
            'user_name' => $ticket->user?->name,
            'user_email' => $ticket->user?->email,
            'user_in_charge_id' => $ticket->user_in_charge_id,
            'user_in_charge_name' => $ticket->userInCharge?->name,
            'user_in_charge_email' => $ticket->userInCharge?->email,
            'helpdesk_board_id' => $ticket->helpdesk_board_id,
            'helpdesk_board_name' => $ticket->helpdeskBoard?->name,
            'helpdesk_board_slot_id' => $ticket->helpdesk_board_slot_id,
            'helpdesk_board_slot_name' => $ticket->helpdeskBoardSlot?->name,
            'helpdesk_board_slot_order' => $ticket->helpdeskBoardSlot?->order,
            'is_done' => $ticket->is_done,
            'done_at' => $ticket->done_at?->toIso8601String(),
            'is_locked' => $ticket->is_locked,
            'locked_at' => $ticket->locked_at?->toIso8601String(),
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
            'created_at' => $ticket->created_at->toIso8601String(),
            'updated_at' => $ticket->updated_at->toIso8601String(),
            'url' => route('helpdesk.tickets.show', $ticket),
            'github_repositories' => $linkedRepositories->map(function ($repo) {
                return [
                    'id' => $repo->id,
                    'full_name' => $repo->full_name,
                    'name' => $repo->name,
                    'owner' => $repo->owner,
                    'url' => $repo->url,
                    'language' => $repo->language,
                    'is_private' => $repo->is_private,
                ];
            })->toArray(),
            'resolution' => $ticket->resolution ? [
                'id' => $ticket->resolution->id,
                'resolution_text' => $ticket->resolution->resolution_text,
                'ai_generated' => $ticket->resolution->ai_generated,
                'user_confirmed' => $ticket->resolution->user_confirmed,
                'effectiveness_score' => $ticket->resolution->effectiveness_score,
                'created_at' => $ticket->resolution->created_at?->toIso8601String(),
                'updated_at' => $ticket->resolution->updated_at?->toIso8601String(),
            ] : null,
        ];

        return $this->success([
            'repository' => [
                'id' => $repository->id,
                'full_name' => $repository->full_name,
                'name' => $repository->name,
                'owner' => $repository->owner,
                'url' => $repository->url,
            ],
            'ticket' => $ticketData,
        ], 'Nächstes offenes Ticket gefunden und gesperrt');
    }

    /**
     * Markiert ein Ticket als erledigt
     * 
     * Query Parameter:
     * - ticket_id: Ticket ID (optional, wenn uuid verwendet wird)
     * - ticket_uuid: Ticket UUID (optional, wenn id verwendet wird)
     * - repo: GitHub Repository full_name (optional, zur Validierung)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function markTicketAsDone(Request $request)
    {
        $ticketId = $request->query('ticket_id');
        $ticketUuid = $request->query('ticket_uuid');
        $repoFullName = $request->query('repo');

        // Ticket finden (auch wenn es gesperrt oder als done markiert ist)
        $ticket = null;
        if ($ticketUuid) {
            $ticket = HelpdeskTicket::withTrashed()->where('uuid', $ticketUuid)->first();
        } elseif ($ticketId) {
            $ticket = HelpdeskTicket::withTrashed()->find($ticketId);
        } else {
            return $this->error('Parameter "ticket_id" oder "ticket_uuid" fehlt.', 400);
        }

        if (!$ticket) {
            return $this->error('Ticket nicht gefunden.', 404);
        }
        
        // Prüfe ob Ticket gelöscht wurde
        if ($ticket->trashed()) {
            return $this->error('Ticket wurde gelöscht.', 404);
        }

        // Optional: Validierung gegen Repository, wenn angegeben
        if ($repoFullName) {
            $repository = IntegrationsGithubRepository::where('full_name', $repoFullName)->first();
            
            if (!$repository) {
                return $this->error("GitHub Repository '{$repoFullName}' nicht gefunden.", 404);
            }

            // Prüfe ob Ticket mit Repository verknüpft ist
            $isLinked = IntegrationAccountLink::where('account_type', 'github_repository')
                ->where('account_id', $repository->id)
                ->where('linkable_type', HelpdeskTicket::class)
                ->where('linkable_id', $ticket->id)
                ->exists();

            if (!$isLinked) {
                return $this->error("Ticket ist nicht mit Repository '{$repoFullName}' verknüpft.", 400);
            }
        }

        // Ticket als erledigt markieren und entsperren
        $ticket->is_done = true;
        $ticket->done_at = now();
        $ticket->unlock(); // Ticket entsperren

        // Alle DoD-Einträge als erledigt markieren
        if (!empty($ticket->dod) && is_array($ticket->dod)) {
            $dod = $ticket->dod;
            foreach ($dod as $index => $item) {
                $dod[$index]['checked'] = true;
            }
            $ticket->dod = $dod;
        }

        $ticket->save();

        // Ticket-Daten formatieren
        $ticketData = [
            'id' => $ticket->id,
            'uuid' => $ticket->uuid,
            'title' => $ticket->title,
            'notes' => $ticket->notes,
            'description' => $ticket->notes, // Abwärtskompatibilität
            'dod' => $ticket->dod,
            'dod_progress' => $ticket->dod_progress,
            'is_done' => $ticket->is_done,
            'done_at' => $ticket->done_at->toIso8601String(),
            'status' => $ticket->status?->value,
            'priority' => $ticket->priority?->value,
            'created_at' => $ticket->created_at->toIso8601String(),
            'updated_at' => $ticket->updated_at->toIso8601String(),
            'url' => route('helpdesk.tickets.show', $ticket),
        ];

        $response = [
            'ticket' => $ticketData,
        ];

        if ($repoFullName) {
            $response['repository'] = [
                'full_name' => $repoFullName,
            ];
        }

        return $this->success($response, 'Ticket wurde als erledigt markiert');
    }

    /**
     * Markiert ein Ticket als "checked" (geprüft) - entsperrt es, markiert es aber nicht als done
     * 
     * Query Parameter:
     * - ticket_id: Ticket ID (optional, wenn uuid verwendet wird)
     * - ticket_uuid: Ticket UUID (optional, wenn id verwendet wird)
     * - repo: GitHub Repository full_name (optional, zur Validierung)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function markTicketAsChecked(Request $request)
    {
        $ticketId = $request->query('ticket_id');
        $ticketUuid = $request->query('ticket_uuid');
        $repoFullName = $request->query('repo');

        // Ticket finden (auch wenn es gesperrt ist)
        $ticket = null;
        if ($ticketUuid) {
            $ticket = HelpdeskTicket::withTrashed()->where('uuid', $ticketUuid)->first();
        } elseif ($ticketId) {
            $ticket = HelpdeskTicket::withTrashed()->find($ticketId);
        } else {
            return $this->error('Parameter "ticket_id" oder "ticket_uuid" fehlt.', 400);
        }

        if (!$ticket) {
            return $this->error('Ticket nicht gefunden.', 404);
        }
        
        // Prüfe ob Ticket gelöscht wurde
        if ($ticket->trashed()) {
            return $this->error('Ticket wurde gelöscht.', 404);
        }

        // Optional: Validierung gegen Repository, wenn angegeben
        if ($repoFullName) {
            $repository = IntegrationsGithubRepository::where('full_name', $repoFullName)->first();
            
            if (!$repository) {
                return $this->error("GitHub Repository '{$repoFullName}' nicht gefunden.", 404);
            }

            // Prüfe ob Ticket mit Repository verknüpft ist
            $isLinked = IntegrationAccountLink::where('account_type', 'github_repository')
                ->where('account_id', $repository->id)
                ->where('linkable_type', HelpdeskTicket::class)
                ->where('linkable_id', $ticket->id)
                ->exists();

            if (!$isLinked) {
                return $this->error("Ticket ist nicht mit Repository '{$repoFullName}' verknüpft.", 400);
            }
        }

        // Ticket entsperren (aber nicht als done markieren)
        $ticket->unlock();
        $ticket->save();

        // Ticket-Daten formatieren
        $ticketData = [
            'id' => $ticket->id,
            'uuid' => $ticket->uuid,
            'title' => $ticket->title,
            'is_locked' => $ticket->is_locked,
            'is_done' => $ticket->is_done,
            'status' => $ticket->status?->value,
            'updated_at' => $ticket->updated_at->toIso8601String(),
        ];

        $response = [
            'ticket' => $ticketData,
        ];

        if ($repoFullName) {
            $response['repository'] = [
                'full_name' => $repoFullName,
            ];
        }

        return $this->success($response, 'Ticket wurde als geprüft markiert und entsperrt');
    }

    /**
     * Entsperrt ein Ticket (ohne es als erledigt zu markieren)
     * 
     * POST /api/helpdesk/tickets/unlock
     * Body: { "ticket_id": 123 } oder { "ticket_uuid": "..." }
     */
    public function unlockTicket(Request $request)
    {
        $ticketId = $request->input('ticket_id');
        $ticketUuid = $request->input('ticket_uuid');

        if (!$ticketId && !$ticketUuid) {
            return $this->error('Ticket-ID oder UUID erforderlich', 400);
        }

        $ticket = null;
        if ($ticketId) {
            $ticket = HelpdeskTicket::find($ticketId);
        } elseif ($ticketUuid) {
            $ticket = HelpdeskTicket::where('uuid', $ticketUuid)->first();
        }

        if (!$ticket) {
            return $this->error('Ticket nicht gefunden', 404);
        }

        // Ticket entsperren
        $ticket->unlock();
        $ticket->save();

        $ticketData = [
            'id' => $ticket->id,
            'uuid' => $ticket->uuid,
            'title' => $ticket->title,
            'is_locked' => $ticket->is_locked,
            'status' => $ticket->status?->value,
            'updated_at' => $ticket->updated_at->toIso8601String(),
        ];

        return $this->success([
            'ticket' => $ticketData,
        ], 'Ticket wurde entsperrt');
    }

    /**
     * Gibt ein Ticket anhand von ID, UUID oder Repository zurück
     *
     * Query Parameter:
     * - ticket_id: Ticket ID (optional, wenn uuid oder repo verwendet wird)
     * - ticket_uuid: Ticket UUID (optional, wenn id oder repo verwendet wird)
     * - repo: GitHub Repository full_name (optional, wenn nur repo angegeben wird, wird das Ticket mit der niedrigsten Slot-Order zurückgegeben)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTicket(Request $request)
    {
        $ticketId = $request->query('ticket_id');
        $ticketUuid = $request->query('ticket_uuid');
        $repoFullName = $request->query('repo');

        // Ticket finden (auch wenn es gesperrt oder als done markiert ist)
        $ticket = null;
        if ($ticketUuid) {
            $ticket = HelpdeskTicket::withTrashed()->where('uuid', $ticketUuid)
                ->with(['helpdeskBoard:id,name', 'helpdeskBoardSlot:id,name,order', 'team:id,name', 'userInCharge:id,name,email', 'user:id,name,email', 'resolution'])
                ->first();
        } elseif ($ticketId) {
            $ticket = HelpdeskTicket::withTrashed()->with(['helpdeskBoard:id,name', 'helpdeskBoardSlot:id,name,order', 'team:id,name', 'userInCharge:id,name,email', 'user:id,name,email', 'resolution'])
                ->find($ticketId);
        } elseif ($repoFullName) {
            // Wenn nur repo angegeben ist: Ticket mit der niedrigsten Slot-Order für diese Repo holen
            $repository = IntegrationsGithubRepository::where('full_name', $repoFullName)->first();

            if (!$repository) {
                return $this->error("GitHub Repository '{$repoFullName}' nicht gefunden.", 404);
            }

            // Alle Ticket-IDs finden, die mit diesem Repository verknüpft sind
            $links = IntegrationAccountLink::where('account_type', 'github_repository')
                ->where('account_id', $repository->id)
                ->where('linkable_type', HelpdeskTicket::class)
                ->get();

            if ($links->isEmpty()) {
                return $this->success([
                    'repository' => [
                        'id' => $repository->id,
                        'full_name' => $repository->full_name,
                        'name' => $repository->name,
                        'owner' => $repository->owner,
                        'url' => $repository->url,
                    ],
                    'ticket' => null,
                    'message' => 'Keine Tickets für dieses Repository gefunden',
                ], 'Kein Ticket gefunden');
            }

            $ticketIds = $links->pluck('linkable_id')->toArray();

            // Ticket mit der niedrigsten Slot-Order finden (nur aus Slots, nicht aus Backlog/Inbox)
            $ticket = HelpdeskTicket::withTrashed()
                ->whereIn('id', $ticketIds)
                ->whereNotNull('helpdesk_board_slot_id') // Nur Tickets aus Slots
                ->with(['helpdeskBoard:id,name', 'helpdeskBoardSlot:id,name,order', 'team:id,name', 'userInCharge:id,name,email', 'user:id,name,email', 'resolution'])
                ->join('helpdesk_board_slots', 'helpdesk_tickets.helpdesk_board_slot_id', '=', 'helpdesk_board_slots.id')
                ->orderBy('helpdesk_board_slots.order', 'asc')
                ->select('helpdesk_tickets.*')
                ->first();

            // Fallback: Wenn kein Ticket in Slots, dann erstes Ticket aus der Liste nehmen
            if (!$ticket) {
                $ticket = HelpdeskTicket::withTrashed()
                    ->whereIn('id', $ticketIds)
                    ->with(['helpdeskBoard:id,name', 'helpdeskBoardSlot:id,name,order', 'team:id,name', 'userInCharge:id,name,email', 'user:id,name,email', 'resolution'])
                    ->first();
            }
        } else {
            return $this->error('Parameter "ticket_id", "ticket_uuid" oder "repo" fehlt.', 400);
        }

        if (!$ticket) {
            return $this->error('Ticket nicht gefunden.', 404);
        }

        // Prüfe ob Ticket gelöscht wurde
        if ($ticket->trashed()) {
            return $this->error('Ticket wurde gelöscht.', 404);
        }

        // Optional: Validierung gegen Repository, wenn angegeben (und wenn ticket_id oder ticket_uuid verwendet wurde)
        if ($repoFullName && ($ticketId || $ticketUuid)) {
            $repository = IntegrationsGithubRepository::where('full_name', $repoFullName)->first();

            if (!$repository) {
                return $this->error("GitHub Repository '{$repoFullName}' nicht gefunden.", 404);
            }

            // Prüfe ob Ticket mit Repository verknüpft ist
            $isLinked = IntegrationAccountLink::where('account_type', 'github_repository')
                ->where('account_id', $repository->id)
                ->where('linkable_type', HelpdeskTicket::class)
                ->where('linkable_id', $ticket->id)
                ->exists();

            if (!$isLinked) {
                return $this->error("Ticket ist nicht mit Repository '{$repoFullName}' verknüpft.", 400);
            }
        }

        // Verknüpfte GitHub Repositories laden
        $linkedRepositories = $ticket->githubRepositories();

        // Ticket-Daten formatieren
        $ticketData = [
            'id' => $ticket->id,
            'uuid' => $ticket->uuid,
            'title' => $ticket->title,
            'notes' => $ticket->notes,
            'description' => $ticket->notes, // Abwärtskompatibilität
            'dod' => $ticket->dod,
            'dod_progress' => $ticket->dod_progress,
            'team_id' => $ticket->team_id,
            'team_name' => $ticket->team?->name,
            'user_id' => $ticket->user_id,
            'user_name' => $ticket->user?->name,
            'user_email' => $ticket->user?->email,
            'user_in_charge_id' => $ticket->user_in_charge_id,
            'user_in_charge_name' => $ticket->userInCharge?->name,
            'user_in_charge_email' => $ticket->userInCharge?->email,
            'helpdesk_board_id' => $ticket->helpdesk_board_id,
            'helpdesk_board_name' => $ticket->helpdeskBoard?->name,
            'helpdesk_board_slot_id' => $ticket->helpdesk_board_slot_id,
            'helpdesk_board_slot_name' => $ticket->helpdeskBoardSlot?->name,
            'helpdesk_board_slot_order' => $ticket->helpdeskBoardSlot?->order,
            'helpdesk_ticket_group_id' => $ticket->helpdesk_ticket_group_id,
            'is_done' => $ticket->is_done,
            'done_at' => $ticket->done_at?->toIso8601String(),
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
            'created_at' => $ticket->created_at->toIso8601String(),
            'updated_at' => $ticket->updated_at->toIso8601String(),
            'url' => route('helpdesk.tickets.show', $ticket),
            'github_repositories' => $linkedRepositories->map(function ($repo) {
                return [
                    'id' => $repo->id,
                    'full_name' => $repo->full_name,
                    'name' => $repo->name,
                    'owner' => $repo->owner,
                    'url' => $repo->url,
                    'language' => $repo->language,
                    'is_private' => $repo->is_private,
                ];
            })->toArray(),
            'resolution' => $ticket->resolution ? [
                'id' => $ticket->resolution->id,
                'resolution_text' => $ticket->resolution->resolution_text,
                'ai_generated' => $ticket->resolution->ai_generated,
                'user_confirmed' => $ticket->resolution->user_confirmed,
                'effectiveness_score' => $ticket->resolution->effectiveness_score,
                'created_at' => $ticket->resolution->created_at?->toIso8601String(),
                'updated_at' => $ticket->resolution->updated_at?->toIso8601String(),
            ] : null,
        ];

        $response = [
            'ticket' => $ticketData,
        ];

        if ($repoFullName) {
            $response['repository'] = [
                'full_name' => $repoFullName,
            ];
        }

        return $this->success($response, 'Ticket erfolgreich geladen');
    }

    /**
     * Fügt einen Plan/Kommentar zum Ticket hinzu
     * 
     * Query Parameter:
     * - ticket_id: Ticket ID (optional, wenn uuid verwendet wird)
     * - ticket_uuid: Ticket UUID (optional, wenn id verwendet wird)
     * 
     * Body Parameter:
     * - plan: Der Plan-Text (required)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addPlanToTicket(Request $request)
    {
        $ticketId = $request->query('ticket_id');
        $ticketUuid = $request->query('ticket_uuid');
        $plan = $request->input('plan');

        if (!$plan) {
            return $this->error('Parameter "plan" fehlt.', 400);
        }

        // Ticket finden
        $ticket = null;
        if ($ticketUuid) {
            $ticket = HelpdeskTicket::withTrashed()->where('uuid', $ticketUuid)->first();
        } elseif ($ticketId) {
            $ticket = HelpdeskTicket::withTrashed()->find($ticketId);
        } else {
            return $this->error('Parameter "ticket_id" oder "ticket_uuid" fehlt.', 400);
        }

        if (!$ticket) {
            return $this->error('Ticket nicht gefunden.', 404);
        }
        
        if ($ticket->trashed()) {
            return $this->error('Ticket wurde gelöscht.', 404);
        }

        // Plan zur Anmerkung (notes) hinzufügen (als separater Abschnitt)
        $separator = "\n\n---\n\n## 🤖 Agent Plan\n\n";
        $currentNotes = $ticket->notes ?? '';

        // Entferne alten Plan, falls vorhanden
        $notesWithoutPlan = preg_replace('/\n\n---\n\n## 🤖 Agent Plan\n\n.*/s', '', $currentNotes);

        // Füge neuen Plan hinzu
        $newNotes = trim($notesWithoutPlan) . $separator . $plan;

        $ticket->notes = $newNotes;
        $ticket->save();

        return $this->success([
            'ticket' => [
                'id' => $ticket->id,
                'uuid' => $ticket->uuid,
                'notes' => $ticket->notes,
                'description' => $ticket->notes, // Abwärtskompatibilität
            ],
        ], 'Plan wurde zum Ticket hinzugefügt');
    }

    /**
     * Setzt den Status eines Tickets
     * 
     * Query Parameter:
     * - ticket_id: Ticket ID (optional, wenn uuid verwendet wird)
     * - ticket_uuid: Ticket UUID (optional, wenn id verwendet wird)
     * - status: Neuer Status (required, z.B. "open", "in_progress", "waiting", "resolved", "closed")
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function setTicketStatus(Request $request)
    {
        $ticketId = $request->query('ticket_id');
        $ticketUuid = $request->query('ticket_uuid');
        $statusValue = $request->query('status');

        if (!$statusValue) {
            return $this->error('Parameter "status" fehlt.', 400);
        }

        // Validiere Status
        try {
            $status = TicketStatus::from($statusValue);
        } catch (\ValueError $e) {
            return $this->error("Ungültiger Status '{$statusValue}'. Erlaubte Werte: open, in_progress, waiting, resolved, closed", 400);
        }

        // Ticket finden
        $ticket = null;
        if ($ticketUuid) {
            $ticket = HelpdeskTicket::withTrashed()->where('uuid', $ticketUuid)->first();
        } elseif ($ticketId) {
            $ticket = HelpdeskTicket::withTrashed()->find($ticketId);
        } else {
            return $this->error('Parameter "ticket_id" oder "ticket_uuid" fehlt.', 400);
        }

        if (!$ticket) {
            return $this->error('Ticket nicht gefunden.', 404);
        }
        
        if ($ticket->trashed()) {
            return $this->error('Ticket wurde gelöscht.', 404);
        }

        // Status setzen
        $ticket->status = $status;
        $ticket->save();

        return $this->success([
            'ticket' => [
                'id' => $ticket->id,
                'uuid' => $ticket->uuid,
                'status' => $ticket->status->value,
                'status_label' => $ticket->status->label(),
            ],
        ], 'Status wurde aktualisiert');
    }

    /**
     * Führt eine Code-Analyse mit Claude durch (read-only)
     * 
     * Body Parameter:
     * - ticket_id: Ticket ID (required)
     * - ticket_title: Ticket Titel (required)
     * - ticket_description: Ticket Beschreibung (required)
     * - repo_structure: Repository-Struktur als JSON (required)
     * - repo_files: Wichtige Dateien-Inhalte als JSON (optional)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function analyzeCodeWithClaude(Request $request)
    {
        $ticketId = $request->input('ticket_id');
        $ticketTitle = $request->input('ticket_title');
        $ticketDescription = $request->input('ticket_description');
        $repoStructure = $request->input('repo_structure');
        $repoFiles = $request->input('repo_files', []);

        if (!$ticketId || !$ticketTitle || !$repoStructure) {
            return $this->error('Parameter "ticket_id", "ticket_title" und "repo_structure" sind erforderlich.', 400);
        }

        try {
            $openAiService = app(OpenAiService::class);
            
            // Erstelle strukturierten Prompt für Claude
            $prompt = $this->buildCodeAnalysisPrompt(
                $ticketId,
                $ticketTitle,
                $ticketDescription ?? '',
                $repoStructure,
                $repoFiles
            );

            // Rufe Claude/OpenAI auf
            $messages = [
                [
                    'role' => 'system',
                    'content' => 'Du bist ein erfahrener Software-Entwickler, der Code-Analysen durchführt. Du arbeitest NUR read-only - du änderst, committest oder pushst NICHTS. Du analysierst Code und erstellst detaillierte Pläne.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ];

            $response = $openAiService->chat($messages, 'gpt-5.2', [
                'max_tokens' => 4000,
                'temperature' => 0.3,
                'tools' => false,
                'with_context' => false,
            ]);

            $analysis = $response['content'] ?? '';

            return $this->success([
                'analysis' => $analysis,
                'ticket_id' => $ticketId,
            ], 'Code-Analyse erfolgreich durchgeführt');

        } catch (\Exception $e) {
            Log::error('Code-Analyse Fehler', [
                'ticket_id' => $ticketId,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Fehler bei der Code-Analyse: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Erstellt den Prompt für die Code-Analyse
     */
    private function buildCodeAnalysisPrompt(
        int $ticketId,
        string $ticketTitle,
        string $ticketDescription,
        array $repoStructure,
        array $repoFiles = []
    ): string {
        $prompt = <<<PROMPT
# Code-Analyse für Ticket #{$ticketId}

## Kontext
**Ticket-ID:** {$ticketId}
**Titel:** {$ticketTitle}
**Beschreibung:**
{$ticketDescription}

## Projektstruktur
```json
{$this->formatJsonForPrompt($repoStructure)}
```

PROMPT;

        if (!empty($repoFiles)) {
            $prompt .= "\n## Wichtige Dateien-Inhalte\n\n";
            foreach ($repoFiles as $file => $content) {
                $prompt .= "### {$file}\n```\n" . substr($content, 0, 2000) . "\n```\n\n";
            }
        }

        $prompt .= <<<PROMPT

## Aufgabe
Führe eine detaillierte Code-Analyse durch (NUR READ-ONLY - keine Änderungen!). Erstelle einen strukturierten Plan mit folgenden Abschnitten:

### 1. Problem-Zusammenfassung
1-2 Sätze: Was ist das Problem?

### 2. Definition of Done
Was muss erfüllt sein, damit das Ticket als "done" gilt?

### 3. Code-Suche
Suche nach relevanten Begriffen im Code (Klassenname, Route, Fehlermeldung, Feature). Identifiziere Kandidaten-Dateien.

### 4. Likely Files
Liste mit 3-10 Dateien/Classes, die betroffen wären:
- Dateipfad
- Warum diese Datei betroffen ist (1 Satz)

### 5. Umsetzungsplan
3-7 Schritte:
- Pro Schritt: Was genau geändert werden müsste (OHNE es zu ändern!)
- Reihenfolge beachten

### 6. How to Test
2-5 konkrete User-Test-Schritte + erwartetes Ergebnis:
- Schritt 1: ...
- Erwartetes Ergebnis: ...

### 7. Risiko-Einstufung
low / medium / high + 1 Satz warum

### 8. Offene Fragen
max. 3 Rückfragen (nur wenn wirklich nötig)

---

**WICHTIG:** 
- NUR READ-ONLY - keine Code-Änderungen!
- Strukturierter Output als Markdown
- Präzise und konkret
PROMPT;

        return $prompt;
    }

    /**
     * Formatiert JSON für den Prompt
     */
    private function formatJsonForPrompt($data): string
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $json ?: '{}';
    }
}
