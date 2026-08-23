<?php

namespace Platform\Helpdesk\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Platform\Helpdesk\Models\{HelpdeskTicket, HelpdeskTicketEscalation};

class TicketEscalatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public HelpdeskTicket $ticket,
        public HelpdeskTicketEscalation $escalation
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $escalationLevel = $this->escalation->escalation_level;
        $boardName = $this->ticket->helpdeskBoard?->name ?? 'Unbekanntes Board';
        
        return (new MailMessage)
            ->subject("🚨 Ticket eskaliert: {$this->ticket->title}")
            ->greeting("Hallo {$notifiable->name},")
            ->line("Ein Ticket wurde automatisch eskaliert und benötigt Ihre Aufmerksamkeit.")
            ->line("")
            ->line("**Ticket-Details:**")
            ->line("• **Titel:** {$this->ticket->title}")
            ->line("• **Board:** {$boardName}")
            ->line("• **Eskalations-Level:** {$escalationLevel->description()}")
            ->line("• **Grund:** {$this->escalation->reason}")
            ->line("• **Erstellt:** {$this->ticket->created_at->format('d.m.Y H:i')}")
            ->line("• **Eskaliert:** {$this->escalation->escalated_at->format('d.m.Y H:i')}")
            ->line("")
            ->when($this->ticket->sla, function ($message) {
                $remainingTime = $this->ticket->sla->getRemainingTime($this->ticket);
                if ($remainingTime !== null) {
                    if ($remainingTime < 0) {
                        $message->line("⚠️ **SLA überschritten um:** " . abs($remainingTime) . " Stunden");
                    } else {
                        $message->line("⏰ **Verbleibende SLA-Zeit:** {$remainingTime} Stunden");
                    }
                }
                return $message;
            })
            ->action('Ticket anzeigen', route('helpdesk.tickets.show', $this->ticket))
            ->line("Bitte überprüfen Sie das Ticket und ergreifen Sie entsprechende Maßnahmen.")
            ->salutation("Mit freundlichen Grüßen,\nIhr Helpdesk-System");
    }

    public function toArray($notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'ticket_title' => $this->ticket->title,
            'escalation_level' => $this->escalation->escalation_level->value,
            'escalation_reason' => $this->escalation->reason,
            'escalated_at' => $this->escalation->escalated_at->toISOString(),
            'board_name' => $this->ticket->helpdeskBoard?->name,
        ];
    }
}
