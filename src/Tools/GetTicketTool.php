<?php

namespace Platform\Helpdesk\Tools;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Helpdesk\Tools\Concerns\ResolvesHelpdeskTeam;

class GetTicketTool implements ToolContract, ToolMetadataContract
{
    use ResolvesHelpdeskTeam;

    public function getName(): string
    {
        return 'helpdesk.ticket.GET';
    }

    public function getDescription(): string
    {
        return 'GET /helpdesk/tickets/{id} - Ruft ein Ticket ab. Parameter: ticket_id (required), team_id (optional).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'ticket_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Tickets (ERFORDERLICH). Nutze "helpdesk.tickets.GET".',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.',
                ],
            ],
            'required' => ['ticket_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int)$resolved['team_id'];

            $ticketId = (int)($arguments['ticket_id'] ?? 0);
            if ($ticketId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'ticket_id ist erforderlich.');
            }

            $ticket = HelpdeskTicket::query()
                ->with(['helpdeskBoard', 'helpdeskBoardSlot', 'helpdeskTicketGroup', 'user', 'userInCharge', 'escalations'])
                ->where('team_id', $teamId)
                ->find($ticketId);

            if (!$ticket) {
                return ToolResult::error('NOT_FOUND', 'Ticket nicht gefunden (oder kein Zugriff).');
            }

            Gate::forUser($context->user)->authorize('view', $ticket);

            return ToolResult::success([
                'id' => $ticket->id,
                'uuid' => $ticket->uuid,
                'title' => $ticket->title,
                'notes' => $ticket->notes,
                'description' => $ticket->notes, // Abwärtskompatibilität
                'dod' => $ticket->dod,
                'dod_progress' => $ticket->dod_progress,
                'team_id' => $ticket->team_id,
                'board' => $ticket->helpdeskBoard ? [
                    'id' => $ticket->helpdeskBoard->id,
                    'name' => $ticket->helpdeskBoard->name,
                ] : null,
                'slot' => $ticket->helpdeskBoardSlot ? [
                    'id' => $ticket->helpdeskBoardSlot->id,
                    'name' => $ticket->helpdeskBoardSlot->name,
                ] : null,
                'group' => $ticket->helpdeskTicketGroup ? [
                    'id' => $ticket->helpdeskTicketGroup->id,
                    'name' => $ticket->helpdeskTicketGroup->name,
                ] : null,
                'owner_user' => $ticket->user ? [
                    'id' => $ticket->user->id,
                    'name' => $ticket->user->name,
                ] : null,
                'user_in_charge' => $ticket->userInCharge ? [
                    'id' => $ticket->userInCharge->id,
                    'name' => $ticket->userInCharge->name,
                ] : null,
                'priority' => (string)($ticket->priority?->value ?? $ticket->priority),
                'story_points' => (string)($ticket->story_points?->value ?? $ticket->story_points),
                'is_done' => (bool)$ticket->is_done,
                'is_locked' => (bool)$ticket->is_locked,
                'locked_at' => $ticket->locked_at?->toISOString(),
                'locked_by_user_id' => $ticket->locked_by_user_id,
                'due_date' => $ticket->due_date?->toDateString(),
                'escalation_level' => (string)($ticket->escalation_level?->value ?? $ticket->escalation_level),
                'escalated_at' => $ticket->escalated_at?->toISOString(),
                'escalation_count' => (int)$ticket->escalation_count,
                'created_at' => $ticket->created_at?->toISOString(),
                'updated_at' => $ticket->updated_at?->toISOString(),
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf dieses Ticket.');
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des Tickets: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['helpdesk', 'ticket', 'get'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}


