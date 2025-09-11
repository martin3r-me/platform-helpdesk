<?php

namespace Platform\Helpdesk\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Helpdesk\Models\HelpdeskTicket;
use Platform\Helpdesk\Models\HelpdeskBoard;
use Carbon\Carbon;

class Dashboard extends Component
{
    public $perspective = 'team';

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        // === BOARDS (nur Team-Boards) ===
        $boards = HelpdeskBoard::where('team_id', $team->id)->orderBy('name')->get();
        $activeBoards = $boards->count();
        $totalBoards = $boards->count();

        if ($this->perspective === 'personal') {
            // === PERSÖNLICHE TICKETS ===
            $myTickets = HelpdeskTicket::query()
                ->where('user_in_charge_id', $user->id)
                ->where('team_id', $team->id)
                ->get();

            $openTickets = $myTickets->where('is_done', false)->count();
            $completedTickets = $myTickets->where('is_done', true)->count();
            $totalTickets = $myTickets->count();
            $highPriorityTickets = $myTickets->where('priority', 'high')->count();
            $overdueTickets = $myTickets->where('is_done', false)
                ->filter(fn($ticket) => $ticket->due_date && $ticket->due_date->isPast())
                ->count();

            // === PERSÖNLICHE MONATLICHE PERFORMANCE ===
            $monthlyCreatedTickets = HelpdeskTicket::query()
                ->where('team_id', $team->id)
                ->where('user_in_charge_id', $user->id)
                ->whereDate('created_at', '>=', $startOfMonth)
                ->count();

            $monthlyCompletedTickets = HelpdeskTicket::query()
                ->where('team_id', $team->id)
                ->where('user_in_charge_id', $user->id)
                ->whereDate('done_at', '>=', $startOfMonth)
                ->count();

            // === PERSÖNLICHE SLA-STATISTIKEN ===
            $slaOverdueTickets = $myTickets->where('is_done', false)
                ->filter(function($ticket) {
                    if (!$ticket->sla) return false;
                    return $ticket->sla->isOverdue($ticket);
                })
                ->count();

            $slaAtRiskTickets = $myTickets->where('is_done', false)
                ->filter(function($ticket) {
                    if (!$ticket->sla) return false;
                    $remaining = $ticket->sla->getRemainingTime($ticket);
                    return $remaining !== null && $remaining <= 4; // 4 Stunden oder weniger
                })
                ->count();

            // === PERSÖNLICHE ESKALATIONS-STATISTIKEN ===
            $escalatedTickets = $myTickets->where('is_done', false)
                ->filter(fn($ticket) => $ticket->isEscalated())
                ->count();

            $criticalEscalations = $myTickets->where('is_done', false)
                ->filter(fn($ticket) => $ticket->isCritical())
                ->count();

            $escalationLevels = [
                'warning' => $myTickets->where('is_done', false)
                    ->filter(fn($ticket) => $ticket->escalation_level === \Platform\Helpdesk\Enums\TicketEscalationLevel::WARNING)
                    ->count(),
                'escalated' => $myTickets->where('is_done', false)
                    ->filter(fn($ticket) => $ticket->escalation_level === \Platform\Helpdesk\Enums\TicketEscalationLevel::ESCALATED)
                    ->count(),
                'critical' => $myTickets->where('is_done', false)
                    ->filter(fn($ticket) => $ticket->escalation_level === \Platform\Helpdesk\Enums\TicketEscalationLevel::CRITICAL)
                    ->count(),
                'urgent' => $myTickets->where('is_done', false)
                    ->filter(fn($ticket) => $ticket->escalation_level === \Platform\Helpdesk\Enums\TicketEscalationLevel::URGENT)
                    ->count(),
            ];

        } else {
            // === TEAM-TICKETS ===
            $teamTickets = HelpdeskTicket::query()
                ->where('team_id', $team->id)
                ->get();

            $openTickets = $teamTickets->where('is_done', false)->count();
            $completedTickets = $teamTickets->where('is_done', true)->count();
            $totalTickets = $teamTickets->count();
            $highPriorityTickets = $teamTickets->where('priority', 'high')->count();
            $overdueTickets = $teamTickets->where('is_done', false)
                ->filter(fn($ticket) => $ticket->due_date && $ticket->due_date->isPast())
                ->count();

            // === TEAM MONATLICHE PERFORMANCE ===
            $monthlyCreatedTickets = HelpdeskTicket::query()
                ->where('team_id', $team->id)
                ->whereDate('created_at', '>=', $startOfMonth)
                ->count();

            $monthlyCompletedTickets = HelpdeskTicket::query()
                ->where('team_id', $team->id)
                ->whereDate('done_at', '>=', $startOfMonth)
                ->count();

            // === TEAM SLA-STATISTIKEN ===
            $slaOverdueTickets = $teamTickets->where('is_done', false)
                ->filter(function($ticket) {
                    if (!$ticket->sla) return false;
                    return $ticket->sla->isOverdue($ticket);
                })
                ->count();

            $slaAtRiskTickets = $teamTickets->where('is_done', false)
                ->filter(function($ticket) {
                    if (!$ticket->sla) return false;
                    $remaining = $ticket->sla->getRemainingTime($ticket);
                    return $remaining !== null && $remaining <= 4; // 4 Stunden oder weniger
                })
                ->count();

            // === TEAM ESKALATIONS-STATISTIKEN ===
            $escalatedTickets = $teamTickets->where('is_done', false)
                ->filter(fn($ticket) => $ticket->isEscalated())
                ->count();

            $criticalEscalations = $teamTickets->where('is_done', false)
                ->filter(fn($ticket) => $ticket->isCritical())
                ->count();

            $escalationLevels = [
                'warning' => $teamTickets->where('is_done', false)
                    ->filter(fn($ticket) => $ticket->escalation_level === \Platform\Helpdesk\Enums\TicketEscalationLevel::WARNING)
                    ->count(),
                'escalated' => $teamTickets->where('is_done', false)
                    ->filter(fn($ticket) => $ticket->escalation_level === \Platform\Helpdesk\Enums\TicketEscalationLevel::ESCALATED)
                    ->count(),
                'critical' => $teamTickets->where('is_done', false)
                    ->filter(fn($ticket) => $ticket->escalation_level === \Platform\Helpdesk\Enums\TicketEscalationLevel::CRITICAL)
                    ->count(),
                'urgent' => $teamTickets->where('is_done', false)
                    ->filter(fn($ticket) => $ticket->escalation_level === \Platform\Helpdesk\Enums\TicketEscalationLevel::URGENT)
                    ->count(),
            ];
        }

