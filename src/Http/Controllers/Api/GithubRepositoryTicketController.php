<?php

namespace Platform\Helpdesk\Http\Controllers\Api;

use Platform\Core\Http\Controllers\ApiController;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Helpdesk\Enums\TicketStatus;
use Platform\Integrations\Models\IntegrationsGithubRepository;
use Platform\Integrations\Models\IntegrationAccountLink;
use Illuminate\Http\Request;

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
        $ticket = HelpdeskTicket::whereIn('id', $ticketIds)
            ->where('is_done', false)
            ->where('is_locked', false) // Nur nicht gesperrte Tickets
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhereNotIn('status', ['closed', 'resolved']);
            })
            ->orderBy('created_at', 'asc') // Ältestes zuerst
            ->with(['helpdeskBoard:id,name', 'team:id,name', 'userInCharge:id,name,email'])
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

        // Ticket-Daten formatieren
        $ticketData = [
            'id' => $ticket->id,
            'uuid' => $ticket->uuid,
            'title' => $ticket->title,
            'description' => $ticket->description,
            'team_id' => $ticket->team_id,
            'team_name' => $ticket->team?->name,
            'user_id' => $ticket->user_id,
            'user_in_charge_id' => $ticket->user_in_charge_id,
            'user_in_charge_name' => $ticket->userInCharge?->name,
            'helpdesk_board_id' => $ticket->helpdesk_board_id,
            'helpdesk_board_name' => $ticket->helpdeskBoard?->name,
            'is_done' => $ticket->is_done,
            'is_locked' => $ticket->is_locked,
            'locked_at' => $ticket->locked_at?->toIso8601String(),
            'due_date' => $ticket->due_date?->format('Y-m-d'),
            'story_points' => $ticket->story_points?->value,
            'story_points_numeric' => $ticket->story_points?->points(),
            'priority' => $ticket->priority?->value,
            'status' => $ticket->status?->value,
            'escalation_level' => $ticket->escalation_level?->value,
            'created_at' => $ticket->created_at->toIso8601String(),
            'updated_at' => $ticket->updated_at->toIso8601String(),
            'url' => route('helpdesk.tickets.show', $ticket),
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
        $ticket->save();

        // Ticket-Daten formatieren
        $ticketData = [
            'id' => $ticket->id,
            'uuid' => $ticket->uuid,
            'title' => $ticket->title,
            'description' => $ticket->description,
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
     * Gibt ein Ticket anhand von ID oder UUID zurück
     * 
     * Query Parameter:
     * - ticket_id: Ticket ID (optional, wenn uuid verwendet wird)
     * - ticket_uuid: Ticket UUID (optional, wenn id verwendet wird)
     * - repo: GitHub Repository full_name (optional, zur Validierung)
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
                ->with(['helpdeskBoard:id,name', 'team:id,name', 'userInCharge:id,name,email', 'user:id,name,email'])
                ->first();
        } elseif ($ticketId) {
            $ticket = HelpdeskTicket::withTrashed()->with(['helpdeskBoard:id,name', 'team:id,name', 'userInCharge:id,name,email', 'user:id,name,email'])
                ->find($ticketId);
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

        // Verknüpfte GitHub Repositories laden
        $linkedRepositories = $ticket->githubRepositories();

        // Ticket-Daten formatieren
        $ticketData = [
            'id' => $ticket->id,
            'uuid' => $ticket->uuid,
            'title' => $ticket->title,
            'description' => $ticket->description,
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

        // Plan zur Beschreibung hinzufügen (als separater Abschnitt)
        $separator = "\n\n---\n\n## 🤖 Agent Plan\n\n";
        $currentDescription = $ticket->description ?? '';
        
        // Entferne alten Plan, falls vorhanden
        $descriptionWithoutPlan = preg_replace('/\n\n---\n\n## 🤖 Agent Plan\n\n.*/s', '', $currentDescription);
        
        // Füge neuen Plan hinzu
        $newDescription = trim($descriptionWithoutPlan) . $separator . $plan;
        
        $ticket->description = $newDescription;
        $ticket->save();

        return $this->success([
            'ticket' => [
                'id' => $ticket->id,
                'uuid' => $ticket->uuid,
                'description' => $ticket->description,
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
}
