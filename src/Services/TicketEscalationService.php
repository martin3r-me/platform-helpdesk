<?php

namespace Platform\Helpdesk\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Platform\Helpdesk\Models\{
    HelpdeskTicket,
    HelpdeskBoardEscalationRule,
    HelpdeskTicketEscalation
};
use Platform\Helpdesk\Enums\TicketEscalationLevel;
use Platform\Helpdesk\Notifications\TicketEscalatedNotification;

class TicketEscalationService
{
    /**
     * Prüft alle offenen Tickets auf Eskalations-Bedarf
     */
    public function checkEscalations(): void
    {
        Log::info('Starting ticket escalation check');

        // withoutGlobalScopes() für SLA-Beziehung verwenden, da in Console-Kontext kein Auth-User vorhanden ist
        $openTickets = HelpdeskTicket::query()
            ->where('is_done', false)
            ->whereHas('helpdeskBoard.sla', function ($query) {
                $query->where('is_active', true);
            })
            ->with([
                'helpdeskBoard' => function ($query) {
                    $query->with(['sla' => function ($query) {
                        $query->withoutGlobalScopes();
                    }]);
                },
                'userInCharge', 
                'team'
            ])
            ->get();

        $escalatedCount = 0;

        foreach ($openTickets as $ticket) {
            try {
                if ($this->checkTicketEscalation($ticket)) {
                    $escalatedCount++;
                }
            } catch (\Exception $e) {
                Log::error("Failed to check escalation for ticket {$ticket->id}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        Log::info("Escalation check completed. {$escalatedCount} tickets escalated.");
    }

    /**
     * Prüft ein einzelnes Ticket auf Eskalations-Bedarf
     */
    public function checkTicketEscalation(HelpdeskTicket $ticket): bool
    {
        // Lade SLA ohne Global Scope, falls noch nicht geladen
        $sla = $ticket->sla;
        
        // Falls SLA über Accessor null ist, versuche direkt über Board zu laden
        if (!$sla && $ticket->helpdeskBoard) {
            $sla = $ticket->helpdeskBoard->sla()->withoutGlobalScopes()->first();
        }
        
        // Keine SLA = Keine Eskalation
        if (!$sla) {
            return false;
        }

        $newLevel = $sla->getEscalationLevel($ticket);
        
        // Prüfe ob Eskalations-Level geändert hat
        if ($newLevel !== $ticket->escalation_level) {
            $this->escalateTicket($ticket, $newLevel);
            return true;
        }

        return false;
    }

    /**
     * Eskaliert ein Ticket
     */
    public function escalateTicket(HelpdeskTicket $ticket, TicketEscalationLevel $level): void
    {
        Log::info("Escalating ticket {$ticket->id} to level {$level->value}");

        // Eskalations-Historie erstellen
        $escalation = $ticket->escalations()->create([
            'escalation_level' => $level,
            'reason' => $this->generateEscalationReason($ticket, $level),
            'escalated_by_user_id' => null, // Automatische Eskalation
            'escalated_at' => now(),
            'notification_sent' => [],
        ]);

        // Ticket-Status aktualisieren
        $ticket->update([
            'escalation_level' => $level,
            'escalated_at' => now(),
            'escalation_count' => $ticket->escalation_count + 1,
        ]);

        // Benachrichtigungen senden
        $this->sendEscalationNotifications($ticket, $escalation);

        Log::info("Ticket {$ticket->id} escalated successfully");
    }

    /**
     * Generiert den Grund für die Eskalation
     */
    private function generateEscalationReason(HelpdeskTicket $ticket, TicketEscalationLevel $level): string
    {
        $reason = "Automatische Eskalation: " . $level->description();

        if ($ticket->sla) {
            $remainingTime = $ticket->sla->getRemainingTime($ticket);
            if ($remainingTime !== null) {
                if ($remainingTime < 0) {
                    $reason .= " (SLA um " . abs($remainingTime) . " Stunden überschritten)";
                } else {
                    $reason .= " (noch {$remainingTime} Stunden verbleibend)";
                }
            }
        }

        return $reason;
    }

    /**
     * Sendet Eskalations-Benachrichtigungen
     */
    private function sendEscalationNotifications(HelpdeskTicket $ticket, HelpdeskTicketEscalation $escalation): void
    {
        // Standard-Benachrichtigungen an Board-Besitzer und Team-Lead
        $notifyUserIds = [
            $ticket->helpdeskBoard->user_id, // Board-Besitzer
            $ticket->team->user_id ?? null,  // Team-Lead (falls vorhanden)
        ];

        $notifyUserIds = array_filter($notifyUserIds); // Leere Werte entfernen

        foreach ($notifyUserIds as $userId) {
            $user = \Platform\Core\Models\User::find($userId);
            if (!$user) continue;

            try {
                $user->notify(new TicketEscalatedNotification($ticket, $escalation));

                // Benachrichtigung als gesendet markieren
                $sent = $escalation->notification_sent ?? [];
                $sent[] = [
                    'user_id' => $userId,
                    'channel' => 'email',
                    'sent_at' => now()->toISOString(),
                ];
                $escalation->update(['notification_sent' => $sent]);

            } catch (\Exception $e) {
                Log::error("Failed to send escalation notification", [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Löst eine Eskalation auf
     */
    public function resolveEscalation(HelpdeskTicket $ticket, $userId = null): void
    {
        $currentEscalation = $ticket->currentEscalation();
        
        if ($currentEscalation && !$currentEscalation->isResolved()) {
            $currentEscalation->resolve($userId);
            
            // Ticket-Status zurücksetzen
            $ticket->update([
                'escalation_level' => TicketEscalationLevel::NONE,
                'escalated_at' => null,
            ]);

            Log::info("Escalation resolved for ticket {$ticket->id}");
        }
    }

    /**
     * Manuelle Eskalation
     */
    public function manuallyEscalate(HelpdeskTicket $ticket, TicketEscalationLevel $level, $userId = null, $reason = null): void
    {
        $escalation = $ticket->escalations()->create([
            'escalation_level' => $level,
            'reason' => $reason ?? "Manuelle Eskalation durch Benutzer",
            'escalated_by_user_id' => $userId,
            'escalated_at' => now(),
            'notification_sent' => [],
        ]);

        $ticket->update([
            'escalation_level' => $level,
            'escalated_at' => now(),
            'escalation_count' => $ticket->escalation_count + 1,
        ]);

        Log::info("Ticket {$ticket->id} manually escalated to {$level->value}");
    }
}