        // === BOARD-ÜBERSICHT ===
        $perspective = $this->perspective;
        $activeBoardsList = $boards->map(function ($board) use ($user, $perspective) {
            if ($perspective === 'personal') {
                $boardTickets = HelpdeskTicket::where('helpdesk_board_id', $board->id)
                    ->where('user_in_charge_id', $user->id)
                    ->get();
            } else {
                $boardTickets = HelpdeskTicket::where('helpdesk_board_id', $board->id)->get();
            }
            
            return [
                'id' => $board->id,
                'name' => $board->name,
                'open_tickets' => $boardTickets->where('is_done', false)->count(),
                'total_tickets' => $boardTickets->count(),
                'high_priority' => $boardTickets->where('priority', 'high')->count(),
            ];
        })
        ->sortByDesc('open_tickets')
        ->take(5);

        return view('helpdesk::livewire.dashboard', [
            'currentDate' => now()->format('d.m.Y'),
            'currentDay' => now()->format('l'),
            'perspective' => $this->perspective,
            'activeBoards' => $activeBoards,
            'totalBoards' => $totalBoards,
            'openTickets' => $openTickets,
            'completedTickets' => $completedTickets,
            'totalTickets' => $totalTickets,
            'highPriorityTickets' => $highPriorityTickets,
            'overdueTickets' => $overdueTickets,
            'monthlyCreatedTickets' => $monthlyCreatedTickets,
            'monthlyCompletedTickets' => $monthlyCompletedTickets,
            'slaOverdueTickets' => $slaOverdueTickets,
            'slaAtRiskTickets' => $slaAtRiskTickets,
            'escalatedTickets' => $escalatedTickets,
            'criticalEscalations' => $criticalEscalations,
            'escalationLevels' => $escalationLevels,
            'activeBoardsList' => $activeBoardsList,
        ])->layout('platform::layouts.app');
    }
}